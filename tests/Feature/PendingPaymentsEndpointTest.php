<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PosDonationController::pendingPayments() — the Pending Transactions popup's data source.
 * Fetched fresh on every open (and after every save) rather than rendered once into the
 * page, so it never goes stale the way a server-rendered-once list would once Save Donation
 * (a plain fetch() with no page reload) completes or creates a pending transaction.
 */
class PendingPaymentsEndpointTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Pending Payments Endpoint Test Event', 'slug' => 'pending-payments-endpoint-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertGuestDonation(int $eventId, array $overrides = []): int
    {
        return DB::table('donations_without_logins')->insertGetId(array_merge([
            'event_id' => $eventId, 'donor_name' => 'Pending Donor', 'amount' => 60,
            'is_anonymous' => false, 'email' => 'pending.donor@example.com', 'mobile' => '0412345678',
            'payment_method' => 'Bank', 'payment_status' => 'Pending', 'donation_status' => 'Pending', 'purpose' => 'General',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    public function test_returns_masked_mobile_and_email_not_the_real_values(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.pending-payments', $eventId));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('Pending Donor', $results[0]['name']);
        $this->assertStringContainsString('•', $results[0]['masked_mobile']);
        $this->assertStringContainsString('•', $results[0]['masked_email']);
        $this->assertStringNotContainsString('0412345678', json_encode($results));
        $this->assertStringNotContainsString('pending.donor@example.com', json_encode($results));
    }

    public function test_q_matches_against_the_real_unmasked_mobile(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.pending-payments', ['event' => $eventId, 'q' => '412345']));

        $response->assertOk();
        $this->assertCount(1, $response->json('results'));
    }

    public function test_q_with_no_match_returns_an_empty_list(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.pending-payments', ['event' => $eventId, 'q' => 'nobody-matches-this']));

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
    }

    public function test_excludes_a_paid_donation(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, ['payment_status' => 'Paid', 'donation_status' => 'Completed']);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.pending-payments', $eventId));

        $this->assertSame([], $response->json('results'));
    }

    public function test_excludes_a_pending_devotee_donation(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $devoteeUser = User::factory()->create(['name' => 'Priya Nathan', 'email' => 'priya@example.com', 'mobile' => '0423111222', 'role' => 'Devotee']);
        $devotee = \App\Models\Devotee::create(['user_id' => $devoteeUser->id, 'verified' => 1]);
        DB::table('donations')->insert([
            'devotee_id' => $devotee->devotee_id, 'event_id' => $eventId, 'amount' => 40,
            'payment_method' => 'Bank', 'payment_status' => 'Pending', 'donation_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.pending-payments', $eventId));

        $this->assertSame([], $response->json('results'));
    }

    public function test_the_edit_url_points_at_this_events_pos_page_with_edit_id(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($eventId);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.pending-payments', $eventId));

        $this->assertStringContainsString('edit_id=' . $donationId, $response->json('results.0.edit_url'));
    }

    public function test_a_role_outside_the_pos_group_cannot_reach_the_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = $this->makeEvent();

        $response = $this->actingAs($staff)->get(route('admin.events.pos.pending-payments', $eventId));

        $response->assertRedirect();
    }
}
