<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA v2 ("I'm not a robot" checkbox) for the login, devotee registration and
 * public donation forms — added after this app's own mail-abuse incident (a spam-suspended
 * mailbox from a no-cooldown OTP endpoint) made bot traffic on these forms a real concern.
 * Mirrors StripeConfigService/LinklyConfigService's split: the site/secret key pair always
 * lives in .env (config('services.recaptcha.*')), never in the database; only the on/off
 * switch is a Setting, so it can be enabled without a deploy once keys are in place.
 */
class RecaptchaService
{
    public static function siteKey(): ?string
    {
        return config('services.recaptcha.site_key') ?: null;
    }

    private static function secretKey(): ?string
    {
        return config('services.recaptcha.secret_key') ?: null;
    }

    /**
     * Whether the widget should actually render/be enforced — never true if the keys aren't
     * configured, regardless of the Setting, so an admin flipping the toggle on before adding
     * keys to .env can't accidentally lock every form.
     */
    public static function enabled(): bool
    {
        return (bool) Setting::get('recaptcha_enabled', false) && self::siteKey() && self::secretKey();
    }

    /**
     * Verifies a submitted g-recaptcha-response token against Google's siteverify endpoint.
     * Fails "closed" (returns false) on a missing token or any network/API error — a
     * legitimate user just sees "please complete the reCAPTCHA" and retries, which is a far
     * smaller cost than a captcha that silently never blocks anything.
     */
    public static function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (!self::enabled()) {
            return true;
        }

        if (!$token) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => self::secretKey(),
                'response' => $token,
                'remoteip' => $remoteIp,
            ]);
        } catch (\Exception $e) {
            Log::warning('reCAPTCHA verification request failed', ['message' => $e->getMessage()]);
            return false;
        }

        return (bool) $response->json('success');
    }
}
