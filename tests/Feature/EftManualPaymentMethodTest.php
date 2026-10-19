<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "EFT Manual" — the portal's Add Transaction modal's own option for recording a terminal-
 * card payment that was physically taken but never driven through this app's own real-time
 * EFT Terminal charge flow (so there's no sci_transactions/linkly_transactions row for it),
 * e.g. reconstructing a transaction after the fact. Distinct from 'EFT Terminal', which
 * stays reserved for — and immutable because of — a payment this app drove end to end.
 */
class EftManualPaymentMethodTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'EFT Manual Test Event', 'slug' => 'eft-manual-test-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_guest_donation_can_be_recorded_as_eft_manual(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->post(route('admin.events.portal.storeGuest', $eventId), [
            'donor_name' => 'Recovered Donor', 'event_id' => $eventId, 'amount' => 51,
            'purpose' => 'General Donation', 'payment_method' => 'EFT Manual',
            'payment_status' => 'Paid', 'donation_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('donations_without_logins', [
            'donor_name' => 'Recovered Donor', 'payment_method' => 'EFT Manual', 'payment_status' => 'Paid',
        ]);
    }

    public function test_an_eft_manual_guest_donation_can_still_be_edited_unlike_a_real_eft_terminal_one(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => 'Recovered Donor', 'amount' => 51,
            'payment_method' => 'EFT Manual', 'payment_status' => 'Paid', 'purpose' => 'General',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.donations.updateGuest', $donationId), [
            'donor_name' => 'Recovered Donor', 'amount' => 55, 'purpose' => 'General',
            'payment_method' => 'EFT Manual', 'payment_status' => 'Paid',
            'donation_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'amount' => 55.00]);
    }

    public function test_a_devotee_donation_can_be_recorded_as_eft_manual(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $devoteeUser = User::factory()->create(['role' => 'Devotee', 'mobile' => fake()->unique()->numerify('04########')]);
        $devotee = \App\Models\Devotee::create(['user_id' => $devoteeUser->id, 'verified' => 1]);

        $response = $this->actingAs($admin)->post(route('admin.events.portal.storeDevotee', $eventId), [
            'devotee_id' => $devotee->devotee_id, 'event_id' => $eventId, 'amount' => 75,
            'payment_mode' => 'EFT Manual', 'payment_status' => 'Pending',
            'donation_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('donations', [
            'devotee_id' => $devotee->devotee_id, 'payment_method' => 'EFT Manual', 'payment_status' => 'Pending',
        ]);
    }

    public function test_the_portal_add_transaction_modal_offers_eft_manual_and_defaults_to_pending(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee("eftManualOpt.value = 'EFT Manual'", false);
        $response->assertSee("paymentStatusSelect.value = 'Pending'", false);
    }
}
