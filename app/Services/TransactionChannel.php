<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

/**
 * Which physical/UI channel a payment was actually recorded through — stamped once at
 * creation time (see each insert/create call site) onto the new source_channel column
 * added alongside this. Never backfilled for older rows; the Transactions dashboard shows
 * those as "Unknown" rather than guessing.
 */
class TransactionChannel
{
    public const KIOSK = 'kiosk';
    public const REGISTER = 'register';
    public const POS = 'pos';
    public const PORTAL = 'portal';
    public const PUBLIC = 'public';
    public const STAFF_COUNTER = 'staff_counter';

    public static function resolve(): string
    {
        $request = request();

        // Anonymous guest donations and a logged-in devotee's own self-service donation are
        // the same channel for this purpose — the donor-facing website, never a staff tool —
        // so both land here rather than "portal" (which means a staff member's own session).
        if (!Auth::check() || (Auth::user()->role ?? null) === 'Devotee') {
            return self::PUBLIC;
        }

        if ($request && $request->attributes->get('kiosk_device')) {
            return self::KIOSK;
        }

        if ($request && $request->attributes->get('register')) {
            return self::REGISTER;
        }

        // The counter POS page and the Portal's own Quick Entry post to the exact same
        // backend endpoints (storeDevoteeDonation/storeGuestDonation/startEftCharge/ticket
        // order), so the route name can't tell them apart — the page the request actually
        // came from can. Same-origin fetch() calls keep the Referer header by default, so
        // this is reliable in practice even though it isn't a security boundary.
        $referer = $request?->headers->get('referer', '') ?? '';
        if ($referer && str_contains($referer, '/pos')) {
            return self::POS;
        }

        return self::PORTAL;
    }

    public static function label(?string $channel): string
    {
        return match ($channel) {
            self::KIOSK => 'Kiosk',
            self::REGISTER => 'Register',
            self::POS => 'POS Counter',
            self::PORTAL => 'Portal (Quick Entry)',
            self::PUBLIC => 'Public Self-Service',
            self::STAFF_COUNTER => 'Staff Offline Counter',
            default => 'Unknown',
        };
    }

    public static function all(): array
    {
        return [self::KIOSK, self::REGISTER, self::POS, self::PORTAL, self::STAFF_COUNTER, self::PUBLIC];
    }
}
