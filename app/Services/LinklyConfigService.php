<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Resolves which Linkly Cloud credential pair (Sandbox or Live) is active, based on the
 * "linkly_mode" Setting — mirrors StripeConfigService's test/live split. Unlike Stripe,
 * pairing a PIN pad produces a "secret" that itself must be persisted (it's what lets the
 * app skip re-pairing on every request) — that's stored as a Setting too, one per mode, so
 * switching modes doesn't clobber the other's pairing.
 */
class LinklyConfigService
{
    public static function mode(): string
    {
        return Setting::get('linkly_mode', 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    public static function isLive(): bool
    {
        return self::mode() === 'live';
    }

    public static function username(): ?string
    {
        return self::isLive()
            ? config('services.linkly.live_username')
            : config('services.linkly.sandbox_username');
    }

    public static function password(): ?string
    {
        return self::isLive()
            ? config('services.linkly.live_password')
            : config('services.linkly.sandbox_password');
    }

    public static function authBaseUrl(): string
    {
        return self::isLive()
            ? 'https://auth.cloud.pceftpos.com'
            : 'https://auth.sandbox.cloud.pceftpos.com';
    }

    public static function apiBaseUrl(): string
    {
        return self::isLive()
            ? 'https://rest.pos.cloud.pceftpos.com'
            : 'https://rest.pos.sandbox.cloud.pceftpos.com';
    }

    private static function secretSettingKey(): string
    {
        return self::isLive() ? 'linkly_secret_live' : 'linkly_secret_sandbox';
    }

    public static function secret(): ?string
    {
        return Setting::get(self::secretSettingKey()) ?: null;
    }

    public static function setSecret(string $secret): void
    {
        Setting::set(self::secretSettingKey(), $secret);
    }

    public static function clearSecret(): void
    {
        Setting::set(self::secretSettingKey(), '');
    }

    public static function isPaired(): bool
    {
        return self::secret() !== null;
    }

    /**
     * A stable identifier for this POS install, generated once and persisted — Linkly's
     * token endpoint requires a posId (and posVendorId) but doesn't document them changing
     * per request, so a fixed value per Setting key (not per mode — the same app instance
     * either way) is simplest.
     */
    public static function posId(): string
    {
        $id = Setting::get('linkly_pos_id');
        if (!$id) {
            $id = (string) Str::uuid();
            Setting::set('linkly_pos_id', $id);
        }
        return $id;
    }

    public static function posVendorId(): string
    {
        $id = Setting::get('linkly_pos_vendor_id');
        if (!$id) {
            $id = (string) Str::uuid();
            Setting::set('linkly_pos_vendor_id', $id);
        }
        return $id;
    }
}
