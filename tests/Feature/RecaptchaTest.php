<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Google reCAPTCHA gates Login, Devotee Registration and the public donation form (see
 * app/Services/RecaptchaService.php) — added after this app's own mail-abuse incident made
 * bot traffic on these forms a real concern. Covers the "never breaks a form before it's
 * configured" fail-open design and that a bad/missing token is actually rejected once it is.
 */
class RecaptchaTest extends TestCase
{
    private function enableFakeRecaptcha(): void
    {
        config(['services.recaptcha.site_key' => 'test-site-key', 'services.recaptcha.secret_key' => 'test-secret-key']);
        Setting::set('recaptcha_enabled', '1');
    }

    // The Setting can be on, but with no site/secret key in .env the widget must never
    // actually block a real user — this is the state every deployment starts in.
    public function test_disabled_when_keys_are_not_configured_even_if_setting_is_on(): void
    {
        Setting::set('recaptcha_enabled', '1');
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret_key' => null]);

        $this->assertFalse(RecaptchaService::enabled());
        $this->assertTrue(RecaptchaService::verify(null));
    }

    public function test_verify_fails_closed_on_missing_token_once_configured(): void
    {
        $this->enableFakeRecaptcha();

        $this->assertFalse(RecaptchaService::verify(null));
        $this->assertFalse(RecaptchaService::verify(''));
    }

    public function test_verify_trusts_a_successful_google_siteverify_result(): void
    {
        $this->enableFakeRecaptcha();
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true], 200)]);

        $this->assertTrue(RecaptchaService::verify('a-token'));
    }

    // Http::fake() calls stack within a test rather than replacing each other (a
    // second registration doesn't override the first for an already-matched pattern —
    // see the fix in tests/Feature/LinklyCorePaymentsTest.php for the same lesson), so
    // this is deliberately its own test rather than a second fake() call appended to the
    // one above.
    public function test_verify_rejects_a_failed_google_siteverify_result(): void
    {
        $this->enableFakeRecaptcha();
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false], 200)]);

        $this->assertFalse(RecaptchaService::verify('a-bad-token'));
    }

    // Baseline: with reCAPTCHA never configured (the out-of-the-box state), login works
    // exactly as it always has — no g-recaptcha-response needed at all.
    public function test_login_works_without_recaptcha_response_when_not_configured(): void
    {
        $user = User::factory()->create([
            'role' => 'Devotee',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_blocked_without_a_valid_recaptcha_once_enabled(): void
    {
        $this->enableFakeRecaptcha();
        $user = User::factory()->create([
            'role' => 'Devotee',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();
    }

    public function test_login_succeeds_with_a_valid_recaptcha_once_enabled(): void
    {
        $this->enableFakeRecaptcha();
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true], 200)]);
        $user = User::factory()->create([
            'role' => 'Devotee',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'g-recaptcha-response' => 'a-valid-token',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    // The public "donate without login" form: baseline works unconfigured, and is blocked
    // without a valid token once enabled — same gate, different endpoint.
    public function test_public_donation_is_blocked_without_a_valid_recaptcha_once_enabled(): void
    {
        $this->enableFakeRecaptcha();

        $response = $this->post('/donate-without-login', [
            'donor_name' => 'Jane Donor',
            'amount' => '25',
            'purpose' => 'General Fund',
            'payment_method' => 'Cash',
        ]);

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertDatabaseMissing('donations_without_logins', ['donor_name' => 'Jane Donor']);
    }
}
