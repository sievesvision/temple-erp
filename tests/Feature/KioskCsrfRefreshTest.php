<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The kiosk login page's own periodic keep-alive — see AuthController::
 * refreshKioskCsrfToken()'s docblock for the two ways a stale-loaded page's embedded token
 * otherwise goes bad well before anyone notices: ordinary idle time past SESSION_LIFETIME,
 * and Auth::login() rotating the session/token from a second tab of the same browser.
 */
class KioskCsrfRefreshTest extends TestCase
{
    public function test_it_returns_the_current_sessions_csrf_token(): void
    {
        $response = $this->get(route('kiosk.csrf-token'));

        $response->assertOk();
        $response->assertJsonStructure(['token']);
        $this->assertSame(csrf_token(), $response->json('token'));
    }

    public function test_it_reflects_a_token_rotated_by_a_login_in_another_tab(): void
    {
        // Simulates exactly the reported scenario: the kiosk page is already loaded (holding
        // whatever token this GET returns first), then something else in the same browser
        // session — a login from a second tab — rotates the token before the kiosk page
        // fetches again.
        $before = $this->get(route('kiosk.csrf-token'))->json('token');

        $user = \App\Models\User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $after = $this->get(route('kiosk.csrf-token'))->json('token');

        $this->assertNotSame($before, $after);
        $this->assertSame(csrf_token(), $after);
    }
}
