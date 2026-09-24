<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PIN login — a faster alternative to email+password for kiosk-only accounts. See
 * AuthController::attemptKioskPinLogin()'s own docblock for why the failed-attempt lockout
 * is global (one pair of Setting keys), not per-account or per-device: a wrong PIN can't be
 * attributed to a specific account until it matches one.
 */
class KioskPinLoginTest extends TestCase
{
    private function posLevelCoordinatorWithPin(string $pin = '123456'): array
    {
        $user = User::factory()->create([
            'role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########'),
            'pos_pin' => Hash::make($pin), 'pos_pin_set_at' => now(),
        ]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Pin Test Event', 'event_date' => now()->addMonth()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'pos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $eventId];
    }

    public function test_correct_pin_logs_in_and_lands_on_the_single_event(): void
    {
        [$user, $eventId] = $this->posLevelCoordinatorWithPin();

        $response = $this->post(route('kiosk.pin-login'), ['pin' => '123456']);

        $response->assertRedirect(route('admin.events.pos', $eventId));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Event Coordinator', session('active_role'));
    }

    public function test_correct_pin_for_a_dual_access_account_lands_on_the_select_grid(): void
    {
        [$user] = $this->posLevelCoordinatorWithPin();
        DB::table('ticket_controllers')->insert(['user_id' => $user->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->post(route('kiosk.pin-login'), ['pin' => '123456']);

        $response->assertRedirect(route('kiosk.select'));
    }

    public function test_wrong_pin_fails_without_revealing_anything(): void
    {
        $this->posLevelCoordinatorWithPin('123456');

        $response = $this->post(route('kiosk.pin-login'), ['pin' => '000000']);

        $response->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_a_pin_belonging_to_a_user_whose_kiosk_access_was_revoked_no_longer_works(): void
    {
        $user = User::factory()->create([
            'role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########'),
            'pos_pin' => Hash::make('654321'), 'pos_pin_set_at' => now(),
        ]);
        // No event_coordinators row at all — access was removed after the PIN was set.

        $response = $this->post(route('kiosk.pin-login'), ['pin' => '654321']);

        $response->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_five_wrong_attempts_lock_out_pin_login_entirely(): void
    {
        $this->posLevelCoordinatorWithPin('123456');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('kiosk.pin-login'), ['pin' => '999999']);
        }

        $this->assertNotNull(Setting::get('kiosk_pin_locked_at'));

        // Even the CORRECT PIN is now rejected — the lock is on the mechanism, not tied to
        // whether this particular guess was right.
        $response = $this->post(route('kiosk.pin-login'), ['pin' => '123456']);
        $response->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_a_successful_email_login_clears_the_pin_lockout(): void
    {
        Setting::set('kiosk_pin_failed_attempts', 5);
        Setting::set('kiosk_pin_locked_at', now()->toDateTimeString());

        $user = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $this->assertNull(Setting::get('kiosk_pin_locked_at'));
        $this->assertSame(0, (int) Setting::get('kiosk_pin_failed_attempts'));
    }

    public function test_the_kiosk_login_page_shows_the_locked_notice_when_locked(): void
    {
        Setting::set('kiosk_pin_locked_at', now()->toDateTimeString());

        $response = $this->get(route('kiosk.login'));

        $response->assertOk();
        $response->assertSee('PIN sign-in is temporarily disabled');
        $response->assertDontSee('id="kioskPinPanel"', false);
    }
}
