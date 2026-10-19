<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Pledge" — the portal's Add Transaction modal's own payment status for a donor's
 * commitment to pay later, with no payment method chosen at all (distinct from Pending,
 * which already has a method and is just awaiting confirmation it landed).
 */
class DonationPledgeTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Pledge Test Event', 'slug' => 'pledge-test-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_guest_pledge_can_be_recorded_without_a_payment_method(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->post(route('admin.events.portal.storeGuest', $eventId), [
            'donor_name' => 'Pledging Donor', 'event_id' => $eventId, 'amount' => 200,
            'purpose' => 'General Donation', 'payment_status' => 'Pledge',
            'donation_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('donations_without_logins', [
            'donor_name' => 'Pledging Donor', 'payment_method' => null, 'payment_status' => 'Pledge',
        ]);
    }

    public function test_a_devotee_pledge_can_be_recorded_without_a_payment_method(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $devoteeUser = User::factory()->create(['role' => 'Devotee', 'mobile' => fake()->unique()->numerify('04########')]);
        $devotee = \App\Models\Devotee::create(['user_id' => $devoteeUser->id, 'verified' => 1]);

        $response = $this->actingAs($admin)->post(route('admin.events.portal.storeDevotee', $eventId), [
            'devotee_id' => $devotee->devotee_id, 'event_id' => $eventId, 'amount' => 100,
            'payment_status' => 'Pledge', 'donation_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('donations', [
            'devotee_id' => $devotee->devotee_id, 'payment_method' => null, 'payment_status' => 'Pledge',
        ]);
    }

    public function test_a_non_pledge_guest_donation_still_requires_a_payment_method(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->post(route('admin.events.portal.storeGuest', $eventId), [
            'donor_name' => 'No Method Donor', 'event_id' => $eventId, 'amount' => 60,
            'purpose' => 'General Donation', 'payment_status' => 'Pending',
            'donation_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('payment_method');
    }

    public function test_a_pledge_can_later_be_edited_to_a_real_payment_method_and_status(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => 'Pledging Donor', 'amount' => 200,
            'payment_method' => null, 'payment_status' => 'Pledge', 'purpose' => 'General',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.donations.updateGuest', $donationId), [
            'donor_name' => 'Pledging Donor', 'amount' => 200, 'purpose' => 'General',
            'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'donation_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('donations_without_logins', [
            'id' => $donationId, 'payment_method' => 'Cash', 'payment_status' => 'Paid',
        ]);
    }

    public function test_the_portal_add_transaction_modal_offers_a_pledge_option(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('Pledge (no payment yet)', false);
        $response->assertSee('qePledgeHint', false);
    }
}
