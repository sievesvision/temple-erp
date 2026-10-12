<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CashSettlementService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Only a Pending transaction can be edited, and clicking one goes straight to the POS page
 * prefilled" — the Current Period Transactions pane links a Pending guest donation straight
 * to PosDonationController::show()'s own ?edit_id= handling (devotee rows have no POS
 * equivalent at all — see PosDonationController's class docblock — so they keep the pane's
 * own inline edit instead). Completing it POSTs through the pre-existing
 * admin.donations.updateGuest route, updating the SAME row rather than creating a new one.
 */
class PosPendingTransactionEditTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Pending Edit Test Event', 'slug' => 'pending-edit-test-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertGuestDonation(int $eventId, string $status, string $method = 'Bank'): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => 'Pending Donor', 'amount' => 60,
            'payment_method' => $method, 'payment_status' => $status, 'purpose' => 'General',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_pending_guest_donation_gets_a_complete_in_pos_link_on_the_pane(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Pending');

        $response = $this->actingAs($admin)->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertSee('Complete in POS');
        $response->assertSee('Pending');
    }

    public function test_a_paid_donation_shows_no_edit_action(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Paid', 'Cash');

        $response = $this->actingAs($admin)->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertDontSee('Complete in POS');
    }

    public function test_visiting_the_pos_page_with_edit_id_prefills_the_form(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($eventId, 'Pending');

        $response = $this->actingAs($admin)->get(route('admin.events.pos', ['event' => $eventId, 'edit_id' => $donationId]));

        $response->assertOk();
        $response->assertSee('Completing a pending transaction for Pending Donor');
        // UPDATE_GUEST_URL is JSON-encoded into the page's own <script> (Laravel's @json
        // escapes forward slashes, so the raw route() URL never appears verbatim) — the
        // donation id is the one unambiguous, unescaped marker that it was actually built
        // from THIS donation rather than left null.
        $response->assertSee('update-guest\/' . $donationId, false);
    }

    public function test_edit_id_for_an_already_paid_donation_is_ignored(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($eventId, 'Paid', 'Cash');

        $response = $this->actingAs($admin)->get(route('admin.events.pos', ['event' => $eventId, 'edit_id' => $donationId]));

        $response->assertOk();
        $response->assertDontSee('Completing a pending transaction');
    }

    public function test_edit_id_belonging_to_another_event_is_ignored(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $otherEventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($otherEventId, 'Pending');

        $response = $this->actingAs($admin)->get(route('admin.events.pos', ['event' => $eventId, 'edit_id' => $donationId]));

        $response->assertOk();
        $response->assertDontSee('Completing a pending transaction');
    }

    public function test_completing_a_pending_transaction_updates_the_same_row(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($eventId, 'Pending');

        $before = DB::table('donations_without_logins')->count();

        $response = $this->actingAs($admin)->post(route('admin.donations.updateGuest', $donationId), [
            'donor_name' => 'Pending Donor', 'event_id' => $eventId, 'amount' => 60,
            'purpose' => 'General', 'payment_method' => 'Bank', 'payment_status' => 'Paid',
            'donation_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertSame($before, DB::table('donations_without_logins')->count());
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'payment_status' => 'Paid']);

        // Having been completed, it no longer matches PosDonationController::show()'s own
        // Pending-only edit_id lookup — the banner/prefill simply stop appearing.
        $followUp = $this->actingAs($admin)->get(route('admin.events.pos', ['event' => $eventId, 'edit_id' => $donationId]));
        $followUp->assertDontSee('Completing a pending transaction');
    }

    /**
     * The POS page's own Current Period Transactions widget only ever shows the live,
     * not-yet-settled session — browsing a past settlement's own transactions (by date
     * range or any other filter) belongs on the Portal's All Transactions pane instead, not
     * here. A crafted ?settlement_id= query string is simply ignored.
     */
    public function test_the_pos_page_only_ever_shows_the_current_session_never_a_past_settlement(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $yesterday = now()->subDay()->toDateString();
        DB::table('donations_without_logins')->insert([
            'event_id' => $eventId, 'donor_name' => 'Settled Donor', 'amount' => 40,
            'payment_method' => 'Cash', 'payment_status' => 'Paid', 'purpose' => 'General',
            'donation_date' => $yesterday, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $settlement = CashSettlementService::run('event', $eventId, $admin->id, 40.0, 0.0, null, now()->subDay());
        $this->insertGuestDonation($eventId, 'Pending');

        $liveView = $this->actingAs($admin)->get(route('admin.events.pos', $eventId));
        $liveView->assertOk();
        $liveView->assertSee('Pending Donor');
        $liveView->assertDontSee('Settled Donor');

        $withQueryParam = $this->actingAs($admin)->get(route('admin.events.pos', ['event' => $eventId, 'settlement_id' => $settlement->id]));
        $withQueryParam->assertOk();
        $withQueryParam->assertSee('Pending Donor');
        $withQueryParam->assertDontSee('Settled Donor');
    }
}
