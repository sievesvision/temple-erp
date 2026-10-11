<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 2A: the unified `/admin/users` create/edit/activate-deactivate/event-and-ticket-
 * access surface, added alongside (not replacing) the existing per-role Add pages — see
 * SystemUserController::store()'s own docblock for why Event Coordinator/Ticket
 * Controller are deliberately excluded from the role dropdown there.
 */
class SystemUserManagementTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_admin_can_create_a_brand_new_user_of_a_grantable_role(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Committee Member', 'email' => 'new.committee@example.com',
            'mobile' => '0499999001', 'role' => 'Committee',
        ]);

        $response->assertSessionHasNoErrors();
        $newUser = User::where('email', 'new.committee@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertSame('Committee', $newUser->role);
        $this->assertSame('Active', $newUser->status);
        $this->assertSame(1, DB::table('committees')->where('user_id', $newUser->id)->count());
    }

    public function test_priest_creation_generates_a_sequential_priest_id(): void
    {
        $admin = $this->admin();
        DB::table('priests')->insert([
            'user_id' => User::factory()->create(['mobile' => fake()->unique()->numerify('04########')])->id,
            'priest_id' => 'PRIEST0007', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Priest', 'email' => 'new.priest@example.com',
            'mobile' => '0499999002', 'role' => 'Priest',
        ]);

        $response->assertSessionHasNoErrors();
        $newUser = User::where('email', 'new.priest@example.com')->first();
        $this->assertSame('PRIEST0008', DB::table('priests')->where('user_id', $newUser->id)->value('priest_id'));
    }

    public function test_creating_with_an_existing_email_grants_the_role_instead_of_duplicating_the_account(): void
    {
        $admin = $this->admin();
        $existing = User::factory()->create(['role' => 'Devotee', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Ignored', 'email' => $existing->email, 'mobile' => '0499999003', 'role' => 'Staff',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('email', $existing->email)->count());
        $this->assertSame('Devotee', $existing->fresh()->role);
        $this->assertSame(1, DB::table('staff')->where('user_id', $existing->id)->count());
    }

    public function test_granting_a_role_the_user_already_holds_is_rejected(): void
    {
        $admin = $this->admin();
        $existing = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('committees')->insert(['user_id' => $existing->id, 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Ignored', 'email' => $existing->email, 'mobile' => '0499999004', 'role' => 'Committee',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, DB::table('committees')->where('user_id', $existing->id)->count());
    }

    public function test_event_coordinator_and_ticket_controller_are_not_creatable_roles(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'X', 'email' => 'x@example.com', 'mobile' => '0499999005', 'role' => 'Event Coordinator',
        ]);

        $response->assertSessionHasErrors('role');
    }

    public function test_a_non_admin_cannot_create_users(): void
    {
        $nonAdmin = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);

        $this->actingAs($nonAdmin)->post(route('admin.users.store'), [
            'name' => 'X', 'email' => 'x2@example.com', 'mobile' => '0499999006', 'role' => 'Staff',
        ]);

        $this->assertNull(User::where('email', 'x2@example.com')->first());
    }

    public function test_admin_can_edit_a_users_details(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Old Name', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->post(route('admin.users.update', $target->id), [
            'name' => 'New Name', 'email' => $target->email, 'mobile' => $target->mobile, 'status' => 'Active',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('New Name', $target->fresh()->name);
    }

    public function test_deactivating_a_user_blocks_login(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['status' => 'Active', 'mobile' => fake()->unique()->numerify('04########'), 'password' => Hash::make('password')]);
        $this->assertSame('Active', $target->status);

        $this->actingAs($admin)->post(route('admin.users.toggle-status', $target->id));
        $this->assertSame('Inactive', $target->fresh()->status);

        auth()->logout();
        $this->post(route('login.post'), ['email' => $target->email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_toggling_status_again_reactivates(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['status' => 'Inactive', 'mobile' => fake()->unique()->numerify('04########')]);

        $this->actingAs($admin)->post(route('admin.users.toggle-status', $target->id));

        $this->assertSame('Active', $target->fresh()->status);
    }

    public function test_a_non_admin_cannot_edit_or_toggle_status(): void
    {
        $nonAdmin = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);
        $target = User::factory()->create(['name' => 'Untouched', 'mobile' => fake()->unique()->numerify('04########')]);

        $this->actingAs($nonAdmin)->post(route('admin.users.update', $target->id), [
            'name' => 'Hacked', 'email' => $target->email, 'mobile' => $target->mobile, 'status' => 'Active',
        ]);
        $this->actingAs($nonAdmin)->post(route('admin.users.toggle-status', $target->id));

        $this->assertSame('Untouched', $target->fresh()->name);
        $this->assertSame('Active', $target->fresh()->status);
    }

    public function test_event_access_endpoint_returns_every_event_this_user_coordinates(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['mobile' => fake()->unique()->numerify('04########')]);
        $eventA = DB::table('events')->insertGetId(['event_name' => 'Event A', 'event_date' => now()->addDays(10)->toDateString(), 'slug' => 'event-a-' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $eventB = DB::table('events')->insertGetId(['event_name' => 'Event B', 'event_date' => now()->addDays(20)->toDateString(), 'slug' => 'event-b-' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('event_coordinators')->insert([
            ['user_id' => $target->id, 'event_id' => $eventA, 'level' => 'pos', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $target->id, 'event_id' => $eventB, 'level' => 'admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('ticket_controllers')->insert(['user_id' => $target->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($admin)->getJson(route('admin.users.event-access', $target->id));

        $response->assertOk();
        $response->assertJsonCount(2, 'events');
        $response->assertJson(['ticket_level' => 'entry']);
    }

    public function test_a_non_admin_cannot_read_event_access(): void
    {
        $nonAdmin = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);
        $target = User::factory()->create(['mobile' => fake()->unique()->numerify('04########')]);

        // Blocked by the route group's own role.admin middleware before the controller's
        // internal check ever runs — a redirect, not a raw 403, same as every other
        // admin.users.* route in this file.
        $this->actingAs($nonAdmin)->get(route('admin.users.event-access', $target->id))->assertRedirect();
    }

    /**
     * The user-centric "Event & Ticket Access" action reuses the existing, unchanged
     * EventCoordinatorController::store() route — confirming it can genuinely be driven
     * with an arbitrary event_id + user_id from this page, not just from the event
     * console itself.
     */
    public function test_granting_event_access_from_the_users_page_uses_the_existing_coordinator_route(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId(['event_name' => 'Some Event', 'event_date' => now()->addMonth()->toDateString(), 'slug' => 'some-event-' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.store', $eventId), [
            'user_id' => $target->id, 'level' => 'pos',
        ]);

        $response->assertRedirect();
        $this->assertSame('pos', DB::table('event_coordinators')->where('event_id', $eventId)->where('user_id', $target->id)->value('level'));
    }
}
