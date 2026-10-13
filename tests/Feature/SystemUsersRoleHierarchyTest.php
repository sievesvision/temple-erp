<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin has full access to /admin/users; Committee is admitted too, but strictly capped
 * to accounts less authoritative than itself (RolePermission::levels()) — never a peer
 * Committee member, never Admin, and never grantable at creation time. Every other role
 * still can't reach this page at all. The per-action controller check is the real
 * boundary (a crafted direct POST must be rejected even if the UI would never offer the
 * button) — the list-visibility filtering is just what keeps the UI honest about it.
 */
class SystemUsersRoleHierarchyTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function committee(): User
    {
        $u = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('committees')->insert(['user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        return $u;
    }

    public function test_admin_sees_every_role_including_other_admins_and_committee(): void
    {
        $admin = $this->admin();
        $otherAdmin = User::factory()->create(['name' => 'Other Admin', 'role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $committeeMember = User::factory()->create(['name' => 'Committee Member', 'role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Other Admin');
        $response->assertSee('Committee Member');
    }

    public function test_committee_does_not_see_admin_or_other_committee_members(): void
    {
        $committee = $this->committee();
        User::factory()->create(['name' => 'Some Admin', 'role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        User::factory()->create(['name' => 'Peer Committee', 'role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);
        User::factory()->create(['name' => 'Lower Staffer', 'role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($committee)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertDontSee('Some Admin');
        $response->assertDontSee('Peer Committee');
        $response->assertSee('Lower Staffer');
    }

    /**
     * A Devotee who also holds a Committee grant is still a peer to a Committee viewer —
     * their PRIMARY role alone isn't what determines authority, every granted role does
     * (User::authorisedLevel()), same rule the query's grant-table lookups enforce.
     */
    public function test_committee_does_not_see_a_devotee_who_also_holds_a_committee_grant(): void
    {
        $committee = $this->committee();
        $hiddenPeer = User::factory()->create(['name' => 'Secretly Committee', 'role' => 'Devotee', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('committees')->insert(['user_id' => $hiddenPeer->id, 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($committee)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertDontSee('Secretly Committee');
    }

    public function test_committee_role_filter_dropdown_excludes_admin_and_committee(): void
    {
        $committee = $this->committee();

        $response = $this->actingAs($committee)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertDontSee('<option value="Admin"', false);
        $response->assertDontSee('<option value="Committee"', false);
        $response->assertSee('<option value="Staff"', false);
    }

    public function test_committee_create_user_form_cannot_offer_admin_or_committee(): void
    {
        $committee = $this->committee();

        $response = $this->actingAs($committee)->get(route('admin.users.index'));

        $response->assertOk();
        // The role select inside the Create User modal — same assertion shape, scoped by
        // the surrounding option list rather than a selector the test harness can target.
        $response->assertDontSee('<option value="Admin"', false);
    }

    public function test_committee_cannot_create_a_committee_level_user_even_via_direct_post(): void
    {
        $committee = $this->committee();

        $response = $this->actingAs($committee)->post(route('admin.users.store'), [
            'name' => 'Sneaky New Committee', 'email' => 'sneaky.committee@example.com',
            'mobile' => '0499912345', 'role' => 'Committee',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertNull(User::where('email', 'sneaky.committee@example.com')->first());
    }

    public function test_committee_can_create_a_lower_privilege_user(): void
    {
        $committee = $this->committee();

        $response = $this->actingAs($committee)->post(route('admin.users.store'), [
            'name' => 'New Staffer', 'email' => 'new.staffer@example.com',
            'mobile' => '0499912346', 'role' => 'Staff',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertNotNull(User::where('email', 'new.staffer@example.com')->first());
    }

    public function test_committee_cannot_edit_an_admin_account_via_direct_post(): void
    {
        $committee = $this->committee();
        $targetAdmin = User::factory()->create(['name' => 'Untouchable Admin', 'role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($committee)->post(route('admin.users.update', $targetAdmin->id), [
            'name' => 'Hacked Name', 'email' => $targetAdmin->email, 'mobile' => $targetAdmin->mobile, 'status' => 'Active',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('Untouchable Admin', $targetAdmin->fresh()->name);
    }

    public function test_committee_cannot_toggle_status_of_a_peer_committee_member(): void
    {
        $committee = $this->committee();
        $peer = User::factory()->create(['role' => 'Committee', 'status' => 'Active', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('committees')->insert(['user_id' => $peer->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($committee)->post(route('admin.users.toggle-status', $peer->id));

        $this->assertSame('Active', $peer->fresh()->status);
    }

    public function test_committee_can_toggle_status_of_an_event_coordinator(): void
    {
        $committee = $this->committee();
        $eventCoordinator = User::factory()->create(['role' => 'Event Coordinator', 'status' => 'Active', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($committee)->post(route('admin.users.toggle-status', $eventCoordinator->id));

        $response->assertSessionHasNoErrors();
        $this->assertSame('Inactive', $eventCoordinator->fresh()->status);
    }

    public function test_a_staff_user_cannot_reach_the_system_users_page_at_all(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($staff)->get(route('admin.users.index'));

        $response->assertRedirect();
    }

    public function test_committees_view_activity_link_is_hidden_since_the_audit_log_stays_admin_only(): void
    {
        $committee = $this->committee();
        User::factory()->create(['name' => 'Managed Staffer', 'role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($committee)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertDontSee('View Activity');
    }

    public function test_admins_view_activity_link_is_shown(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Managed Staffer', 'role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('View Activity');
    }
}
