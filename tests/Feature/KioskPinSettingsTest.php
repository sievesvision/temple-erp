<?php

namespace Tests\Feature;

use App\Models\KioskPin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The self-service "set/change my kiosk PIN" screen — always requires the real password in
 * the same request (never just an active session), since a PIN alone must never be enough to
 * install a new one. PINs are per-destination, and uniqueness is only ever checked within the
 * SAME account's own other destinations — never against other users — since a PIN only means
 * anything paired with this account's own (admin-assigned) username. See
 * AuthController::updateKioskPinSettings().
 */
class KioskPinSettingsTest extends TestCase
{
    private function posLevelCoordinator(?string $username = 'sieves'): array
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########'), 'username' => $username]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Pin Settings Event', 'event_date' => now()->addMonth()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'pos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $eventId];
    }

    public function test_wrong_current_password_rejects_a_pin_change(): void
    {
        [$user, $eventId] = $this->posLevelCoordinator();

        $response = $this->actingAs($user)->post(route('kiosk.pin.update'), [
            'current_password' => 'not-the-password',
            'destination_type' => 'event', 'destination_id' => $eventId,
            'new_pin' => '111222', 'new_pin_confirmation' => '111222',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertSame(0, KioskPin::where('user_id', $user->id)->count());
    }

    public function test_correct_password_and_valid_new_pin_saves_it_hashed(): void
    {
        [$user, $eventId] = $this->posLevelCoordinator();

        $response = $this->actingAs($user)->post(route('kiosk.pin.update'), [
            'current_password' => 'password',
            'destination_type' => 'event', 'destination_id' => $eventId,
            'new_pin' => '111222', 'new_pin_confirmation' => '111222',
        ]);

        $response->assertSessionHasNoErrors();
        $pin = KioskPin::where('user_id', $user->id)->first();
        $this->assertNotNull($pin);
        $this->assertNotEquals('111222', $pin->pin);
        $this->assertTrue(Hash::check('111222', $pin->pin));
        $this->assertNotNull($pin->pin_set_at);
    }

    public function test_password_alone_with_no_new_pin_just_clears_a_lockout(): void
    {
        [$user, $eventId] = $this->posLevelCoordinator();
        $user->update(['kiosk_pin_failed_attempts' => 5, 'kiosk_pin_locked_at' => now()]);

        $response = $this->actingAs($user)->post(route('kiosk.pin.update'), [
            'current_password' => 'password',
            'destination_type' => 'event', 'destination_id' => $eventId,
        ]);

        $response->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertNull($user->kiosk_pin_locked_at);
        $this->assertSame(0, KioskPin::where('user_id', $user->id)->count());
    }

    public function test_a_pin_colliding_with_this_same_accounts_other_destination_is_rejected(): void
    {
        [$user, $eventId] = $this->posLevelCoordinator();
        DB::table('ticket_controllers')->insert(['user_id' => $user->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);
        KioskPin::create(['user_id' => $user->id, 'destination_type' => 'tickets', 'destination_id' => null, 'pin' => Hash::make('333444'), 'pin_set_at' => now()]);

        $response = $this->actingAs($user)->post(route('kiosk.pin.update'), [
            'current_password' => 'password',
            'destination_type' => 'event', 'destination_id' => $eventId,
            'new_pin' => '333444', 'new_pin_confirmation' => '333444',
        ]);

        $response->assertSessionHasErrors('new_pin');
        $this->assertSame(1, KioskPin::where('user_id', $user->id)->count());
    }

    /**
     * The core regression test for the fix: under the old single-PIN-per-account design, a
     * PIN identical to another user's PIN was rejected — and that rejection was itself an
     * oracle (it confirmed a working PIN existed for some other, unknown account, usable at
     * the login screen with no password). Now that login requires username+PIN together, a
     * PIN identical to a DIFFERENT user's PIN is harmless and must be explicitly allowed.
     */
    public function test_a_pin_identical_to_a_different_users_pin_is_allowed(): void
    {
        [$existing, $existingEventId] = $this->posLevelCoordinator('sieves');
        KioskPin::create(['user_id' => $existing->id, 'destination_type' => 'event', 'destination_id' => $existingEventId, 'pin' => Hash::make('333444'), 'pin_set_at' => now()]);

        [$user, $eventId] = $this->posLevelCoordinator('other');

        $response = $this->actingAs($user)->post(route('kiosk.pin.update'), [
            'current_password' => 'password',
            'destination_type' => 'event', 'destination_id' => $eventId,
            'new_pin' => '333444', 'new_pin_confirmation' => '333444',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, KioskPin::where('user_id', $user->id)->count());
    }

    public function test_a_non_kiosk_eligible_account_gets_403_on_both_routes(): void
    {
        $user = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $this->actingAs($user)->get(route('kiosk.pin.edit'))->assertForbidden();
        $this->actingAs($user)->post(route('kiosk.pin.update'), [
            'current_password' => 'password', 'destination_type' => 'tickets',
        ])->assertForbidden();
    }

    public function test_a_destination_not_belonging_to_the_account_gets_403(): void
    {
        [$user] = $this->posLevelCoordinator();

        $response = $this->actingAs($user)->post(route('kiosk.pin.update'), [
            'current_password' => 'password', 'destination_type' => 'tickets',
            'new_pin' => '111222', 'new_pin_confirmation' => '111222',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_reset_the_pin_lockout_for_one_user(): void
    {
        [$user] = $this->posLevelCoordinator();
        $user->update(['kiosk_pin_failed_attempts' => 5, 'kiosk_pin_locked_at' => now()]);
        [$otherUser] = $this->posLevelCoordinator('other');
        $otherUser->update(['kiosk_pin_failed_attempts' => 5, 'kiosk_pin_locked_at' => now()]);
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->post(route('admin.kiosk-pin.reset-lockout', $user->id));

        $response->assertRedirect();
        $user->refresh();
        $otherUser->refresh();
        $this->assertNull($user->kiosk_pin_locked_at);
        $this->assertNotNull($otherUser->kiosk_pin_locked_at);
    }

    public function test_a_non_admin_cannot_reset_the_pin_lockout(): void
    {
        [$user] = $this->posLevelCoordinator();
        $user->update(['kiosk_pin_failed_attempts' => 5, 'kiosk_pin_locked_at' => now()]);

        $this->actingAs($user)->post(route('admin.kiosk-pin.reset-lockout', $user->id));

        $user->refresh();
        $this->assertNotNull($user->kiosk_pin_locked_at);
    }

    public function test_admin_can_assign_a_kiosk_username(): void
    {
        [$user] = $this->posLevelCoordinator(null);
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->post(route('admin.users.set-username', $user->id), ['username' => 'newusr']);

        $response->assertRedirect();
        $this->assertSame('newusr', $user->refresh()->username);
    }

    public function test_username_must_be_between_4_and_6_characters(): void
    {
        [$user] = $this->posLevelCoordinator(null);
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->post(route('admin.users.set-username', $user->id), ['username' => 'ab']);

        $response->assertSessionHasErrors('username');
    }

    public function test_a_non_admin_cannot_assign_usernames(): void
    {
        [$user] = $this->posLevelCoordinator(null);

        $this->actingAs($user)->post(route('admin.users.set-username', $user->id), ['username' => 'sneaky']);

        $this->assertNull($user->refresh()->username);
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
        [$user, $eventId] = $this->posLevelCoordinator();

        // Simulate having just POSTed to this very page (the exact scenario that broke
        // url()->previous()) before loading it again.
        $response = $this->actingAs($user)->withHeaders(['referer' => route('kiosk.pin.edit')])->get(route('kiosk.pin.edit'));

        $response->assertOk();
        $response->assertSee(route('admin.events.pos', $eventId), false);
        $response->assertDontSee('href="' . route('kiosk.pin.edit') . '"', false);
    }

    public function test_settings_page_shows_each_destinations_own_bookmarkable_landing_page(): void
    {
        [$user, $eventId] = $this->posLevelCoordinator();
        DB::table('ticket_controllers')->insert(['user_id' => $user->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->get(route('kiosk.pin.edit'));

        $response->assertOk();
        $response->assertSee(route('kiosk.login.event', $eventId), false);
        $response->assertSee(route('kiosk.login.tickets'), false);
    }

    public function test_back_to_counter_links_to_the_select_grid_for_a_dual_access_account_with_no_known_referrer(): void
    {
        [$user] = $this->posLevelCoordinator();
        DB::table('ticket_controllers')->insert(['user_id' => $user->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->get(route('kiosk.pin.edit'));

        $response->assertOk();
        $response->assertSee(route('kiosk.select'), false);
    }

    /**
     * Regression: a dual-destination account linking to the settings page FROM one specific
     * counter (e.g. clicking "Manage kiosk PIN" while on Event 2's POS) used to always land
     * back on the generic "choose your counter" grid instead of that same counter — the grid
     * fallback only ever fired for the destination COUNT, never for where they actually came
     * from. The real referrer must now be remembered and survive this page's own self-POST
     * save (which is exactly what corrupts url()->previous() into a loop).
     */
    public function test_back_to_counter_returns_to_the_specific_counter_a_dual_access_account_came_from(): void
    {
        [$user, $eventId] = $this->posLevelCoordinator();
        DB::table('ticket_controllers')->insert(['user_id' => $user->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);

        // Arrived from that event's own POS page.
        $response = $this->actingAs($user)->withHeaders(['referer' => route('admin.events.pos', $eventId)])->get(route('kiosk.pin.edit'));
        $response->assertOk();
        $response->assertSee(route('admin.events.pos', $eventId), false);

        // Saving (a self-POST back to this page) must not lose that memory...
        $this->post(route('kiosk.pin.update'), [
            'current_password' => 'password', 'destination_type' => 'event', 'destination_id' => $eventId,
        ]);

        // ...so reloading the settings page afterwards still offers the same specific counter,
        // not the grid.
        $response = $this->get(route('kiosk.pin.edit'));
        $response->assertOk();
        $response->assertSee(route('admin.events.pos', $eventId), false);
    }
}
