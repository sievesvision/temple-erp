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
 *
 * Also covers whoever actually operates a POS counter — both POS pages
 * (event-pos-donation.blade.php, ticket-pos.blade.php) show an "Open EFT Terminal Settings"
 * link unconditionally, and a station whose own terminal has gone unpaired is exactly the
 * situation that link exists for; waiting on an Admin to be available isn't realistic for an
 * unattended counter. That's a 'pos'-level Event Coordinator specifically (the only tier that
 * ever lands on the POS page with no console access at all — see EventCoordinatorLevel's
 * docblock) — 'entry'-level stays excluded, since an entry-level coordinator already has the
 * full console (and whatever Admin oversight comes with that) rather than only the POS page. For
 * Ticket Controller, 'view' and 'entry' both land on the Ticket POS page the same way (only
 * 'admin' reaches the Ticket Console — see TicketControllerLevel's docblock), so both tiers
 * are included here.
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
            // 'entry'-level deliberately stays excluded — an entry-level coordinator already
            // has the full console (with an Admin to fall back on for terminal setup), unlike
            // a 'pos'-level coordinator who only ever sees the POS page and nothing else.
            return DB::table('event_coordinators')
                ->where('user_id', $user->id)
                ->whereIn('level', ['pos', 'admin'])
                ->exists();
        }

        if ($activeRole === 'Ticket Controller') {
            return TicketControllerLevel::atLeast(TicketControllerLevel::of($user->id), 'view');
        }

        return false;
    }
}
