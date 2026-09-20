<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Who can reach and use the standalone EFT Terminal Settings page (see
 * App\Services\EftTerminalAccess) — deliberately broader than the main Settings page's own
 * 'settings' RolePermission: an event-admin Event Coordinator or admin-level Ticket
 * Controller already has full pairing/refund/logon rights over any terminal from their own
 * console, so they can view the registry and add a new terminal too. Only "set default" and
 * "remove a terminal" stay System-Admin-only, since those affect every other console's
 * fallback resolution.
 */
class EftTerminalRegistryAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();
    }

    // 'Event Coordinator' is in the route's own role list (broad enough for entry-level
    // coordinators to reach their own console), but only 'admin' level should ever pass
    // EftTerminalAccess::canManageRegistry() — this is the controller doing the real
    // narrowing, not the route.
    private function entryLevelCoordinator(): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Access Test Event (entry)', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'entry',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    private function eventAdminCoordinator(): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Access Test Event', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    private function ticketAdminController(): User
    {
        $user = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('ticket_controllers')->insert([
            'user_id' => $user->id, 'level' => 'admin', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    public function test_event_admin_coordinator_can_view_and_add_a_terminal(): void
    {
        $user = $this->eventAdminCoordinator();

        $this->actingAs($user)->get('/admin/eft-terminals')->assertOk();

        $response = $this->actingAs($user)->post('/admin/eft-terminals', [
            'key' => 'event-admin-added', 'label' => 'Added by Event Admin',
        ]);

        $response->assertRedirect(route('admin.eft-terminals.index'));
        $this->assertDatabaseHas('eft_terminals', ['key' => 'event-admin-added']);
    }

    public function test_ticket_admin_controller_can_view_and_add_a_terminal(): void
    {
        $user = $this->ticketAdminController();

        $this->actingAs($user)->get('/admin/eft-terminals')->assertOk();

        $response = $this->actingAs($user)->post('/admin/eft-terminals', [
            'key' => 'ticket-admin-added', 'label' => 'Added by Ticket Admin',
        ]);

        $response->assertRedirect(route('admin.eft-terminals.index'));
        $this->assertDatabaseHas('eft_terminals', ['key' => 'ticket-admin-added']);
    }

    public function test_entry_level_coordinator_cannot_view_or_add_a_terminal(): void
    {
        $user = $this->entryLevelCoordinator();

        $this->actingAs($user)->get('/admin/eft-terminals')->assertStatus(403);

        $this->actingAs($user)->post('/admin/eft-terminals', [
            'key' => 'should-not-exist', 'label' => 'Should Not Exist',
        ]);
        $this->assertDatabaseMissing('eft_terminals', ['key' => 'should-not-exist']);
    }

    // Set-default and remove stay System-Admin-only even for an event-admin coordinator who
    // can otherwise fully manage the registry (view/add/pair).
    public function test_event_admin_coordinator_cannot_set_default_or_remove_a_terminal(): void
    {
        $user = $this->eventAdminCoordinator();
        $terminal = EftTerminal::factory()->create(['key' => 'protected-terminal']);

        $this->actingAs($user)->post("/admin/eft-terminals/{$terminal->id}/default")->assertRedirect();
        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id, 'is_default' => false]);

        $this->actingAs($user)->delete("/admin/eft-terminals/{$terminal->id}");
        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id]);
    }

    // return_context tells EftTerminalController where the "Add Terminal" form was actually
    // submitted from (Ticket Console / a specific event's Console / the standalone page) so
    // the browser lands back there instead of always ending up on the standalone page — see
    // EftTerminalController::redirectAfterAction(). Which *pane* re-opens on that console is
    // then handled entirely client-side (the "consoleActivePane" localStorage convention).
    public function test_return_context_ticket_console_redirects_to_the_ticket_console(): void
    {
        $user = $this->ticketAdminController();

        $response = $this->actingAs($user)->post('/admin/eft-terminals', [
            'key' => 'from-ticket-console', 'label' => 'From Ticket Console',
            'return_context' => 'ticket-console',
        ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $this->assertDatabaseHas('eft_terminals', ['key' => 'from-ticket-console']);
    }

    public function test_return_context_event_console_redirects_to_that_events_console(): void
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Return Context Event', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/admin/eft-terminals', [
            'key' => 'from-event-console', 'label' => 'From Event Console',
            'return_context' => "event-console:{$eventId}",
        ]);

        $response->assertRedirect(route('admin.events.console', $eventId));
        $this->assertDatabaseHas('eft_terminals', ['key' => 'from-event-console']);
    }

    public function test_admin_can_set_default_and_remove_a_non_default_terminal(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $terminal = EftTerminal::factory()->create(['key' => 'removable-terminal']);

        $this->actingAs($admin)->post("/admin/eft-terminals/{$terminal->id}/default")
            ->assertRedirect();
        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id, 'is_default' => true]);

        $other = EftTerminal::factory()->create(['key' => 'other-terminal']);
        $this->actingAs($admin)->delete("/admin/eft-terminals/{$other->id}")->assertRedirect();
        $this->assertDatabaseMissing('eft_terminals', ['id' => $other->id]);
    }
}
