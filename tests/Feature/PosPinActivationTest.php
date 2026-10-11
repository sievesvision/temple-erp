<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountSetupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The one-time, expiring PIN activation/reset link — the only way anyone other than the
 * account holder can get a PIN set (see AccountSetupService::sendPosPinSetupLink() and
 * AuthController::showPosPinActivate()/submitPosPinActivate()). Mirrors Laravel's own
 * password-reset-token behaviour: single use, time-limited, consumed on success.
 */
class PosPinActivationTest extends TestCase
{
    private function userWithLink(): array
    {
        $user = User::factory()->create(['mobile' => fake()->unique()->numerify('04########')]);
        $setup = AccountSetupService::sendPosPinSetupLink($user);
        $token = str($setup['url'])->afterLast('/');

        return [$user, (string) $token];
    }

    public function test_a_valid_link_sets_the_pin_and_consumes_the_token(): void
    {
        [$user, $token] = $this->userWithLink();

        $getResponse = $this->get(route('pos.pin.activate', ['user' => $user->id, 'token' => $token]));
        $getResponse->assertOk();
        $getResponse->assertSee('Set your POS PIN');

        $postResponse = $this->post(route('pos.pin.activate.submit', ['user' => $user->id, 'token' => $token]), [
            'new_pin' => '246810', 'new_pin_confirmation' => '246810',
        ]);

        $postResponse->assertRedirect(route('pos.login'));
        $user->refresh();
        $this->assertTrue(Hash::check('246810', $user->pos_pin));
        $this->assertNotNull($user->pos_pin_set_at);
        $this->assertSame(0, DB::table('pos_pin_setup_tokens')->where('user_id', $user->id)->count());
    }

    public function test_the_same_link_cannot_be_used_twice(): void
    {
        [$user, $token] = $this->userWithLink();

        $this->post(route('pos.pin.activate.submit', ['user' => $user->id, 'token' => $token]), [
            'new_pin' => '246810', 'new_pin_confirmation' => '246810',
        ]);

        $second = $this->post(route('pos.pin.activate.submit', ['user' => $user->id, 'token' => $token]), [
            'new_pin' => '135791', 'new_pin_confirmation' => '135791',
        ]);

        $second->assertSessionHasErrors('new_pin');
        $this->assertTrue(Hash::check('246810', $user->fresh()->pos_pin));
    }

    public function test_an_expired_link_is_rejected(): void
    {
        [$user, $token] = $this->userWithLink();
        DB::table('pos_pin_setup_tokens')->where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $getResponse = $this->get(route('pos.pin.activate', ['user' => $user->id, 'token' => $token]));
        $getResponse->assertOk();
        $getResponse->assertSee('Link expired');

        $postResponse = $this->post(route('pos.pin.activate.submit', ['user' => $user->id, 'token' => $token]), [
            'new_pin' => '246810', 'new_pin_confirmation' => '246810',
        ]);
        $postResponse->assertSessionHasErrors('new_pin');
        $this->assertNull($user->fresh()->pos_pin);
    }

    public function test_a_bogus_token_is_rejected(): void
    {
        $user = User::factory()->create(['mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->get(route('pos.pin.activate', ['user' => $user->id, 'token' => 'not-a-real-token']));

        $response->assertOk();
        $response->assertSee('Link expired');
    }

    /**
     * Triggering a new link for the same account invalidates any earlier unused one, rather
     * than leaving several simultaneously valid links outstanding.
     */
    public function test_requesting_a_new_link_invalidates_the_previous_one(): void
    {
        [$user, $firstToken] = $this->userWithLink();
        $second = AccountSetupService::sendPosPinSetupLink($user);
        $secondToken = (string) str($second['url'])->afterLast('/');

        $this->assertSame(1, DB::table('pos_pin_setup_tokens')->where('user_id', $user->id)->count());

        $oldLinkResponse = $this->post(route('pos.pin.activate.submit', ['user' => $user->id, 'token' => $firstToken]), [
            'new_pin' => '246810', 'new_pin_confirmation' => '246810',
        ]);
        $oldLinkResponse->assertSessionHasErrors('new_pin');

        $newLinkResponse = $this->post(route('pos.pin.activate.submit', ['user' => $user->id, 'token' => $secondToken]), [
            'new_pin' => '246810', 'new_pin_confirmation' => '246810',
        ]);
        $newLinkResponse->assertRedirect(route('pos.login'));
    }

    public function test_activating_a_pin_also_clears_any_existing_lockout(): void
    {
        [$user, $token] = $this->userWithLink();
        $user->update(['pos_pin_failed_attempts' => 5, 'pos_pin_locked_at' => now()]);

        $this->post(route('pos.pin.activate.submit', ['user' => $user->id, 'token' => $token]), [
            'new_pin' => '246810', 'new_pin_confirmation' => '246810',
        ]);

        $user->refresh();
        $this->assertNull($user->pos_pin_locked_at);
        $this->assertSame(0, $user->pos_pin_failed_attempts);
    }
}
