<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Partial Pledge Payments, Phase B — the portal's Add Payment action (DonationController::
 * addGuestPayment()/addDevoteePayment()), the detail modal's Pledge Summary/Payment History,
 * and the Edit Donation modal's amount field becoming read-only once a pledge has a
 * confirmed payment.
 */
class DonationAddPaymentTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Add Payment Test Event', 'slug' => 'add-payment-test-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makePledge(int $eventId, float $amount = 1000): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => 'Pledging Donor', 'amount' => $amount, 'purpose' => 'General',
            'payment_method' => null, 'payment_status' => 'Pledge', 'donation_status' => 'Pending',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_admin_can_record_a_payment_against_a_guest_pledge(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);

        $response = $this->actingAs($admin)->post(route('admin.donations.addPaymentGuest', $donationId), [
            'amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('donation_payments', [
            'donation_type' => 'guest', 'donation_id' => $donationId, 'amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);
        $this->assertDatabaseHas('donations_without_logins', [
            'id' => $donationId, 'donation_status' => 'Partially Paid',
        ]);
    }

    public function test_admin_can_record_a_payment_against_a_devotee_pledge(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = DB::table('donations')->insertGetId([
            'event_id' => $eventId, 'amount' => 500, 'payment_method' => null, 'payment_status' => 'Pledge',
            'donation_status' => 'Pending', 'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.donations.addPaymentDevotee', $donationId), [
            'amount' => 500, 'payment_method' => 'Bank Transfer', 'payment_status' => 'Completed',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('donations', ['id' => $donationId, 'donation_status' => 'Completed', 'payment_status' => 'Paid']);
    }

    public function test_a_payment_exceeding_the_outstanding_balance_is_rejected(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 300);

        $response = $this->actingAs($admin)->post(route('admin.donations.addPaymentGuest', $donationId), [
            'amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('donation_payments', ['donation_id' => $donationId]);
    }

    public function test_eft_terminal_is_not_an_offered_payment_method_on_the_add_payment_form(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 300);

        $response = $this->actingAs($admin)->post(route('admin.donations.addPaymentGuest', $donationId), [
            'amount' => 100, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Completed',
        ]);

        $response->assertSessionHasErrors('payment_method');
    }

    public function test_a_view_level_coordinator_cannot_add_a_payment(): void
    {
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 300);
        $coordinator = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('event_coordinators')->insert([
            'user_id' => $coordinator->id, 'event_id' => $eventId, 'level' => 'view',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($coordinator)->post(route('admin.donations.addPaymentGuest', $donationId), [
            'amount' => 100, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);

        $this->assertDatabaseMissing('donation_payments', ['donation_id' => $donationId]);
    }

    public function test_the_portal_shows_the_pledge_summary_and_add_payment_button(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('Pledge Summary', false);
        $response->assertSee('No payments recorded yet.', false);
        $response->assertSee(route('admin.donations.addPaymentGuest', $donationId), false);
    }

    public function test_the_edit_modal_amount_field_is_locked_once_a_payment_is_confirmed(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);
        $this->actingAs($admin)->post(route('admin.donations.addPaymentGuest', $donationId), [
            'amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('readonly', false);
        $response->assertSee('already has a confirmed payment', false);
    }

    public function test_the_edit_modal_amount_field_stays_editable_for_a_pure_pledge(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->makePledge($eventId, 1000);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertDontSee('already has a confirmed payment', false);
    }

    public function test_the_server_rejects_an_amount_change_once_a_payment_is_confirmed(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);
        $this->actingAs($admin)->post(route('admin.donations.addPaymentGuest', $donationId), [
            'amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.donations.updateGuest', $donationId), [
            'donor_name' => 'Pledging Donor', 'amount' => 1500, 'purpose' => 'General',
            'payment_method' => 'Cash', 'payment_status' => 'Partially Paid',
            'donation_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'amount' => 1000.00]);
    }
}
