<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dedicated POS login landing page for POS-only accounts, and the session-timeout
 * redirect that sends an expired POS-page request back to it instead of the general
 * devotee/management login. Reuses the existing Event Coordinator 'pos'-level / Ticket
 * Controller 'view'/'entry'-level restrictions as-is — see AuthController::completeLogin()
 * and TicketController::manageTickets() — this only adds the POS-styled login view and the
 * route-based Authenticate::redirectUsing() callback (AppServiceProvider::boot()).
 */
class PosLoginTest extends TestCase
{
    private function posLevelCoordinator(): array
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'POS Test Event', 'event_date' => now()->addMonth()->toDateString(),
            'slug' => 'pos-test-event',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'pos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $eventId];
    }

    public function test_pos_login_page_renders_without_general_navigation_links(): void
    {
        $response = $this->get(route('pos.login'));

        $response->assertOk();
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertDontSee('Create Account');
        $response->assertDontSee('Forgot Password');
    }

    public function test_the_bare_pos_path_redirects_to_pos_login(): void
    {
        $response = $this->get('/pos');

        $response->assertRedirect(route('pos.login'));
    }

    public function test_logging_in_via_pos_page_still_lands_a_pos_level_coordinator_on_their_event(): void
    {
        [$user, $eventId] = $this->posLevelCoordinator();

        // Visiting the POS page first is incidental — completeLogin() decides the
        // destination purely from the account's role/level, not which login page posted.
        $this->get(route('pos.login'));

        $response = $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('admin.events.pos', $eventId));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_expired_session_on_the_event_pos_page_redirects_to_the_pos_login(): void
    {
        [, $eventId] = $this->posLevelCoordinator();

        $response = $this->get(route('admin.events.pos', $eventId));

        $response->assertRedirect(route('pos.login'));
    }

    public function test_an_expired_session_on_the_ticket_pos_page_redirects_to_the_pos_login(): void
    {
        $response = $this->get(route('admin.tickets.pos'));

        $response->assertRedirect(route('pos.login'));
    }

    public function test_an_expired_session_on_an_unrelated_page_still_redirects_to_the_general_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_logout_with_the_pos_flag_returns_to_the_pos_login(): void
    {
        $user = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($user)->get(route('logout', ['from' => 'pos']));

        $response->assertRedirect(route('pos.login'));
    }

    public function test_logout_without_the_pos_flag_returns_to_the_general_login(): void
    {
        $user = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($user)->get(route('logout'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_generic_pos_login_page_shows_no_destination_specific_wording(): void
    {
        $response = $this->get(route('pos.login'));

        $response->assertOk();
        $response->assertSee('Counter Sign In');
        $response->assertDontSee('Event Donation POS');
        $response->assertDontSee('Ticket Sales POS');
    }

    public function test_an_events_own_pos_login_page_shows_its_own_name(): void
    {
        $this->posLevelCoordinator();

        $response = $this->get(route('pos.login.event', 'pos-test-event'));

        $response->assertOk();
        $response->assertSee('POS Test Event — Event Donation POS');
    }

    public function test_a_nonexistent_events_pos_login_page_falls_back_to_the_generic_wording(): void
    {
        $response = $this->get(route('pos.login.event', 'no-such-event'));

        $response->assertOk();
        $response->assertSee('Counter Sign In');
        $response->assertDontSee('Event Donation POS');
    }

    public function test_the_tickets_pos_login_page_shows_its_own_name(): void
    {
        $response = $this->get(route('pos.login.tickets'));

        $response->assertOk();
        $response->assertSee('Ticket Sales POS');
    }

    public function test_logging_in_from_an_events_own_landing_page_still_lands_on_that_event_regardless_of_page(): void
    {
        [$user, $eventId] = $this->posLevelCoordinator();

        // The destination-specific page is cosmetic only — the account's own destination
        // still decides where login lands, same as the generic page.
        $this->get(route('pos.login.event', 'pos-test-event'));

        $response = $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('admin.events.pos', $eventId));
    }
}
