<?php

namespace App\Http\Controllers;

use App\Models\CashBanking;
use App\Models\CashSettlement;
use App\Models\Event;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\CashSettlementService;
use App\Services\EventCoordinatorLevel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Actions for the Cash Settlement widget — embedded as the full "Cash Banking" pane in the
 * Event Console / Ticket Console for admin/entry-tier users, and as a stripped-down
 * "Settle Till" widget with no history on the Event POS / Ticket POS page for a pos-level
 * event coordinator (who never reaches the console at all — see EventConsoleController::
 * show()'s own redirect). See CashSettlementService::canSettle()/canViewHistory() for
 * exactly who gets which. Every scope posts here and redirects straight back to wherever
 * it was submitted from, mirroring how every other console/POS form already works.
 */
class CashSettlementController extends Controller
{
    /**
     * Kept as a thin wrapper so every other method here reads "can this user touch cash
     * settlement for this scope at all" in one place — CashSettlementService::canSettle()
     * is the actual rule, shared with the page controllers that decide whether to render
     * the widget in the first place.
     */
    private function canManageEventCash(Event $event, $user, ?string $activeRole): bool
    {
        return CashSettlementService::canSettle('event', $event->event_id, $user, $activeRole);
    }

    private function canManageTicketsCash($user, ?string $activeRole): bool
    {
        return CashSettlementService::canSettle('tickets', null, $user, $activeRole);
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

    /**
     * Re-renders the Settle Till widget with live data — the POS page's own #settleTillModal
     * is already-rendered page markup, so opening it (no navigation, no server round-trip)
     * would otherwise keep showing whatever "Current Period Transactions" snapshot was on
     * screen at the page's own last load, missing anything recorded since (a sale from
     * another station, a public donation-page submission). The modal's own JS fetches this
     * on every `show.bs.modal` and swaps it in — see event-pos-donation.blade.php/
     * ticket-pos.blade.php's own small script right after #settleTillModal.
     */
    public function pane(Request $request)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        $scope = $request->input('scope');
        $eventId = null;

        if ($scope === 'event') {
            $event = Event::findOrFail((int) $request->input('event_id'));
            if (!$this->canManageEventCash($event, $user, $activeRole)) {
                abort(403, 'Unauthorized access.');
            }
            $eventId = $event->event_id;
        } elseif ($scope === 'tickets') {
            if (!$this->canManageTicketsCash($user, $activeRole)) {
                abort(403, 'Unauthorized access.');
            }
        } else {
            abort(422, 'Unknown scope.');
        }

        $canViewCashHistory = CashSettlementService::canViewHistory($scope, $eventId, $user, $activeRole);
        $paneData = CashSettlementService::paneData($scope, $eventId, $canViewCashHistory);

        // Same bar PosDonationController/EventConsoleController use for this same widget —
        // kept in sync by hand since there's no single shared helper for it yet. Tickets
        // scope has no edit route for an order at all, so it's always false there.
        $canEditCurrentTransactions = false;
        if ($scope === 'event') {
            $coordinatorLevel = $activeRole === 'Event Coordinator' ? EventCoordinatorLevel::of($eventId, $user->id) : null;
            $canEditCurrentTransactions = $activeRole === 'Admin'
                || RolePermission::can($activeRole, 'donations', 'edit')
                || EventCoordinatorLevel::atLeast($coordinatorLevel, 'entry');
        }

        return view('admin.partials.cash-settlement-pane', array_merge($paneData, [
            'scope' => $scope,
            'eventId' => $eventId,
            'canViewCashHistory' => $canViewCashHistory,
            'canEditCurrentTransactions' => $canEditCurrentTransactions,
            'context' => $request->input('context', 'pos'),
            'temple' => Setting::templeBranding(),
        ]))->render();
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

        $validated = $request->validate([
            'period_end' => 'nullable|date',
            'till_count' => 'required|numeric|min:0',
            'amount_banked' => 'required|numeric|min:0|lte:till_count',
            'variance_note' => 'nullable|string|max:1000',
        ], [
            'amount_banked.lte' => "You can't bank more than what's counted in the till.",
        ]);
        $periodEnd = $request->filled('period_end') ? Carbon::parse($request->input('period_end')) : Carbon::now();

        // A mismatch between what was counted and what the system expected must be
        // explained, not just silently absorbed into the carried-forward balance.
        $preview = CashSettlementService::preview($scope, $eventId, $periodEnd);
        $expected = round($preview['opening_balance'] + $preview['cash_received'], 2);
        $variance = round($validated['till_count'] - $expected, 2);
        if (abs($variance) >= 0.01 && !trim((string) ($validated['variance_note'] ?? ''))) {
            return redirect()->back()->withErrors([
                'variance_note' => "The till count doesn't match the expected balance (expected {$expected}, counted {$validated['till_count']}) — please add a note explaining the difference before settling.",
            ])->withInput();
        }

        CashSettlementService::run(
            $scope, $eventId, Auth::id(),
            (float) $validated['till_count'], (float) $validated['amount_banked'],
            $validated['variance_note'] ?? null, $periodEnd
        );

        return redirect($redirectBack)->with('success', 'Settlement recorded — the opening balance for the next period is now set.');
    }

    /**
     * variance_note is always editable; till_count/amount_banked only on the most recent
     * settlement for its scope (see CashSettlementService::canEditFigures()) — editing an
     * older one would desync every settlement that ran after it.
     */
    public function updateSettlement(Request $request, CashSettlement $settlement)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!CashSettlementService::canViewHistory($settlement->scope, $settlement->event_id, $user, $activeRole)) {
            abort(403, 'Unauthorized access.');
        }

        $rules = ['variance_note' => 'nullable|string|max:1000'];
        $editFigures = CashSettlementService::canEditFigures($settlement);
        if ($editFigures) {
            $rules['till_count'] = 'required|numeric|min:0';
            $rules['amount_banked'] = 'required|numeric|min:0|lte:till_count';
        }
        $validated = $request->validate($rules);

        CashSettlementService::update($settlement, $editFigures ? $validated : ['variance_note' => $validated['variance_note'] ?? null], $user->id);

        return redirect()->back()->with('success', 'Settlement updated.');
    }

    /**
     * Both actions post from the same widget for either scope, so a hidden 'scope'/
     * 'event_id' pair (validated against that scope's own access gate) is how the request
     * says which one it means. Returns [scope, eventId, redirectBackRoute].
     *
     * The redirect target is wherever the form was actually submitted from (the Referer),
     * not a hardcoded "home" route — the widget lives on four different pages (Event
     * Console, Ticket Console, Event POS, Ticket POS), and for a pos-level coordinator the
     * console route would just bounce them straight back to the POS page anyway (see
     * EventConsoleController::show()'s own redirect), silently eating the success flash
     * message in that second hop. Same reasoning as EftTerminalRegistryAccessTest's own
     * redirect()->back() pattern for a form that's @include'd on more than one page.
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
            $fallback = route('admin.events.portal', $event->slug ?? $event->event_id);
            return ['event', $event->event_id, url()->previous($fallback)];
        }

        if ($scope === 'tickets') {
            if (!$this->canManageTicketsCash($user, $activeRole)) {
                abort(403, 'Unauthorized access.');
            }
            return ['tickets', null, url()->previous(route('admin.tickets.index'))];
        }

        abort(422, 'Unknown scope.');
    }
}
