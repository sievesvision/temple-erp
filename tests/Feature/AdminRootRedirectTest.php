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
}
