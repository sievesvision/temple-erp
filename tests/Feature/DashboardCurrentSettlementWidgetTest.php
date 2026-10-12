<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CashSettlementService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Dashboard pane's second card used to be "Recent Donations"/"Recent Orders" — replaced
 * with a "Current Settlement" summary (opening balance, cash received, expected till, last
 * settled date) and a direct link into the Settlements pane, for whoever can actually settle
 * cash. Anyone who can't (e.g. a view-level coordinator) still gets the original list, so the
 * dashboard never goes blank for them.
 */
class DashboardCurrentSettlementWidgetTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Dashboard Widget Test', 'slug' => 'dashboard-widget-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_event_dashboard_shows_current_settlement_instead_of_recent_donations(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        CashSettlementService::run('event', $eventId, $admin->id, 40.0, 40.0, null);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('Current Settlement');
        $response->assertSee('Opening Balance');
        $response->assertSee('Expected in Till');
        $response->assertSee('Go to Settlements');
        $response->assertDontSee('Recent Donations');
    }

    public function test_a_view_level_coordinator_still_sees_recent_donations_since_they_cannot_settle(): void
    {
        $eventId = $this->makeEvent();
        $viewer = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('event_coordinators')->insert([
            'user_id' => $viewer->id, 'event_id' => $eventId, 'level' => 'view',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($viewer)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('Recent Donations');
        $response->assertDontSee('Current Settlement');
    }

    public function test_the_ticket_dashboard_shows_current_settlement_instead_of_recent_orders(): void
    {
        $admin = $this->admin();
        CashSettlementService::run('tickets', null, $admin->id, 0.0, 0.0, null);

        $response = $this->actingAs($admin)->get(route('admin.tickets.index'));

        $response->assertOk();
        $response->assertSee('Current Settlement');
        $response->assertSee('Go to Settlements');
        $response->assertDontSee('Recent Orders');
    }
}
