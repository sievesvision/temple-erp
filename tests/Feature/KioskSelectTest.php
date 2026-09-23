<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The "choose your counter" grid for an account holding more than one kiosk-only
 * destination at once (two pos-level events, or an event plus ticket access) — see
 * AuthController::possibleKioskPosDestinations()/completeLogin()/showKioskSelect(). Only
 * ever engages for a Event Coordinator/Ticket Controller account that is PURELY kiosk-only;
 * every other combination keeps today's pre-existing single-destination behavior.
 */
class KioskSelectTest extends TestCase
{
    private function makeEvent(string $name = 'Kiosk Event'): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => $name, 'event_date' => now()->addMonth()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function assignEvent(User $user, int $eventId, string $level): void
    {
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => $level,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function grantTicketAccess(User $user, string $level): void
    {
        DB::table('ticket_controllers')->insert([
            'user_id' => $user->id, 'level' => $level, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_single_pos_event_with_no_ticket_grant_still_lands_directly_on_the_event(): void
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = $this->makeEvent();
        $this->assignEvent($user, $eventId, 'pos');

        $response = $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('admin.events.pos', $eventId));
    }

    public function test_a_pos_event_plus_a_ticket_grant_logs_in_to_the_select_grid(): void
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = $this->makeEvent();
        $this->assignEvent($user, $eventId, 'pos');
        $this->grantTicketAccess($user, 'entry');

        $response = $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('kiosk.select'));

        $grid = $this->actingAs($user)->get(route('kiosk.select'));
        $grid->assertOk();
        $grid->assertSee('Kiosk Event');
        $grid->assertSee('Ticket Sales');
        $grid->assertSee('value="' . $eventId . '"', false);
    }

    /**
     * Reproduces a real production bug: an Event Coordinator (primary role) who also holds a
     * secondary Ticket Controller grant reaches the grid, but clicking "Ticket Sales" landed
     * on a 403 — active_role stayed 'Event Coordinator' (set at login) all the way through,
     * and TicketController::posShow() only recognises the ticket_controllers grant when
     * active_role is literally 'Ticket Controller'. Exercises the actual POST endpoint the
     * tile submits to, for both directions, to prove the fix holds.
     */
    public function test_choosing_the_ticket_tile_switches_active_role_and_actually_reaches_the_kiosk(): void
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = $this->makeEvent();
        $this->assignEvent($user, $eventId, 'pos');
        $this->grantTicketAccess($user, 'entry');

        $response = $this->actingAs($user)->post(route('kiosk.select.choose'), ['type' => 'tickets']);

        $response->assertRedirect(route('admin.tickets.pos'));
        $this->assertSame('Ticket Controller', session('active_role'));

        // Follow through to the real destination — must not 403.
        $kiosk = $this->get(route('admin.tickets.pos'));
        $kiosk->assertOk();
    }

    public function test_choosing_the_event_tile_switches_active_role_and_actually_reaches_the_kiosk(): void
    {
        $user = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = $this->makeEvent();
        $this->assignEvent($user, $eventId, 'pos');
        $this->grantTicketAccess($user, 'entry');

        $response = $this->actingAs($user)->post(route('kiosk.select.choose'), ['type' => 'event', 'event_id' => $eventId]);

        $response->assertRedirect(route('admin.events.pos', $eventId));
        $this->assertSame('Event Coordinator', session('active_role'));

        $kiosk = $this->get(route('admin.events.pos', $eventId));
        $kiosk->assertOk();
    }

    public function test_choosing_a_destination_not_actually_granted_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = $this->makeEvent();
        $this->assignEvent($user, $eventId, 'pos');
        $this->grantTicketAccess($user, 'entry');
        $otherEventId = $this->makeEvent('Not Assigned To Me');

        $response = $this->actingAs($user)->post(route('kiosk.select.choose'), ['type' => 'event', 'event_id' => $otherEventId]);

        $response->assertForbidden();
    }

    public function test_the_single_destination_auto_redirect_also_switches_active_role(): void
    {
        // Primary role Event Coordinator, but their ONLY reachable destination is a
        // secondary ticket grant (zero event assignments) — the count===1 fast path in
        // completeLogin() must also switch active_role, not just the grid's own POST handler.
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $this->grantTicketAccess($user, 'entry');

        $response = $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('admin.tickets.pos'));
        $this->assertSame('Ticket Controller', session('active_role'));

        $kiosk = $this->get(route('admin.tickets.pos'));
        $kiosk->assertOk();
    }

    public function test_two_pos_events_with_no_ticket_grant_also_lands_on_the_select_grid(): void
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventA = $this->makeEvent('Event A');
        $eventB = $this->makeEvent('Event B');
        $this->assignEvent($user, $eventA, 'pos');
        $this->assignEvent($user, $eventB, 'pos');

        $response = $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('kiosk.select'));

        $grid = $this->actingAs($user)->get(route('kiosk.select'));
        $grid->assertSee('Event A');
        $grid->assertSee('Event B');
    }

    public function test_a_mixed_pos_and_admin_level_coordinator_keeps_the_existing_earliest_event_behavior(): void
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $posEvent = $this->makeEvent('Pos Event');
        $adminEvent = $this->makeEvent('Admin Event');
        $this->assignEvent($user, $posEvent, 'pos');
        $this->assignEvent($user, $adminEvent, 'admin');
        // Also holds ticket access — must NOT trigger the grid, since not every event
        // assignment is 'pos' level (the guard in possibleKioskPosDestinations()).
        $this->grantTicketAccess($user, 'entry');

        $response = $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        // Both events were created the same instant; whichever sorts first by event_date is
        // the expected target — assert against the existing, unmodified completeLogin() logic
        // rather than hardcoding an event id.
        $earliest = DB::table('event_coordinators')
            ->join('events', 'event_coordinators.event_id', '=', 'events.event_id')
            ->where('event_coordinators.user_id', $user->id)
            ->orderBy('events.event_date')
            ->select('events.event_id', 'event_coordinators.level')
            ->first();

        $response->assertRedirect($earliest->level === 'pos'
            ? route('admin.events.pos', $earliest->event_id)
            : route('admin.events.console', $earliest->event_id));
    }

    public function test_an_admin_level_ticket_controller_is_unaffected_even_with_a_pos_event(): void
    {
        $user = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = $this->makeEvent();
        $this->assignEvent($user, $eventId, 'pos');
        $this->grantTicketAccess($user, 'admin');

        $response = $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('admin.tickets.index'));
    }

    public function test_visiting_the_select_grid_directly_with_only_one_destination_redirects_past_it(): void
    {
        $user = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        $this->grantTicketAccess($user, 'view');

        $response = $this->actingAs($user)->get(route('kiosk.select'));

        $response->assertRedirect(route('admin.tickets.pos'));
    }

    public function test_visiting_the_select_grid_directly_with_zero_destinations_redirects_to_the_role_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        $this->grantTicketAccess($user, 'admin');

        $response = $this->actingAs($user)->get(route('kiosk.select'));

        $response->assertRedirect(route('admin.tickets.index'));
    }

    public function test_an_expired_session_on_the_select_grid_redirects_to_the_kiosk_login(): void
    {
        $response = $this->get(route('kiosk.select'));

        $response->assertRedirect(route('kiosk.login'));
    }
}
