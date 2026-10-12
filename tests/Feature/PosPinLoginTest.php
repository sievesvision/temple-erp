<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PIN login — a faster alternative to email+password for POS-only accounts. One
 * organisation-level PIN per account (users.pos_pin), checked once; which destination(s)
 * it opens is resolved fresh from the account's own current role grants
 * (AuthController::possiblePosDestinations()), never from anything the PIN itself
 * "remembers" — see attemptPosPinLogin()'s own docblock.
 */
class PosPinLoginTest extends TestCase
{
    /**
     * The route's own throttle:10,1 would otherwise accumulate across every test method in
     * this class — the testing cache driver is 'array' (in-memory per process, see
     * phpunit.xml), which RefreshDatabase never touches, so without this a later test could
     * be falsely 429'd by an earlier one's POSTs.
     */
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
    }

    private function coordinatorWithPin(string $level, string $username = 'sieves', string $pin = '123456'): array
    {
        $user = User::factory()->create([
            'role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########'),
            'username' => $username, 'pos_pin' => Hash::make($pin), 'pos_pin_set_at' => now(),
        ]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Pin Test Event', 'event_date' => now()->addMonth()->toDateString(),
            'slug' => 'pin-test-event-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => $level,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $eventId];
    }

    /**
     * PIN login always opens POS mode, even for an admin-level coordinator — the whole point
     * of PIN login is the fastest possible counter entry, not the full console. They aren't
     * stuck there: the POS page's own "Back to console" button reaches the full console
     * whenever they actually need it (see PosDonationController's $canReturnToConsole).
     */
    public function test_an_admin_level_coordinator_can_log_in_with_a_pin_and_lands_on_pos_mode(): void
    {
        [$user, $eventId] = $this->coordinatorWithPin('admin');

        $response = $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '123456']);

        $response->assertRedirect(route('admin.events.pos', $eventId));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Event Coordinator', session('active_role'));
    }

    /**
     * 'entry' level resolves a POS destination the same as 'pos'/'admin' now — the POS
     * donation page a PIN login lands on requires at least 'entry' level to use
     * (EventCoordinatorLevel::atLeast(..., 'entry')), so unlike 'view' (see the next test)
     * there's nothing stopping an entry-level coordinator from actually using it.
     */
    public function test_an_entry_level_coordinator_can_log_in_with_a_pin_and_lands_on_pos_mode(): void
    {
        [$user, $eventId] = $this->coordinatorWithPin('entry');

        $response = $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '123456']);

        $response->assertRedirect(route('admin.events.pos', $eventId));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * 'view' is the one level still excluded (see AuthController::possiblePosDestinations()'s
     * own docblock) — the POS page a PIN login lands on 403s a 'view'-level coordinator. The
     * PIN itself is correct here (it's the same one organisation-level PIN), so this must be
     * rejected by destination resolution finding nothing, not by the PIN hash check.
     */
    public function test_a_view_level_coordinator_cannot_use_a_pos_pin(): void
    {
        $this->coordinatorWithPin('view');

        $response = $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '123456']);

        $response->assertSessionHasErrors('pin');
        $this->assertStringContainsString('no POS access', session('errors')->first('pin'));
        $this->assertGuest();
    }

    public function test_correct_username_and_pin_logs_in_and_lands_on_that_exact_event(): void
    {
        [$user, $eventId] = $this->coordinatorWithPin('pos');

        $response = $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '123456']);

        $response->assertRedirect(route('admin.events.pos', $eventId));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Event Coordinator', session('active_role'));
    }

    /**
     * One PIN, two qualifying destinations (an event plus ticket sales): the generic login
     * page has nothing to disambiguate with, so it goes to the picker grid — but the SAME
     * account's event-specific bookmark URL carries enough context to skip straight past it
     * (see showPosLogin()'s docblock on the context_type/context_slug hidden fields).
     */
    public function test_a_dual_destination_account_goes_to_the_picker_or_straight_to_a_bookmarked_destination(): void
    {
        [$user, $eventId] = $this->coordinatorWithPin('pos', 'sieves', '123456');
        $slug = DB::table('events')->where('event_id', $eventId)->value('slug');
        DB::table('ticket_controllers')->insert(['user_id' => $user->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);

        // No context — two qualifying destinations, so the picker grid.
        $response = $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '123456']);
        $response->assertRedirect(route('pos.select'));

        // Reached through this event's own bookmarked login page — straight there, no picker.
        auth()->logout();
        $response = $this->post(route('pos.pin-login'), [
            'username' => 'sieves', 'pin' => '123456', 'context_type' => 'event', 'context_slug' => $slug,
        ]);
        $response->assertRedirect(route('admin.events.pos', $eventId));

        // Reached through the tickets bookmark — straight to tickets instead.
        auth()->logout();
        $response = $this->post(route('pos.pin-login'), [
            'username' => 'sieves', 'pin' => '123456', 'context_type' => 'tickets',
        ]);
        $response->assertRedirect(route('admin.tickets.pos'));
    }

    public function test_wrong_pin_for_a_real_username_fails_with_a_generic_message(): void
    {
        $this->coordinatorWithPin('pos', 'sieves', '123456');

        $response = $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '000000']);

        $response->assertSessionHasErrors('pin');
        $this->assertSame('Incorrect username or PIN.', session('errors')->first('pin'));
        $this->assertGuest();
    }

    public function test_an_unknown_username_fails_with_the_same_generic_message(): void
    {
        $response = $this->post(route('pos.pin-login'), ['username' => 'nobody', 'pin' => '123456']);

        $response->assertSessionHasErrors('pin');
        $this->assertSame('Incorrect username or PIN.', session('errors')->first('pin'));
        $this->assertGuest();
    }

    /**
     * A correct PIN for an account with NO qualifying destination at all (every assignment
     * revoked, or none ever granted) is rejected with a distinct, specific message — safe to
     * be specific here since it only ever shows after a correct PIN, so it reveals nothing
     * useful to someone merely guessing.
     */
    public function test_a_correct_pin_with_no_remaining_pos_access_is_rejected_specifically(): void
    {
        User::factory()->create([
            'role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########'),
            'username' => 'sieves', 'pos_pin' => Hash::make('654321'), 'pos_pin_set_at' => now(),
        ]);
        // No event_coordinators/ticket_controllers rows at all.

        $response = $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '654321']);

        $response->assertSessionHasErrors('pin');
        $this->assertStringContainsString('no POS access', session('errors')->first('pin'));
        $this->assertGuest();
    }

    public function test_five_wrong_attempts_lock_out_only_that_one_account(): void
    {
        [$victim] = $this->coordinatorWithPin('pos', 'sieves', '123456');
        $this->coordinatorWithPin('pos', 'other', '111111');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '999999']);
        }

        $victim->refresh();
        $this->assertNotNull($victim->pos_pin_locked_at);

        // Even the CORRECT PIN is now rejected for the locked account...
        $response = $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '123456']);
        $response->assertSessionHasErrors('pin');
        $this->assertGuest();

        // ...but a DIFFERENT POS-eligible account is completely unaffected.
        $response = $this->post(route('pos.pin-login'), ['username' => 'other', 'pin' => '111111']);
        $this->assertAuthenticated();
    }

    /**
     * A lockout older than POS_PIN_LOCKOUT_MINUTES self-clears on the next attempt — a
     * counter shouldn't stay dead until an admin happens to notice.
     */
    public function test_lockout_auto_expires_after_the_configured_window(): void
    {
        [$victim] = $this->coordinatorWithPin('pos', 'sieves', '123456');
        $victim->update(['pos_pin_failed_attempts' => 5, 'pos_pin_locked_at' => now()->subMinutes(16)]);

        $response = $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '123456']);

        $this->assertAuthenticated();
        $victim->refresh();
        $this->assertNull($victim->pos_pin_locked_at);
        $this->assertSame(0, $victim->pos_pin_failed_attempts);
    }

    public function test_a_successful_email_login_clears_that_accounts_pin_lockout(): void
    {
        [$user] = $this->coordinatorWithPin('pos', 'sieves', '123456');
        $user->update(['pos_pin_failed_attempts' => 5, 'pos_pin_locked_at' => now()]);

        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password']);

        $user->refresh();
        $this->assertNull($user->pos_pin_locked_at);
        $this->assertSame(0, $user->pos_pin_failed_attempts);
    }

    /**
     * A coarse per-IP backstop on top of the per-account lockout above — matters because the
     * account lockout alone doesn't stop someone spraying many different usernames from one
     * IP.
     */
    public function test_rapid_attempts_from_one_ip_are_throttled(): void
    {
        $this->coordinatorWithPin('pos', 'sieves', '123456');

        for ($i = 0; $i < 10; $i++) {
            $response = $this->post(route('pos.pin-login'), ['username' => 'nobody' . $i, 'pin' => '000000']);
            $response->assertStatus(302);
        }

        $response = $this->post(route('pos.pin-login'), ['username' => 'sieves', 'pin' => '123456']);
        $response->assertStatus(429);
    }
}
