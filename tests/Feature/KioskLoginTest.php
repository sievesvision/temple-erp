<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dedicated kiosk login landing page for POS-only accounts, and the session-timeout
 * redirect that sends an expired POS-page request back to it instead of the general
 * devotee/management login. Reuses the existing Event Coordinator 'pos'-level / Ticket
 * Controller 'view'/'entry'-level restrictions as-is — see AuthController::completeLogin()
 * and TicketController::manageTickets() — this only adds the kiosk-styled login view and the
 * route-based Authenticate::redirectUsing() callback (AppServiceProvider::boot()).
 */
class KioskLoginTest extends TestCase
{
    private function posLevelCoordinator(): array
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Kiosk Test Event', 'event_date' => now()->addMonth()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'pos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $eventId];
    }

    public function test_kiosk_login_page_renders_without_general_navigation_links(): void
    {
        $response = $this->get(route('kiosk.login'));

        $response->assertOk();
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertDontSee('Create Account');
        $response->assertDontSee('Forgot Password');
    }

    public function test_the_bare_kiosk_path_redirects_to_kiosk_login(): void
    {
        $response = $this->get('/kiosk');

        $response->assertRedirect(route('kiosk.login'));
    }

    public function test_logging_in_via_kiosk_page_still_lands_a_pos_level_coordinator_on_their_event(): void
    {
        [$user, $eventId] = $this->posLevelCoordinator();

        // Visiting the kiosk page first is incidental — completeLogin() decides the
        // destination purely from the account's role/level, not which login page posted.
        $this->get(route('kiosk.login'));

        $response = $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('admin.events.pos', $eventId));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_expired_session_on_the_event_pos_page_redirects_to_the_kiosk_login(): void
    {
        [, $eventId] = $this->posLevelCoordinator();

        $response = $this->get(route('admin.events.pos', $eventId));

        $response->assertRedirect(route('kiosk.login'));
    }

    public function test_an_expired_session_on_the_ticket_pos_page_redirects_to_the_kiosk_login(): void
    {
        $response = $this->get(route('admin.tickets.pos'));

        $response->assertRedirect(route('kiosk.login'));
    }

    public function test_an_expired_session_on_an_unrelated_page_still_redirects_to_the_general_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_logout_with_the_kiosk_flag_returns_to_the_kiosk_login(): void
    {
        $user = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($user)->get(route('logout', ['from' => 'kiosk']));

        $response->assertRedirect(route('kiosk.login'));
    }

    public function test_logout_without_the_kiosk_flag_returns_to_the_general_login(): void
    {
        $user = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($user)->get(route('logout'));

        $response->assertRedirect(route('login'));
    }
}
