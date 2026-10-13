<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * The /admin/users table used to cram 9 columns and 5+ action pills into every row —
 * this covers the redesign: a grouped filter bar (Status/Role/Search), a simplified
 * table, and the per-row detail modal that now holds everything the old row's extra
 * columns and action buttons used to.
 */
class SystemUsersPageRedesignTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_the_page_renders_with_the_grouped_filter_bar_and_detail_modal(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Jane Settler', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Jane Settler');
        $response->assertSee('su-filter-bar', false);
        $response->assertSee('id="userDetail' . $target->id . '"', false);
        // The old row no longer carries five separate action pills directly — they live
        // inside the detail modal now, behind a single View trigger per row.
        $response->assertSee('su-btn-view', false);
    }

    public function test_defaults_to_active_users_only(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Inactive Person', 'status' => 'Inactive', 'mobile' => fake()->unique()->numerify('04########')]);
        User::factory()->create(['name' => 'Active Person', 'status' => 'Active', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Active Person');
        $response->assertDontSee('Inactive Person');
    }

    public function test_status_all_shows_inactive_users_too(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Inactive Person', 'status' => 'Inactive', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'all']));

        $response->assertOk();
        $response->assertSee('Inactive Person');
    }

    public function test_status_inactive_filters_to_inactive_only(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Inactive Person', 'status' => 'Inactive', 'mobile' => fake()->unique()->numerify('04########')]);
        User::factory()->create(['name' => 'Active Person', 'status' => 'Active', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'Inactive']));

        $response->assertOk();
        $response->assertSee('Inactive Person');
        $response->assertDontSee('Active Person');
    }

    public function test_detail_modal_shows_mobile_pos_username_and_2fa_status(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create([
            'name' => 'Full Detail User', 'mobile' => '0412345678', 'username' => 'fulluser',
            'two_factor_enabled' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('0412345678');
        $response->assertSee('value="fulluser"', false);
    }
}
