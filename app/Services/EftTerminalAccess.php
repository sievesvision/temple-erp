<?php

namespace App\Services;

use App\Models\RolePermission;
use Illuminate\Support\Facades\DB;

/**
 * Who may manage the shared EFT terminal registry (add a terminal, or pair one via the
 * standalone EFT Terminal Settings page — see EftTerminalController/LinklyController) —
 * deliberately broader than the main Settings page's own 'settings' permission, since an
 * event-admin coordinator or ticket-admin controller already has full pairing/refund/logon
 * rights over any terminal from their own console (see DonationController::
 * canManageEftForEvent() / TicketController::canManageTicketConsole()) and needs to be able
 * to add a *new* terminal too — not just re-pair an existing row an Admin had to create for
 * them first. "Set default" and "remove a terminal" stay System-Admin-only (see
 * EftTerminalController::setDefault()/destroy()) since those affect every other console's
 * fallback resolution, not just the caller's own event/module.
 */
class EftTerminalAccess
{
    public static function canManageRegistry($user, ?string $activeRole): bool
    {
        if (!$user) {
            return false;
        }

        if ($activeRole === 'Admin'
            || RolePermission::can($activeRole, 'events', 'edit')
            || RolePermission::can($activeRole, 'tickets', 'edit')) {
            return true;
        }

        if ($activeRole === 'Event Coordinator') {
            return DB::table('event_coordinators')
                ->where('user_id', $user->id)
                ->where('level', 'admin')
                ->exists();
        }

        if ($activeRole === 'Ticket Controller') {
            return TicketControllerLevel::atLeast(TicketControllerLevel::of($user->id), 'admin');
        }

        return false;
    }
}
