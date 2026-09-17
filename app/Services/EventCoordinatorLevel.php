<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Reads and ranks an Event Coordinator's per-event permission tier from the
 * event_coordinators pivot: 'view' (read-only) < 'entry'/'pos' (can log/edit/approve
 * donations, resend receipts) < 'admin' (full control of the event, including Settings and
 * managing other coordinators). 'pos' ranks alongside 'entry' for donation-recording
 * purposes (atLeast(..., 'entry') is true for it) but is otherwise a completely separate,
 * more restrictive path: a pos-level coordinator never gets the general console at all —
 * see EventConsoleController::show()'s explicit redirect and AuthController::login() — only
 * the dedicated kiosk-style POS donation page. A row with no level (shouldn't happen
 * post-migration, but defensively handled) is treated as the lowest tier rather than
 * granted anything by default.
 */
class EventCoordinatorLevel
{
    private const RANK = ['view' => 1, 'pos' => 2, 'entry' => 2, 'admin' => 3];

    public static function of(int $eventId, int $userId): ?string
    {
        return DB::table('event_coordinators')
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->value('level');
    }

    public static function atLeast(?string $level, string $required): bool
    {
        return (self::RANK[$level] ?? 0) >= (self::RANK[$required] ?? PHP_INT_MAX);
    }

    public static function isValid(?string $level): bool
    {
        return isset(self::RANK[$level]);
    }

    public static function levels(): array
    {
        return array_keys(self::RANK);
    }
}
