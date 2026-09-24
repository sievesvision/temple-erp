<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The self-service "set/change my kiosk PIN" screen — always requires the real password in
 * the same request (never just an active session), since a PIN alone must never be enough
 * to install a new one. See AuthController::updateKioskPinSettings().
 */
class KioskPinSettingsTest extends TestCase
{
    private function posLevelCoordinator(): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Pin Settings Event', 'event_date' => now()->addMonth()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'pos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    public function test_wrong_current_password_rejects_a_pin_change(): void
    {
        $user = $this->posLevelCoordinator();

        $response = $this->actingAs($user)->post(route('kiosk.pin.update'), [
            'current_password' => 'not-the-password',
            'new_pin' => '111222', 'new_pin_confirmation' => '111222',
        ]);

        $response->assertSessionHasErrors('current_password');
        $user->refresh();
        $this->assertNull($user->pos_pin);
    }

    public function test_correct_password_and_valid_new_pin_saves_it_hashed(): void
    {
        $user = $this->posLevelCoordinator();

        $response = $this->actingAs($user)->post(route('kiosk.pin.update'), [
            'current_password' => 'password',
            'new_pin' => '111222', 'new_pin_confirmation' => '111222',
        ]);

        $response->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertNotNull($user->pos_pin);
        $this->assertNotEquals('111222', $user->pos_pin);
        $this->assertTrue(Hash::check('111222', $user->pos_pin));
        $this->assertNotNull($user->pos_pin_set_at);
    }

    public function test_password_alone_with_no_new_pin_just_clears_a_lockout(): void
    {
        $user = $this->posLevelCoordinator();
        Setting::set('kiosk_pin_failed_attempts', 5);
        Setting::set('kiosk_pin_locked_at', now()->toDateTimeString());

        $response = $this->actingAs($user)->post(route('kiosk.pin.update'), ['current_password' => 'password']);

        $response->assertSessionHasNoErrors();
        $this->assertNull(Setting::get('kiosk_pin_locked_at'));
        $user->refresh();
        $this->assertNull($user->pos_pin);
    }

    public function test_a_pin_colliding_with_another_users_pin_is_rejected(): void
    {
        $existing = $this->posLevelCoordinator();
        $existing->update(['pos_pin' => Hash::make('333444'), 'pos_pin_set_at' => now()]);

        $user = $this->posLevelCoordinator();

        $response = $this->actingAs($user)->post(route('kiosk.pin.update'), [
            'current_password' => 'password',
            'new_pin' => '333444', 'new_pin_confirmation' => '333444',
        ]);

        $response->assertSessionHasErrors('new_pin');
        $user->refresh();
        $this->assertNull($user->pos_pin);
    }

    public function test_a_non_kiosk_eligible_account_gets_403_on_both_routes(): void
    {
        $user = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $this->actingAs($user)->get(route('kiosk.pin.edit'))->assertForbidden();
        $this->actingAs($user)->post(route('kiosk.pin.update'), ['current_password' => 'password'])->assertForbidden();
    }

    public function test_admin_can_reset_the_pin_lockout(): void
    {
        Setting::set('kiosk_pin_failed_attempts', 5);
        Setting::set('kiosk_pin_locked_at', now()->toDateTimeString());
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->post(route('admin.kiosk-pin.reset-lockout'));

        $response->assertRedirect();
        $this->assertNull(Setting::get('kiosk_pin_locked_at'));
    }

    public function test_a_non_admin_cannot_reset_the_pin_lockout(): void
    {
        Setting::set('kiosk_pin_locked_at', now()->toDateTimeString());
        $user = $this->posLevelCoordinator();

        $this->actingAs($user)->post(route('admin.kiosk-pin.reset-lockout'));

        $this->assertNotNull(Setting::get('kiosk_pin_locked_at'));
    }

    /**
     * Regression: "Back to counter" used url()->previous(), which this page's own form
     * (posting back to itself on save) silently corrupts — the session's tracked previous
     * URL becomes this same settings page after the very first save, turning the link into a
     * loop instead of a way out. The link must be derived from the account's own
     * destination(s), not the request history.
     */
    public function test_back_to_counter_links_straight_to_the_single_event_regardless_of_referrer(): void
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Back Link Event', 'event_date' => now()->addMonth()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'pos',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Simulate having just POSTed to this very page (the exact scenario that broke
        // url()->previous()) before loading it again.
        $response = $this->actingAs($user)->withHeaders(['referer' => route('kiosk.pin.edit')])->get(route('kiosk.pin.edit'));

        $response->assertOk();
        $response->assertSee(route('admin.events.pos', $eventId), false);
        $response->assertDontSee('href="' . route('kiosk.pin.edit') . '"', false);
    }

    public function test_back_to_counter_links_to_the_select_grid_for_a_dual_access_account(): void
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Back Link Event 2', 'event_date' => now()->addMonth()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'pos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ticket_controllers')->insert(['user_id' => $user->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->get(route('kiosk.pin.edit'));

        $response->assertOk();
        $response->assertSee(route('kiosk.select'), false);
    }
}
