<?php

namespace App\Services;

use App\Models\KioskDevice;
use App\Models\RolePermission;
use Illuminate\Support\Facades\DB;

/**
 * Who may manage kiosk devices. Two levels, deliberately mirroring EftTerminalAccess:
 *
 * - canManageRegistry(): the registry-wide gate (list devices, register/pair/activate/
 *   deactivate/revoke/rotate) — identical reasoning and an identical query to
 *   EftTerminalAccess::canManageRegistry(): anyone already trusted to manage the EFT
 *   terminal registry for tickets/events is equally trusted to manage the kiosks that use it.
 *
 * - canConfigure(): a finer, device-specific gate for the one thing that must stay scoped
 *   per-module/per-event rather than registry-wide — a kiosk's module/event/terminal/
 *   payment-method configuration. An admin-tier Ticket Controller configures ticket-selling
 *   kiosks; an admin-tier Event Coordinator configures a donation kiosk for their OWN event
 *   only, not any event. A kiosk with no module assigned yet is Admin-only, since nobody
 *   "owns" it until an admin decides what it's for.
 */
class KioskAccess
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
                ->whereIn('level', ['pos', 'admin'])
                ->exists();
        }

        if ($activeRole === 'Ticket Controller') {
            return TicketControllerLevel::atLeast(TicketControllerLevel::of($user->id), 'view');
        }

        return false;
    }

    public static function canConfigure($user, ?string $activeRole, KioskDevice $device): bool
    {
        if (!$user) {
            return false;
        }

        if ($activeRole === 'Admin') {
            return true;
        }

        if ($device->module === 'tickets') {
            return RolePermission::can($activeRole, 'tickets', 'edit')
                || ($activeRole === 'Ticket Controller' && TicketControllerLevel::atLeast(TicketControllerLevel::of($user->id), 'admin'));
        }

        if ($device->module === 'donations' && $device->event_id) {
            return RolePermission::can($activeRole, 'events', 'edit')
                || ($activeRole === 'Event Coordinator' && EventCoordinatorLevel::atLeast(EventCoordinatorLevel::of($device->event_id, $user->id), 'admin'));
        }

        return false;
    }
}
