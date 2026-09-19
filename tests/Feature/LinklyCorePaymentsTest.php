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
        $this->defaultEftTerminal();
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

    // A completed EFT Terminal purchase's ledger row is linked to the resulting donation
    // record end-to-end (start -> approve -> save), the way the browser actually drives it —
    // not just asserted at the unit level, since this is the join the EFTPOS pane's
    // "Donation" column and refund lookups both depend on.
    public function test_approved_eft_purchase_links_to_the_resulting_donation(): void
    {
        $eventId = $this->createEvent();
        $user = $this->adminUser();

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);
        $start = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'amount' => '25.00',
            'client_ref' => 'link-test',
            'event_id' => $eventId,
        ]);
        $sessionId = $start->json('session_id');

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => Http::response([
                'response' => ['success' => true, 'responseCode' => '00', 'authCode' => 1, 'rrn' => '000001', 'txnRef' => 'EFTXYZ'],
            ], 200),
        ]);
        $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}?event_id={$eventId}")->assertOk();

        $store = $this->actingAs($user)->postJson('/admin/donation/store-guest', [
            'donor_name' => 'Jane Donor',
            'event_id' => $eventId,
            'amount' => '25.00',
            'purpose' => 'General Donation',
            'payment_method' => 'EFT Terminal',
            'transaction_id' => '000001',
            'donation_date' => now()->toDateString(),
            'linkly_session_id' => $sessionId,
        ]);
        $store->assertOk()->assertJson(['success' => true]);

        $donationId = DB::table('donations_without_logins')->where('donor_name', 'Jane Donor')->value('id');
        $this->assertNotNull($donationId);
        $this->assertDatabaseHas('linkly_transactions', [
            'linkly_session_id' => $sessionId,
            'donation_type' => 'guest',
            'donation_id' => $donationId,
        ]);
    }

    // A refund that Linkly approves marks the underlying donation 'Cancelled' (the existing
    // status this app already uses for a reversed payment — see the "Cancelled/Failed
    // (excluded)" handling in manageDonations()), so it stops counting in the temple's totals
    // without a separate manual edit.
    public function test_approved_refund_marks_donation_cancelled(): void
    {
        $eventId = $this->createEvent();
        $adminCoordinator = $this->coordinatorUser($eventId, 'admin');

        $donationId = DB::table('donations_without_logins')->insertGetId([
            'donor_name' => 'Jane Donor',
            'event_id' => $eventId,
            'amount' => 25,
            'purpose' => 'General Donation',
            'payment_method' => 'EFT Terminal',
            'payment_status' => 'Paid',
            'transaction_id' => '000001',
            'donation_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $original = LinklyTransaction::create([
            'pos_txn_ref' => 'EFTORIG05',
            'linkly_session_id' => (string) \Illuminate\Support\Str::uuid(),
            'txn_type' => 'purchase',
            'event_id' => $eventId,
            'donation_type' => 'guest',
            'donation_id' => $donationId,
            'amount' => 25,
            'status' => 'approved',
            'initiated_by' => $adminCoordinator->id,
        ]);

        // A single fake for the whole test: Http::fake() calls stack rather than replace, so
        // registering a second one after starting the refund would not actually override the
        // first "*/sessions/*/transaction*" stub for the later GET poll (first-registered
        // pattern wins) — differentiate by HTTP method instead, in one registration.
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => function ($request) {
                if (strtoupper($request->method()) === 'GET') {
                    return Http::response(['response' => ['success' => true, 'responseCode' => '00', 'authCode' => 2, 'rrn' => '000002']], 200);
                }
                return Http::response('', 202);
            },
        ]);

        $refund = $this->actingAs($adminCoordinator)->postJson("/admin/events/{$eventId}/eft/refund/{$original->id}", [
            'amount' => '25.00',
            'client_ref' => 'refund-cancel-test',
        ]);
        $refundSessionId = $refund->json('session_id');

        $this->actingAs($adminCoordinator)->getJson("/admin/eft/charge/status/{$refundSessionId}?event_id={$eventId}")->assertOk();

        $this->assertDatabaseHas('donations_without_logins', [
            'id' => $donationId,
            'payment_status' => 'Cancelled',
        ]);
    }

    // The server-side recovery fallback this was all added for: if the browser never gets to
    // call storeGuestDonation() itself (a crash, a refresh, or the Cancel-button incident that
    // motivated this), an approved purchase's donation is still saved automatically the
    // moment ANY poll (the browser's own loop, or a manual "Check Status" click — same
    // endpoint either way) discovers the approval, using the donor details captured back at
    // startEftCharge() time.
    public function test_approved_purchase_auto_creates_donation_without_a_separate_save_call(): void
    {
        $eventId = $this->createEvent();
        $user = $this->adminUser();

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => function ($request) {
                if (strtoupper($request->method()) === 'GET') {
                    return Http::response(['response' => ['success' => true, 'responseCode' => '00', 'authCode' => 5, 'rrn' => '000005']], 200);
                }
                return Http::response('', 202);
            },
        ]);

        $start = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'amount' => '30.00',
            'client_ref' => 'auto-create-test',
            'event_id' => $eventId,
            'donor_name' => 'Auto Created Donor',
            'email' => 'donor@example.com',
            'purpose' => 'General Donation',
        ]);
        $sessionId = $start->json('session_id');

        // Only ever poll — deliberately never call storeGuestDonation() in this test.
        $poll = $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}?event_id={$eventId}");
        $poll->assertOk()->assertJson(['payment_status' => 'approved', 'done' => true, 'success' => true]);
        $this->assertNotNull($poll->json('donation_id'));

        $this->assertDatabaseHas('donations_without_logins', [
            'id' => $poll->json('donation_id'),
            'donor_name' => 'Auto Created Donor',
            'amount' => 30,
            'payment_method' => 'EFT Terminal',
            'payment_status' => 'Paid',
        ]);
        $this->assertDatabaseHas('linkly_transactions', [
            'linkly_session_id' => $sessionId,
            'donation_type' => 'guest',
            'donation_id' => $poll->json('donation_id'),
        ]);
    }

    // Polling twice after approval (e.g. the browser's own loop, then a manual "Check Status"
    // click) must not create a second donation for the same charge.
    public function test_polling_twice_after_approval_does_not_duplicate_the_donation(): void
    {
        $eventId = $this->createEvent();
        $user = $this->adminUser();

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => function ($request) {
                if (strtoupper($request->method()) === 'GET') {
                    return Http::response(['response' => ['success' => true, 'responseCode' => '00', 'authCode' => 6, 'rrn' => '000006']], 200);
                }
                return Http::response('', 202);
            },
        ]);

        $start = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'amount' => '15.00',
            'client_ref' => 'double-poll-test',
            'event_id' => $eventId,
            'donor_name' => 'Double Poll Donor',
        ]);
        $sessionId = $start->json('session_id');

        $first = $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}?event_id={$eventId}");
        $second = $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}?event_id={$eventId}");

        $this->assertSame($first->json('donation_id'), $second->json('donation_id'));
        $this->assertSame(1, DB::table('donations_without_logins')->where('donor_name', 'Double Poll Donor')->count());
    }

    // If the browser's own storeGuestDonation() call arrives anyway after the server-side
    // fallback already saved the donation, it must confirm success without inserting a
    // second row (or sending a second receipt email).
    public function test_store_guest_donation_is_idempotent_after_auto_create(): void
    {
        $eventId = $this->createEvent();
        $user = $this->adminUser();

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => function ($request) {
                if (strtoupper($request->method()) === 'GET') {
                    return Http::response(['response' => ['success' => true, 'responseCode' => '00', 'authCode' => 7, 'rrn' => '000007']], 200);
                }
                return Http::response('', 202);
            },
        ]);

        $start = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'amount' => '12.00',
            'client_ref' => 'idempotent-save-test',
            'event_id' => $eventId,
            'donor_name' => 'Idempotent Donor',
        ]);
        $sessionId = $start->json('session_id');

        $poll = $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}?event_id={$eventId}");
        $autoCreatedId = $poll->json('donation_id');
        $this->assertNotNull($autoCreatedId);

        $store = $this->actingAs($user)->postJson('/admin/donation/store-guest', [
            'donor_name' => 'Idempotent Donor',
            'event_id' => $eventId,
            'amount' => '12.00',
            'purpose' => 'General Donation',
            'payment_method' => 'EFT Terminal',
            'transaction_id' => '000007',
            'donation_date' => now()->toDateString(),
            'linkly_session_id' => $sessionId,
        ]);

        $store->assertOk()->assertJson(['success' => true, 'donation_id' => $autoCreatedId]);
        $this->assertSame(1, DB::table('donations_without_logins')->where('donor_name', 'Idempotent Donor')->count());
    }
}
