<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The POS page's Settle Till modal is already-rendered page markup — opening it later (no
 * navigation in between) would otherwise keep showing whatever "Current Period Transactions"
 * snapshot was on screen at the page's own last load. The modal's own JS refetches
 * admin.cash-settlement.pane on every open instead — this covers that endpoint directly.
 */
class CashSettlementPaneLiveRefreshTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Pane Refresh Test', 'slug' => 'pane-refresh-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_pane_endpoint_reflects_a_donation_recorded_after_the_page_would_have_loaded(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        // Simulates: the POS page loaded with nothing in the till yet, THEN a donation came
        // in from elsewhere (another station, the public donation page) before the operator
        // ever opened the Settle Till modal.
        DB::table('donations_without_logins')->insert([
            'event_id' => $eventId, 'donor_name' => 'Just Arrived Donor', 'amount' => 65,
            'payment_method' => 'Cash', 'payment_status' => 'Paid', 'purpose' => 'General',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'event', 'event_id' => $eventId, 'context' => 'pos',
        ]));

        $response->assertOk();
        $response->assertSee('Just Arrived Donor');
        // The returned fragment is the pane's own markup only — no outer modal wrapper,
        // since the JS swaps it straight into the already-open modal's body.
        $response->assertDontSee('id="settleTillModal"', false);
    }

    public function test_the_pane_endpoint_requires_cash_settlement_permission(): void
    {
        $viewer = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = $this->makeEvent();

        $response = $this->actingAs($viewer)->get(route('admin.cash-settlement.pane', [
            'scope' => 'event', 'event_id' => $eventId, 'context' => 'pos',
        ]));

        $response->assertStatus(403);
    }

    public function test_the_pos_page_includes_the_live_refresh_script(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertSee('settleTillModalBody', false);
        $response->assertSee("show.bs.modal", false);
    }

    public function test_the_ticket_pos_page_also_includes_the_live_refresh_script(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('settleTillModalBody', false);
        $response->assertSee("show.bs.modal", false);
    }

    public function test_the_tickets_pane_endpoint_works_too(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'tickets', 'context' => 'pos',
        ]));

        $response->assertOk();
    }
}
