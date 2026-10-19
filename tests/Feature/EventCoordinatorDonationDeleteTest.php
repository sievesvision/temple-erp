<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Delete was previously the one donation action an Event Coordinator could never reach
 * regardless of their per-event level — DonationController::deleteGuestDonation()/
 * deleteDevoteeDonation() only ever checked RolePermission::can(..., 'donations', 'delete'),
 * with no EventCoordinatorLevel fallback the way approve/edit/resend already had. An
 * event-admin or event-entry coordinator now gets the same bar those actions use; 'view'
 * level still doesn't.
 */
class EventCoordinatorDonationDeleteTest extends TestCase
{
    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Coordinator Delete Test Event', 'slug' => 'coordinator-delete-test-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function coordinator(int $eventId, string $level): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => $level,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    private function guestDonation(int $eventId): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => 'Delete Me', 'amount' => 40,
            'payment_method' => 'Cash', 'payment_status' => 'Pending', 'purpose' => 'General',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_an_entry_level_coordinator_can_delete_a_guest_donation_for_their_event(): void
    {
        $eventId = $this->makeEvent();
        $coordinator = $this->coordinator($eventId, 'entry');
        $donationId = $this->guestDonation($eventId);

        $response = $this->actingAs($coordinator)->delete(route('admin.donations.deleteGuest', $donationId));

        $response->assertRedirect();
        $this->assertDatabaseMissing('donations_without_logins', ['id' => $donationId]);
    }

    public function test_an_admin_level_coordinator_can_delete_a_guest_donation_for_their_event(): void
    {
        $eventId = $this->makeEvent();
        $coordinator = $this->coordinator($eventId, 'admin');
        $donationId = $this->guestDonation($eventId);

        $response = $this->actingAs($coordinator)->delete(route('admin.donations.deleteGuest', $donationId));

        $response->assertRedirect();
        $this->assertDatabaseMissing('donations_without_logins', ['id' => $donationId]);
    }

    public function test_a_view_level_coordinator_still_cannot_delete(): void
    {
        $eventId = $this->makeEvent();
        $coordinator = $this->coordinator($eventId, 'view');
        $donationId = $this->guestDonation($eventId);

        $this->actingAs($coordinator)->delete(route('admin.donations.deleteGuest', $donationId));

        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId]);
    }

    public function test_an_entry_level_coordinator_for_a_different_event_cannot_delete(): void
    {
        $eventId = $this->makeEvent();
        $otherEventId = $this->makeEvent();
        $coordinator = $this->coordinator($otherEventId, 'entry');
        $donationId = $this->guestDonation($eventId);

        $this->actingAs($coordinator)->delete(route('admin.donations.deleteGuest', $donationId));

        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId]);
    }

    public function test_an_entry_level_coordinator_can_delete_a_devotee_donation_for_their_event(): void
    {
        $eventId = $this->makeEvent();
        $coordinator = $this->coordinator($eventId, 'entry');
        $donationId = DB::table('donations')->insertGetId([
            'event_id' => $eventId, 'amount' => 60, 'payment_method' => 'Cash', 'payment_status' => 'Pending',
            'purpose' => 'General', 'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($coordinator)->delete(route('admin.donations.deleteDevotee', $donationId));

        $response->assertRedirect();
        $this->assertDatabaseMissing('donations', ['id' => $donationId]);
    }

    public function test_the_event_console_shows_the_delete_action_to_an_entry_level_coordinator(): void
    {
        $eventId = $this->makeEvent();
        $coordinator = $this->coordinator($eventId, 'entry');
        $donationId = $this->guestDonation($eventId);

        $response = $this->actingAs($coordinator)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee(route('admin.donations.deleteGuest', $donationId), false);
    }
}
