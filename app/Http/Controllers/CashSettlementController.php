<?php

namespace App\Http\Controllers;

use App\Models\CashBanking;
use App\Models\Event;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\CashSettlementService;
use App\Services\EventCoordinatorLevel;
use App\Services\TicketControllerLevel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The two actions for the Cash Banking pane embedded in the Event Console and the Ticket
 * Console (see CashSettlementService::paneData(), called from EventConsoleController::
 * show() and TicketController::manageTickets()) — recording a bank deposit and locking in
 * a settlement. Both scopes post here and redirect straight back to their own console,
 * mirroring how every other console form (Settings, Coordinators) already works.
 */
class CashSettlementController extends Controller
{
    private function canManageEventCash(Event $event, $user, ?string $activeRole): bool
    {
        $coordinatorLevel = $activeRole === 'Event Coordinator'
            ? EventCoordinatorLevel::of($event->event_id, $user->id)
            : null;

        return $activeRole === 'Admin'
            || RolePermission::can($activeRole, 'events', 'edit')
            || EventCoordinatorLevel::atLeast($coordinatorLevel, 'admin');
    }

    private function canManageTicketsCash($user, ?string $activeRole): bool
    {
        if ($activeRole === 'Admin' || RolePermission::can($activeRole, 'tickets', 'edit')) {
            return true;
        }

        $controllerLevel = $activeRole === 'Ticket Controller' ? TicketControllerLevel::of($user->id) : null;
        return TicketControllerLevel::atLeast($controllerLevel, 'admin');
    }

    /**
     * The ticket system's own reference bank account — kept as its own small form/route
     * (rather than folded into TicketController::updateSettings()) so submitting it can
     * never accidentally clobber that method's separate payment-methods-override logic,
     * which infers "clear the override" from the *absence* of its own checkbox field.
     */
    public function updateTicketBankSettings(Request $request)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$this->canManageTicketsCash($user, $activeRole)) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'ticket_donation_account_name' => 'nullable|string|max:255',
            'ticket_donation_bank_name' => 'nullable|string|max:255',
            'ticket_donation_bsb' => 'nullable|string|max:20',
            'ticket_donation_account_number' => 'nullable|string|max:50',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        AuditLogService::log('Updated ticket bank account settings');

        return redirect()->back()->with('success', 'Ticket bank account settings saved.');
    }

    public function recordBanking(Request $request)
    {
        [$scope, $eventId, $redirectBack] = $this->resolveScopeFromRequest($request);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'banked_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        CashBanking::create([
            'scope' => $scope,
            'event_id' => $eventId,
            'amount' => $validated['amount'],
            'banked_date' => $validated['banked_date'],
            'reference' => $validated['reference'] ?? null,
            'note' => $validated['note'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        $label = $scope === 'event' ? "event #{$eventId}" : 'tickets';
        AuditLogService::log("Recorded cash banking of {$validated['amount']} for {$label}", Auth::id(), $eventId);

        return redirect($redirectBack)->with('success', 'Banking entry recorded.');
    }

    public function runSettlement(Request $request)
    {
        [$scope, $eventId, $redirectBack] = $this->resolveScopeFromRequest($request);

        $request->validate(['period_end' => 'nullable|date']);
        $periodEnd = $request->filled('period_end') ? Carbon::parse($request->input('period_end')) : Carbon::now();

        CashSettlementService::run($scope, $eventId, Auth::id(), $periodEnd);

        return redirect($redirectBack)->with('success', 'Settlement recorded — the opening balance for the next period is now set.');
    }

    /**
     * Both actions post from the same pane for either scope, so a hidden 'scope'/'event_id'
     * pair (validated against that scope's own access gate) is how the request says which
     * one it means. Returns [scope, eventId, redirectBackRoute].
     */
    private function resolveScopeFromRequest(Request $request): array
    {
        $scope = $request->input('scope');
        $eventId = $request->input('event_id') ? (int) $request->input('event_id') : null;
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        if ($scope === 'event') {
            $event = Event::findOrFail($eventId);
            if (!$this->canManageEventCash($event, $user, $activeRole)) {
                abort(403, 'Unauthorized access.');
            }
            return ['event', $event->event_id, route('admin.events.console', $event->event_id)];
        }

        if ($scope === 'tickets') {
            if (!$this->canManageTicketsCash($user, $activeRole)) {
                abort(403, 'Unauthorized access.');
            }
            return ['tickets', null, route('admin.tickets.index')];
        }

        abort(422, 'Unknown scope.');
    }
}
