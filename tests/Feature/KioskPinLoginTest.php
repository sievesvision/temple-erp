<?php

namespace Tests\Feature;

use App\Models\KioskPin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PIN login — a faster alternative to email+password for kiosk-only accounts. The account is
 * identified by a short username FIRST, so the PIN only ever needs to be checked against that
 * one account's own KioskPin rows (one per destination) — see AuthController::
 * attemptKioskPinLogin()'s own docblock for why this makes the destination known immediately
 * and scopes the failed-attempt lockout to that one account.
 */
class KioskPinLoginTest extends TestCase
{
    private function posLevelCoordinatorWithPin(string $username = 'sieves', string $pin = '123456'): array
    {
        $user = User::factory()->create([
            'role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########'),
            'username' => $username,
        ]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Pin Test Event', 'event_date' => now()->addMonth()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'pos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        KioskPin::create([
            'user_id' => $user->id, 'destination_type' => 'event', 'destination_id' => $eventId,
            'pin' => Hash::make($pin), 'pin_set_at' => now(),
        ]);
        return [$user, $eventId];
    }

    public function test_correct_username_and_pin_logs_in_and_lands_on_that_exact_event(): void
    {
        [$user, $eventId] = $this->posLevelCoordinatorWithPin();

        $response = $this->post(route('kiosk.pin-login'), ['username' => 'sieves', 'pin' => '123456']);

        $response->assertRedirect(route('admin.events.pos', $eventId));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Event Coordinator', session('active_role'));
    }

    public function test_a_dual_destination_account_is_sent_straight_to_the_pin_that_matched_not_a_grid(): void
    {
        [$user, $eventId] = $this->posLevelCoordinatorWithPin('sieves', '123456');
        DB::table('ticket_controllers')->insert(['user_id' => $user->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);
        KioskPin::create([
            'user_id' => $user->id, 'destination_type' => 'tickets', 'destination_id' => null,
            'pin' => Hash::make('456789'), 'pin_set_at' => now(),
        ]);

        // The event PIN opens the event directly...
        $response = $this->post(route('kiosk.pin-login'), ['username' => 'sieves', 'pin' => '123456']);
        $response->assertRedirect(route('admin.events.pos', $eventId));

        // ...and the ticket PIN, for the SAME account, opens ticket sales directly instead.
        auth()->logout();
        $response = $this->post(route('kiosk.pin-login'), ['username' => 'sieves', 'pin' => '456789']);
        $response->assertRedirect(route('admin.tickets.pos'));
    }

    public function test_wrong_pin_for_a_real_username_fails_with_a_generic_message(): void
    {
        $this->posLevelCoordinatorWithPin('sieves', '123456');

        $response = $this->post(route('kiosk.pin-login'), ['username' => 'sieves', 'pin' => '000000']);

        $response->assertSessionHasErrors('pin');
        $this->assertSame('Incorrect username or PIN.', session('errors')->first('pin'));
        $this->assertGuest();
    }

    public function test_an_unknown_username_fails_with_the_same_generic_message(): void
    {
        $response = $this->post(route('kiosk.pin-login'), ['username' => 'nobody', 'pin' => '123456']);

        $response->assertSessionHasErrors('pin');
        $this->assertSame('Incorrect username or PIN.', session('errors')->first('pin'));
        $this->assertGuest();
    }

    public function test_a_pin_belonging_to_a_destination_whose_access_was_revoked_no_longer_works(): void
    {
        $user = User::factory()->create([
            'role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########'),
            'username' => 'sieves',
        ]);
        KioskPin::create([
            'user_id' => $user->id, 'destination_type' => 'event', 'destination_id' => 999999,
            'pin' => Hash::make('654321'), 'pin_set_at' => now(),
        ]);
        // No matching event_coordinators row — access was removed after the PIN was set.

        $response = $this->post(route('kiosk.pin-login'), ['username' => 'sieves', 'pin' => '654321']);

        $response->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_five_wrong_attempts_lock_out_only_that_one_account(): void
    {
        [$victim] = $this->posLevelCoordinatorWithPin('sieves', '123456');
        $this->posLevelCoordinatorWithPin('other', '111111');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('kiosk.pin-login'), ['username' => 'sieves', 'pin' => '999999']);
        }

        $victim->refresh();
        $this->assertNotNull($victim->kiosk_pin_locked_at);

        // Even the CORRECT PIN is now rejected for the locked account...
        $response = $this->post(route('kiosk.pin-login'), ['username' => 'sieves', 'pin' => '123456']);
        $response->assertSessionHasErrors('pin');
        $this->assertGuest();

        // ...but a DIFFERENT kiosk-eligible account is completely unaffected.
        $response = $this->post(route('kiosk.pin-login'), ['username' => 'other', 'pin' => '111111']);
        $this->assertAuthenticated();
    }

    public function test_a_successful_email_login_clears_that_accounts_pin_lockout(): void
    {
        [$user] = $this->posLevelCoordinatorWithPin('sieves', '123456');
        $user->update(['kiosk_pin_failed_attempts' => 5, 'kiosk_pin_locked_at' => now()]);

        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $user->refresh();
        $this->assertNull($user->kiosk_pin_locked_at);
        $this->assertSame(0, $user->kiosk_pin_failed_attempts);
    }
}
