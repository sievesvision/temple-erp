<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Bare /admin isn't a page of its own — a natural URL to type/bookmark, so it should just
 * land you somewhere sensible: the dashboard if signed in, the login page otherwise.
 */
class AdminRootRedirectTest extends TestCase
{
    public function test_a_logged_in_user_visiting_admin_is_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_guest_visiting_admin_is_redirected_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('login'));
    }

    /**
     * An authenticated account whose active_role isn't Admin (e.g. a stale session that
     * got re-initialised to the account's base role — see RoleSwitchMiddleware) used to
     * hit a bare abort(403) here, a dead end with no way back in — unlike every other
     * role-gated section of the app, which sends a wrong-role visitor somewhere useful.
     * It should redirect to login instead, matching that established pattern.
     */
    public function test_a_logged_in_user_with_the_wrong_active_role_is_redirected_to_login_not_403(): void
    {
        $user = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }
}
