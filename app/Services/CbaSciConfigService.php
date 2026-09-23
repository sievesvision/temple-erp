<?php

namespace App\Services;

use App\Models\Setting;

/**
 * The genuinely global (not per-terminal) side of the CBA Smart Terminal / mx51 "Simple
 * Cloud Integration" (SCI) setup — mirrors LinklyConfigService's own split exactly: an
 * admin can toggle sandbox/live mode, but the actual credentials are never admin-editable
 * (see config/services.php's cba_sci block and its docblock for why). Per-terminal pairing
 * state (Pairing ID, Signing Secret Part B, the tenant-specific SCI API base URL, etc.)
 * lives on EftTerminal instead — see that model.
 */
class CbaSciConfigService
{
    public static function mode(): string
    {
        return Setting::get('cba_sci_mode', 'sandbox');
    }

    public static function isLive(): bool
    {
        return self::mode() === 'live';
    }

    public static function pairingApiKey(): ?string
    {
        return self::isLive()
            ? config('services.cba_sci.live_pairing_api_key')
            : config('services.cba_sci.test_pairing_api_key');
    }

    public static function signingSecretPartA(): ?string
    {
        return self::isLive()
            ? config('services.cba_sci.live_signing_secret_part_a')
            : config('services.cba_sci.test_signing_secret_part_a');
    }

    /**
     * Fixed per mx51's own documentation — unlike Linkly, sandbox and live pairing share
     * the same pairing endpoint; which environment a pairing lands in is decided by which
     * Pairing API Key was used to authenticate the pairing call, not the URL.
     */
    public static function pairingApiBaseUrl(): string
    {
        return 'https://sci-pairing-api.integrations.mx51.io/v1';
    }

    /**
     * Whether this integration is configured at all — used to decide whether "CBA Smart
     * Terminal" should even appear as an option when adding a new terminal, so an
     * unconfigured install doesn't offer a provider it has no credentials for.
     */
    public static function isConfigured(): bool
    {
        return self::pairingApiKey() !== null && self::signingSecretPartA() !== null;
    }
}
