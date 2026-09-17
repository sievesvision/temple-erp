<?php

namespace Tests\Feature;

use App\Models\LinklyTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the Linkly Cloud REST "Core Payments" accreditation surface added on top of the
 * existing purchase/display-status integration (see LinklyWebhookTest): starting a Purchase
 * with double-click/refresh protection, Refund (event-admin only), Logon, terminal pairing
 * from the event console, and stale-transaction recovery. See
 * LINKLY_CORE_PAYMENT_GAP_ANALYSIS.md for how each of these maps to the accreditation
 * minimum requirements.
 */
class LinklyCorePaymentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
        Setting::set('linkly_secret_sandbox', 'test-secret');
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'Admin',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);
    }

    /**
     * Inserts a few throwaway events first so the real event's id is never coincidentally
     * equal to a LinklyTransaction id created afterwards in the same test — both sequences
     * start at 1 in a fresh RefreshDatabase, which previously let a route-parameter-order bug
     * in refundEftCharge() slip past every test here undetected (event_id and transaction id
     * happened to match, so the wrong one being bound looked identical to the right one).
     */
    private function createEvent(): int
    {
        for ($i = 0; $i < 3; $i++) {
            DB::table('events')->insertGetId([
                'event_name' => 'Filler Event',
                'event_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return DB::table('events')->insertGetId([
            'event_name' => 'Test Event',
            'event_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * pos-entry equivalent: an Event Coordinator at 'entry' level for the given event — can
     * record donations for it, but must never reach Refund/Logon/Pairing (event-admin only).
     */
    private function coordinatorUser(int $eventId, string $level): User
    {
        $user = User::factory()->create([
            'role' => 'Event Coordinator',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);

        DB::table('event_coordinators')->insert([
            'user_id' => $user->id,
            'event_id' => $eventId,
            'level' => $level,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function fakeLinklyToken(): void
    {
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
        ]);
    }

    // 1 & 9. Purchase initiation persists a ledger row with a unique POS transaction
    // reference and the initiating user.
    public function test_start_charge_creates_ledger_row(): void
    {
        $eventId = $this->createEvent();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);

        $response = $this->actingAs($this->adminUser())->postJson('/admin/eft/charge/start', [
            'amount' => '25.00',
            'client_ref' => 'client-ref-1',
            'event_id' => $eventId,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $sessionId = $response->json('session_id');

        $this->assertDatabaseHas('linkly_transactions', [
            'linkly_session_id' => $sessionId,
            'txn_type' => 'purchase',
            'client_ref' => 'client-ref-1',
            'status' => 'initiated',
            'event_id' => $eventId,
        ]);
    }

    // 8. Two different checkout attempts (different client_ref) get two different, unique
    // POS transaction references.
    public function test_two_purchases_get_distinct_transaction_references(): void
    {
        $eventId = $this->createEvent();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);
        $user = $this->adminUser();

        $first = $this->actingAs($user)->postJson('/admin/eft/charge/start', ['amount' => '10.00', 'client_ref' => 'ref-a', 'event_id' => $eventId]);
        $second = $this->actingAs($user)->postJson('/admin/eft/charge/start', ['amount' => '10.00', 'client_ref' => 'ref-b', 'event_id' => $eventId]);

        $refA = LinklyTransaction::where('linkly_session_id', $first->json('session_id'))->value('pos_txn_ref');
        $refB = LinklyTransaction::where('linkly_session_id', $second->json('session_id'))->value('pos_txn_ref');

        $this->assertNotSame($refA, $refB);
    }

    // 9. Duplicate payment protection: re-submitting the SAME client_ref (double-clicked Pay,
    // or a browser refresh mid-payment) resumes the existing in-flight session instead of
    // starting a second Linkly transaction.
    public function test_duplicate_start_with_same_client_ref_resumes_without_calling_linkly_again(): void
    {
        $eventId = $this->createEvent();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);
        $user = $this->adminUser();

        $first = $this->actingAs($user)->postJson('/admin/eft/charge/start', ['amount' => '15.00', 'client_ref' => 'same-ref', 'event_id' => $eventId]);
        $second = $this->actingAs($user)->postJson('/admin/eft/charge/start', ['amount' => '15.00', 'client_ref' => 'same-ref', 'event_id' => $eventId]);

        $second->assertOk()->assertJson(['success' => true, 'resumed' => true]);
        $this->assertSame($first->json('session_id'), $second->json('session_id'));

        Http::assertSentCount(2); // one token request + exactly one transaction POST
        $this->assertSame(1, LinklyTransaction::where('client_ref', 'same-ref')->count());
    }

    // 14. Transaction recovery: a purchase that's been neither approved/declined/cancelled
    // nor failed for far longer than Linkly's own transaction window is reported UNKNOWN
    // (never silently declined), and polling stops (done: true) rather than looping forever.
    public function test_stale_in_progress_transaction_is_reported_unknown(): void
    {
        $eventId = $this->createEvent();
        $user = $this->adminUser();
        $sessionId = (string) \Illuminate\Support\Str::uuid();

        $txn = LinklyTransaction::create([
            'pos_txn_ref' => 'EFTSTALE1',
            'client_ref' => 'stale-ref',
            'linkly_session_id' => $sessionId,
            'txn_type' => 'purchase',
            'event_id' => $eventId,
            'amount' => 20,
            'status' => 'initiated',
            'initiated_by' => $user->id,
        ]);
        // create() stamps created_at/updated_at itself, so back-date it with a plain query
        // afterwards rather than fighting Eloquent's automatic timestamps.
        DB::table('linkly_transactions')->where('id', $txn->id)->update(['created_at' => now()->subMinutes(5)]);

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => Http::response(null, 202),
        ]);

        $response = $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}?event_id={$eventId}");

        $response->assertOk()->assertJson(['payment_status' => 'unknown', 'done' => true]);
        $this->assertDatabaseHas('linkly_transactions', ['linkly_session_id' => $sessionId, 'status' => 'unknown']);
    }

    // 18. A pos-entry-level user (Event Coordinator at 'entry') must never reach Refund,
    // Logon or Pairing — event-admin only, enforced server-side regardless of any UI.
    public function test_entry_level_coordinator_cannot_refund(): void
    {
        $eventId = $this->createEvent();
        $entryUser = $this->coordinatorUser($eventId, 'entry');
        $adminUser = $this->adminUser();

        $original = LinklyTransaction::create([
            'pos_txn_ref' => 'EFTORIG01',
            'linkly_session_id' => (string) \Illuminate\Support\Str::uuid(),
            'txn_type' => 'purchase',
            'event_id' => $eventId,
            'amount' => 50,
            'status' => 'approved',
            'initiated_by' => $adminUser->id,
        ]);

        $response = $this->actingAs($entryUser)->postJson("/admin/events/{$eventId}/eft/refund/{$original->id}", [
            'amount' => '50.00',
            'client_ref' => 'refund-attempt',
        ]);

        $response->assertStatus(403);
    }

    public function test_entry_level_coordinator_cannot_logon_or_pair(): void
    {
        $eventId = $this->createEvent();
        $entryUser = $this->coordinatorUser($eventId, 'entry');

        $this->actingAs($entryUser)->post("/admin/events/{$eventId}/eft/logon")->assertStatus(403);
        $this->actingAs($entryUser)->post("/admin/events/{$eventId}/eft/pair", ['pair_code' => '123456'])->assertStatus(403);
    }

    // 11. A successful refund by an event-admin coordinator: creates a new ledger row linked
    // back to the original purchase, with the acting user recorded as both initiator and
    // authoriser.
    public function test_event_admin_coordinator_can_refund_approved_purchase(): void
    {
        $eventId = $this->createEvent();
        $adminCoordinator = $this->coordinatorUser($eventId, 'admin');

        $original = LinklyTransaction::create([
            'pos_txn_ref' => 'EFTORIG02',
            'linkly_session_id' => (string) \Illuminate\Support\Str::uuid(),
            'txn_type' => 'purchase',
            'event_id' => $eventId,
            'amount' => 40,
            'currency_code' => 'AUD',
            'status' => 'approved',
            'initiated_by' => $adminCoordinator->id,
        ]);

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);

        $response = $this->actingAs($adminCoordinator)->postJson("/admin/events/{$eventId}/eft/refund/{$original->id}", [
            'amount' => '40.00',
            'client_ref' => 'refund-ok',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('linkly_transactions', [
            'original_transaction_id' => $original->id,
            'txn_type' => 'refund',
            'initiated_by' => $adminCoordinator->id,
            'authorised_by' => $adminCoordinator->id,
        ]);

        // The RFN PAD tag must carry the ORIGINAL purchase's own TxnRef, matching Linkly's
        // "all refunds must be matched to an original purchase" requirement.
        Http::assertSent(function ($request) use ($original) {
            return str_contains($request->url(), '/transaction')
                && ($request['Request']['PurchaseAnalysisData']['RFN'] ?? null) === $original->pos_txn_ref;
        });
    }

    // 12. Duplicate refund protection: a second refund attempt against an already-approved
    // refund is blocked.
    public function test_duplicate_refund_is_blocked(): void
    {
        $eventId = $this->createEvent();
        $adminCoordinator = $this->coordinatorUser($eventId, 'admin');

        $original = LinklyTransaction::create([
            'pos_txn_ref' => 'EFTORIG03',
            'linkly_session_id' => (string) \Illuminate\Support\Str::uuid(),
            'txn_type' => 'purchase',
            'event_id' => $eventId,
            'amount' => 30,
            'status' => 'approved',
            'initiated_by' => $adminCoordinator->id,
        ]);
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTRFD01',
            'linkly_session_id' => (string) \Illuminate\Support\Str::uuid(),
            'txn_type' => 'refund',
            'event_id' => $eventId,
            'amount' => 30,
            'status' => 'approved',
            'original_transaction_id' => $original->id,
            'initiated_by' => $adminCoordinator->id,
            'authorised_by' => $adminCoordinator->id,
        ]);

        $response = $this->actingAs($adminCoordinator)->postJson("/admin/events/{$eventId}/eft/refund/{$original->id}", [
            'amount' => '30.00',
            'client_ref' => 'second-attempt',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    // A purchase that has not been approved (declined/cancelled/failed/still in progress)
    // cannot be refunded at all.
    public function test_unapproved_purchase_cannot_be_refunded(): void
    {
        $eventId = $this->createEvent();
        $adminCoordinator = $this->coordinatorUser($eventId, 'admin');

        $original = LinklyTransaction::create([
            'pos_txn_ref' => 'EFTORIG04',
            'linkly_session_id' => (string) \Illuminate\Support\Str::uuid(),
            'txn_type' => 'purchase',
            'event_id' => $eventId,
            'amount' => 20,
            'status' => 'declined',
            'initiated_by' => $adminCoordinator->id,
        ]);

        $response = $this->actingAs($adminCoordinator)->postJson("/admin/events/{$eventId}/eft/refund/{$original->id}", [
            'amount' => '20.00',
            'client_ref' => 'attempt',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    // 13. Logon is available to event-admin level and reflects the actual Linkly result
    // rather than being faked as always-successful.
    public function test_logon_records_actual_linkly_result(): void
    {
        $eventId = $this->createEvent();
        $admin = $this->adminUser();

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response(['response' => ['success' => false, 'responseText' => 'TERMINAL BUSY']], 200),
        ]);

        $response = $this->actingAs($admin)->post("/admin/events/{$eventId}/eft/logon");

        $response->assertRedirect();
        $this->assertDatabaseHas('linkly_transactions', [
            'txn_type' => 'logon',
            'event_id' => $eventId,
            'status' => 'failed',
            'response_text' => 'TERMINAL BUSY',
        ]);
    }
}
