<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Resolves which Linkly Cloud credential pair (Sandbox or Live) is active, based on the
 * "linkly_mode" Setting — mirrors StripeConfigService's test/live split. Everything here is
 * genuinely global (the same for every paired terminal): the API credentials, the base URLs,
 * and posVendorId (identifies this POS *software*, not any one physical lane). What used to
 * live here too — the pairing secret and posId, which really do differ per physical PIN pad —
 * now live on App\Models\EftTerminal instead, one row per terminal, so more than one can be
 * paired and used at once. See EftTerminal::default()/resolveOrDefault().
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
