<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Reads and ranks a Ticket Controller's tier — the same view/entry/admin concept
 * EventCoordinatorLevel uses, but for the standalone Tickets module: one flat row per user
 * (ticket_controllers.user_id, unique), not scoped to anything, since there's only one
 * ticket module to control. view/entry both land directly on the Ticket POS (see
 * TicketController::manageTickets()'s redirect); only admin reaches the full Ticket Console.
 */
class TicketControllerLevel
{
    private const RANK = ['view' => 1, 'entry' => 2, 'admin' => 3];

    public static function of(int $userId): ?string
    {
        return DB::table('ticket_controllers')->where('user_id', $userId)->value('level');
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
