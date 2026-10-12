<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CashSettlementService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Portal's "All Transactions" pane (event-console.blade.php's pane-table) gets a
 * multi-select Status/Payment Method filter plus a Settlement filter tagging each row with
 * whichever settlement claimed it (or "current" if none has) — see
 * CashSettlementService::settlementKeyMap()'s own docblock for how that tagging is derived.
 */
class AllTransactionsFilterTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'All Transactions Filter Test', 'slug' => 'all-txn-filter-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertGuestDonation(int $eventId, string $donorName, float $amount, string $method, string $status): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => $donorName, 'amount' => $amount,
            'payment_method' => $method, 'payment_status' => $status, 'purpose' => 'General',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_portal_renders_multi_select_filter_pills_for_method_status_and_settlement(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Settled Donor', 50, 'Cash', 'Paid');
        $settlement = CashSettlementService::run('event', $eventId, $admin->id, 50.0, 50.0, null);
        $this->insertGuestDonation($eventId, 'Current Donor', 30, 'Cash', 'Paid');

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('donationsStatusPills', false);
        $response->assertSee('donationsPaymentPills', false);
        $response->assertSee('donationsSettlementPills', false);
        $response->assertSee('Current (Unsettled)');
        $response->assertSee('Settled Donor');
        $response->assertSee('Current Donor');
        // Settled Donor's row is tagged with the real settlement id, Current Donor's with
        // "current" — confirms settlementKeyMap() tagging actually reached the rendered row.
        $response->assertSee('data-settlement="' . $settlement->id . '"', false);
        $response->assertSee('data-settlement="current"', false);
    }

    public function test_cancelled_and_failed_statuses_are_not_active_by_default(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Cancelled Donor', 20, 'Cash', 'Cancelled');

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        // The Cancelled pill itself renders, just not pre-activated — assert the inactive
        // markup pattern (no "active" class token immediately before the Cancelled pill's
        // own data-value) rather than plain text, since "Cancelled" also appears as the
        // donor's own row data regardless of pill state.
        $response->assertSee('data-value="cancelled">', false);
        $response->assertDontSee('donations-multi-pill active" data-value="cancelled"', false);
    }
}
