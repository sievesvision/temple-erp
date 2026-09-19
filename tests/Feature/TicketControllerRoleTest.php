<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketControllerLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the "Ticket Controller" role — a flat grant (ticket_controllers table, one row per
 * user, view/entry/admin tiers via TicketControllerLevel) mirroring Event Coordinator's
 * shape but never scoped to anything, since Tickets is a standalone module. See
 * TicketController::manageTickets()'s redirect and TicketControllerAssignmentController.
 */
class TicketControllerRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
        Setting::set('linkly_secret_sandbox', 'test-secret');
    }

    private function grantTicketController(User $user, string $level): void
    {
        DB::table('ticket_controllers')->insert([
            'user_id' => $user->id,
            'level' => $level,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ticketControllerUser(string $level): User
    {
        $user = User::factory()->create([
            'role' => 'Ticket Controller',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);
        $this->grantTicketController($user, $level);
        return $user;
    }

    public function test_holding_the_grant_makes_ticket_controller_a_selectable_role(): void
    {
        $user = $this->ticketControllerUser('entry');

        $this->assertContains('Ticket Controller', $user->grantedRoles());
    }

    public function test_view_and_entry_level_are_redirected_straight_to_the_pos(): void
    {
        foreach (['view', 'entry'] as $level) {
            $user = $this->ticketControllerUser($level);

            $response = $this->actingAs($user)->get('/admin/manage-tickets');

            $response->assertRedirect(route('admin.tickets.pos'));
        }
    }

    public function test_admin_level_reaches_the_full_console_instead_of_being_redirected(): void
    {
        $user = $this->ticketControllerUser('admin');

        $response = $this->actingAs($user)->get('/admin/manage-tickets');

        $response->assertOk();
        $response->assertSee('Ticket Console', false);
    }

    // A view-level controller lands on the kiosk page itself (per the redirect above) but
    // can't actually complete a sale — same read-only-vs-entry split as EventCoordinatorLevel.
    public function test_view_level_can_open_the_kiosk_but_cannot_complete_a_sale(): void
    {
        $user = $this->ticketControllerUser('view');
        $ticket = Ticket::create(['name' => 'Adult Entry', 'price' => 10.00, 'status' => 'Active']);

        $this->actingAs($user)->get('/admin/tickets/pos')->assertOk();

        $response = $this->actingAs($user)->postJson('/admin/tickets/order', [
            'cart_json' => json_encode([['ticket_id' => $ticket->id, 'name' => $ticket->name, 'price' => 10.00, 'quantity' => 1]]),
            'payment_method' => 'Cash',
        ]);

        $response->assertStatus(403);
    }

    public function test_entry_level_can_complete_a_cash_sale(): void
    {
        $user = $this->ticketControllerUser('entry');
        $ticket = Ticket::create(['name' => 'Adult Entry', 'price' => 10.00, 'status' => 'Active']);

        $response = $this->actingAs($user)->postJson('/admin/tickets/order', [
            'cart_json' => json_encode([['ticket_id' => $ticket->id, 'name' => $ticket->name, 'price' => 10.00, 'quantity' => 1]]),
            'payment_method' => 'Cash',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
    }

    // canUseEftTerminal(): an entry-level Ticket Controller has no RolePermission grid entry
    // at all (mirroring Event Coordinator) — authorised purely via TicketControllerLevel.
    public function test_entry_level_can_poll_eft_charge_status_without_a_rolepermission_grant(): void
    {
        $user = $this->ticketControllerUser('entry');

        $sessionId = (string) \Illuminate\Support\Str::uuid();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => Http::response(null, 202),
        ]);

        $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}")->assertOk();
    }

    public function test_view_level_cannot_poll_eft_charge_status(): void
    {
        $user = $this->ticketControllerUser('view');

        $sessionId = (string) \Illuminate\Support\Str::uuid();

        $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}")->assertStatus(403);
    }

    public function test_admin_can_grant_ticket_controller_access_to_an_existing_user(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $target = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->post('/admin/ticket-controllers', [
            'user_id' => $target->id,
            'level' => 'entry',
        ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $this->assertDatabaseHas('ticket_controllers', ['user_id' => $target->id, 'level' => 'entry']);
    }

    // A non-system-admin Ticket Admin can grant entry/view but never 'admin' — same
    // protection EventCoordinatorController enforces for event-admin coordinators.
    public function test_a_ticket_admin_cannot_grant_admin_level(): void
    {
        $ticketAdmin = $this->ticketControllerUser('admin');
        $target = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($ticketAdmin)->post('/admin/ticket-controllers', [
            'user_id' => $target->id,
            'level' => 'admin',
        ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $this->assertDatabaseMissing('ticket_controllers', ['user_id' => $target->id]);
    }

    public function test_admin_can_update_and_remove_a_ticket_controllers_level(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $target = $this->ticketControllerUser('view');

        $this->actingAs($admin)->post("/admin/ticket-controllers/{$target->id}/level", ['level' => 'admin'])
            ->assertRedirect(route('admin.tickets.index'));
        $this->assertDatabaseHas('ticket_controllers', ['user_id' => $target->id, 'level' => 'admin']);

        $this->actingAs($admin)->delete("/admin/ticket-controllers/{$target->id}")
            ->assertRedirect(route('admin.tickets.index'));
        $this->assertDatabaseMissing('ticket_controllers', ['user_id' => $target->id]);
    }

    public function test_ticket_controller_level_ranking_helper(): void
    {
        $this->assertTrue(TicketControllerLevel::atLeast('admin', 'entry'));
        $this->assertTrue(TicketControllerLevel::atLeast('entry', 'entry'));
        $this->assertFalse(TicketControllerLevel::atLeast('view', 'entry'));
        $this->assertFalse(TicketControllerLevel::atLeast(null, 'view'));
        $this->assertTrue(TicketControllerLevel::isValid('admin'));
        $this->assertFalse(TicketControllerLevel::isValid('pos'));
    }
}
