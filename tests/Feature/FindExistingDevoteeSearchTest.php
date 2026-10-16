<?php

namespace Tests\Feature;

use App\Models\Devotee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Event POS's "Find Existing Devotee" search — PosDonationController::searchDevotee().
 * Pure prefill lookup: it returns a masked preview for display plus the real values a
 * selected result copies into the Donor Details form, but never links the donation this
 * form eventually saves to whatever it matched, and never touches an older transaction's
 * own stored name/email/mobile.
 */
class FindExistingDevoteeSearchTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Find Devotee Test Event', 'slug' => 'find-devotee-test-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertGuestDonation(int $eventId, array $overrides = []): int
    {
        return DB::table('donations_without_logins')->insertGetId(array_merge([
            'event_id' => $eventId, 'donor_name' => 'Ravi Kumar', 'amount' => 50,
            'is_anonymous' => false, 'email' => 'ravi.kumar@example.com', 'mobile' => '0412345678',
            'payment_method' => 'Cash', 'payment_status' => 'Paid', 'purpose' => 'General',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    public function test_a_query_under_two_characters_returns_no_results(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => 'R']));

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
    }

    public function test_finds_a_past_guest_donation_by_name_and_masks_contact_details(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => 'Ravi']));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('Ravi Kumar', $results[0]['name']);
        // The real values are what Select actually copies into the form.
        $this->assertSame('ravi.kumar@example.com', $results[0]['email']);
        $this->assertSame('0412345678', $results[0]['mobile']);
        // The displayed preview is masked, not the raw value.
        $this->assertNotSame('ravi.kumar@example.com', $results[0]['masked_email']);
        $this->assertStringContainsString('@example.com', $results[0]['masked_email']);
        $this->assertStringContainsString('•', $results[0]['masked_email']);
        $this->assertStringContainsString('•', $results[0]['masked_mobile']);
        $this->assertStringEndsWith('678', $results[0]['masked_mobile']);
    }

    public function test_finds_a_past_guest_donation_by_email_or_mobile_too(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId);

        $byEmail = $this->actingAs($admin)->getJson(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => 'ravi.kumar']))->json('results');
        $byMobile = $this->actingAs($admin)->getJson(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => '412345']))->json('results');

        $this->assertCount(1, $byEmail);
        $this->assertCount(1, $byMobile);
    }

    public function test_excludes_anonymous_donations(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, ['is_anonymous' => true]);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => 'Ravi']));

        $this->assertSame([], $response->json('results'));
    }

    public function test_excludes_the_well_wisher_placeholder_used_by_anonymous_no_details(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, [
            'donor_name' => 'The well wisher', 'email' => 'admin@hasq.org', 'is_anonymous' => true,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => 'well']));

        $this->assertSame([], $response->json('results'));
    }

    public function test_excludes_a_guest_row_missing_an_email(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, ['donor_name' => 'No Email Donor', 'email' => null]);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => 'No Email']));

        $this->assertSame([], $response->json('results'));
    }

    public function test_finds_a_registered_devotee_by_name(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $devoteeUser = User::factory()->create(['name' => 'Priya Nathan', 'email' => 'priya.nathan@example.com', 'mobile' => '0423111222', 'role' => 'Devotee']);
        Devotee::create(['user_id' => $devoteeUser->id, 'verified' => 1]);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => 'Priya']));

        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('Priya Nathan', $results[0]['name']);
        $this->assertSame('priya.nathan@example.com', $results[0]['email']);
    }

    /**
     * Same email on both a registered devotee and an old guest donation — shown once, the
     * devotee record wins (more authoritative than a one-off walk-up entry).
     */
    public function test_dedupes_a_devotee_and_guest_entry_sharing_the_same_email(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $devoteeUser = User::factory()->create(['name' => 'Account Name', 'email' => 'shared@example.com', 'mobile' => '0499999999', 'role' => 'Devotee']);
        Devotee::create(['user_id' => $devoteeUser->id, 'verified' => 1]);
        $this->insertGuestDonation($eventId, ['donor_name' => 'Shared Donor', 'email' => 'shared@example.com']);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => 'shared']));

        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('Account Name', $results[0]['name']);
    }

    /**
     * The whole feature is a prefill convenience — selecting a result must never touch any
     * stored record. Covered here by proving the search endpoint itself performs no writes:
     * the matched guest donation's own row is byte-identical before and after the request.
     */
    public function test_searching_never_modifies_the_matched_records(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($eventId);
        $before = DB::table('donations_without_logins')->where('id', $donationId)->first();

        $this->actingAs($admin)->getJson(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => 'Ravi']))->assertOk();

        $after = DB::table('donations_without_logins')->where('id', $donationId)->first();
        $this->assertEquals($before, $after);
    }

    public function test_a_role_outside_the_pos_group_cannot_reach_the_search_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = $this->makeEvent();

        $response = $this->actingAs($staff)->get(route('admin.events.pos.search-devotee', ['event' => $eventId, 'q' => 'Ravi']));

        $response->assertRedirect();
    }
}
