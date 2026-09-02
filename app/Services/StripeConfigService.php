<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Resolves which Stripe key pair (Test or Live) is actually active, based on the
 * "stripe_mode" Setting — admin-configurable from Settings > Donations & Payments,
 * no server access needed to switch. The key material itself always stays in .env,
 * never in the database; only the mode selector is stored as a Setting.
 */
class StripeConfigService
{
    public static function mode(): string
    {
        return Setting::get('stripe_mode', 'test') === 'live' ? 'live' : 'test';
    }

    public static function isLive(): bool
    {
        return self::mode() === 'live';
    }

    public static function key(): ?string
    {
        return self::isLive()
            ? config('services.stripe.live_key')
            : config('services.stripe.test_key');
    }

    public static function secret(): ?string
    {
        return self::isLive()
            ? config('services.stripe.live_secret')
            : config('services.stripe.test_secret');
    }

    public static function webhookSecret(): ?string
    {
        return self::isLive()
            ? config('services.stripe.live_webhook_secret')
            : config('services.stripe.test_webhook_secret');
    }
}
