<?php

namespace App\Http\Controllers;

use App\Models\LinklyTransaction;
use App\Models\RolePermission;
use App\Models\SciTransaction;
use App\Models\Setting;
use App\Services\EventCoordinatorLevel;
use App\Services\EventDonationBreakdown;
use App\Services\LinklyConfigService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The fullscreen per-event "console": donations table, quick entry, and a mini dashboard
 * for one event at a time. Reachable by Admin, by any role RolePermission grants 'events'
 * view to (e.g. Committee), or by an Event Coordinator scoped to just this event via the
 * event_coordinators pivot — mirrors the existing "route broader than capability, narrowed
 * inline" pattern already used everywhere else in this app.
 */
class EventConsoleController extends Controller
{
    public function show($eventId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        // A coordinator's level (view/entry/admin/pos) — null if they're not a coordinator
        // for this event at all. Every capability below is derived from this single lookup.
        $coordinatorLevel = $activeRole === 'Event Coordinator'
            ? EventCoordinatorLevel::of((int) $eventId, $user->id)
            : null;

        // A pos-level coordinator never gets the general console — only the dedicated kiosk
        // page. Send them there instead of a bare 403 if they land on this URL somehow.
        if ($coordinatorLevel === 'pos') {
            return redirect()->route('admin.events.pos', $eventId);
        }

        $isCoordinatorForEvent = $coordinatorLevel !== null;

        if (!($activeRole === 'Admin' || RolePermission::can($activeRole, 'events', 'view') || $isCoordinatorForEvent)) {
            abort(403, 'Unauthorized access.');
        }

        $breakdown = EventDonationBreakdown::forEvent((int) $eventId);
        if (!$breakdown['event']) {
            abort(404, 'Event not found.');
        }

        $event = $breakdown['event'];
        $options = $breakdown['options'];
        $rows = $breakdown['rows'];

        $paidRows = $rows->where('payment_status', 'Paid');
        $pendingRows = $rows->where('payment_status', 'Pending');
        $todayRows = $paidRows->where('donation_date', now()->toDateString());
        // A rough donor headcount: distinct devotees by id, distinct guests by whichever
        // identifying detail they gave (email, else mobile, else just their name) — good
        // enough for an at-a-glance summary tile, not a dedupe-for-billing guarantee.
        $totalDonors = $paidRows->map(fn ($r) => $r->donation_type === 'devotee'
            ? 'devotee:' . $r->devotee_id
            : 'guest:' . strtolower(trim($r->email ?: $r->mobile ?: $r->display_name)))->unique()->count();
        // Normalizes the guest/devotee payment-method naming difference (guest uses "Bank",
        // devotee uses "Bank Transfer") into one bucket, matching the Excel summary sheet.
        $normalizeMethod = fn (?string $m) => in_array(trim((string) $m), ['Bank', 'Bank Transfer'], true)
            ? 'Bank Transfer'
            : (trim((string) $m) ?: 'Unspecified');

        $summary = [
            'paid_total' => $paidRows->sum('amount'),
            'paid_count' => $paidRows->count(),
            'pending_total' => $pendingRows->sum('amount'),
            'pending_count' => $pendingRows->count(),
            'donation_count' => $rows->count(),
            'today_total' => $todayRows->sum('amount'),
            'total_donors' => $totalDonors,
            'option_totals' => $options->mapWithKeys(fn ($opt) => [
                $opt->id => $paidRows->sum(fn ($r) => $r->option_amounts[$opt->id] ?? 0),
            ]),
            'method_totals' => $paidRows->groupBy(fn ($r) => $normalizeMethod($r->payment_method))->map->sum('amount'),
        ];

        // Donation Trend (Dashboard) — paid totals bucketed by week (last 8) and by month
        // (last 6), so a coordinator can see whether giving is picking up or slowing down
        // without opening the full table. Dates parsed once and reused across both buckets
        // rather than re-parsing donation_date on every week/month comparison.
        $paidRowsWithDate = $paidRows->map(fn ($r) => (object) ['amount' => $r->amount, 'date' => \Carbon\Carbon::parse($r->donation_date)]);
        $weeklyTrend = collect(range(7, 0))->map(function ($weeksAgo) use ($paidRowsWithDate) {
            $weekStart = now()->subWeeks($weeksAgo)->startOfWeek();
            $weekEnd = now()->subWeeks($weeksAgo)->endOfWeek();
            return [
                'label' => $weekStart->format('d M'),
                'total' => $paidRowsWithDate->filter(fn ($r) => $r->date->between($weekStart, $weekEnd))->sum('amount'),
            ];
        });
        $monthlyTrend = collect(range(5, 0))->map(function ($monthsAgo) use ($paidRowsWithDate) {
            $monthStart = now()->subMonths($monthsAgo)->startOfMonth();
            $monthEnd = now()->subMonths($monthsAgo)->endOfMonth();
            return [
                'label' => $monthStart->format('M Y'),
                'total' => $paidRowsWithDate->filter(fn ($r) => $r->date->between($monthStart, $monthEnd))->sum('amount'),
            ];
        });

        $devotees = DB::table('devotees')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->select('devotees.devotee_id', 'users.name', 'users.email', 'users.mobile')
            ->orderBy('users.name')
            ->get();

        $enabledPaymentMethods = json_decode(Setting::get('enabled_payment_methods', '["Cash","Bank Transfer","Cheque"]'), true) ?: [];
        // The global baseline includes Stripe only when it's globally enabled — Stripe isn't
        // part of the enabled_payment_methods array itself, it's its own toggle.
        $globalPaymentMethods = $enabledPaymentMethods;
        if ((bool) Setting::get('stripe_enabled', true)) {
            $globalPaymentMethods[] = 'Stripe';
        }
        // An event's own override (if set in Settings) replaces the global list entirely for
        // this event's Quick Entry payment method choices — e.g. disabling Stripe just here.
        $effectivePaymentMethods = $event->paymentMethodsOverride() ?? $globalPaymentMethods;

        // Other events this user can switch to from the topbar — Admin/Committee see every
        // event, an Event Coordinator only the ones they're assigned to.
        if ($activeRole === 'Event Coordinator') {
            $switchableEvents = DB::table('event_coordinators')
                ->join('events', 'event_coordinators.event_id', '=', 'events.event_id')
                ->where('event_coordinators.user_id', $user->id)
                ->where('events.event_id', '!=', $event->event_id)
                ->orderBy('events.event_date')
                ->select('events.event_id', 'events.event_name')
                ->get();
        } else {
            $switchableEvents = DB::table('events')
                ->where('event_id', '!=', $event->event_id)
                ->orderBy('event_date')
                ->select('event_id', 'event_name')
                ->get();
        }

        $eventOptionsForJs = $options->map(fn ($o) => [
            'id' => $o->id,
            'label' => $o->label,
            'amount' => $o->amount === null ? null : (float) $o->amount,
            'allow_quantity' => (bool) $o->allow_quantity,
        ])->values();

        // event-view coordinators get Dashboard + All Donations only; event-entry adds
        // donation entry/edit/approve; event-admin adds Settings and coordinator management.
        $canAddDonation = $activeRole === 'Admin' || RolePermission::can($activeRole, 'donations', 'add') || EventCoordinatorLevel::atLeast($coordinatorLevel, 'entry');
        $canEditDonation = $activeRole === 'Admin' || RolePermission::can($activeRole, 'donations', 'edit') || EventCoordinatorLevel::atLeast($coordinatorLevel, 'entry');
        $canDeleteDonation = $activeRole === 'Admin' || RolePermission::can($activeRole, 'donations', 'delete');
        // Event settings (donation options, contacts, gallery, status, etc.) — only an
        // event-admin coordinator gets this, not event-entry/event-view.
        $canEditEvent = $activeRole === 'Admin' || RolePermission::can($activeRole, 'events', 'edit') || EventCoordinatorLevel::atLeast($coordinatorLevel, 'admin');
        // Managing this event's own coordinators — the system Admin always can; an
        // event-admin coordinator can too, but only to add/remove entry/view coordinators
        // (never grant admin — that stays an Admin-only action via Manage Events).
        $canManageEventCoordinators = $activeRole === 'Admin' || EventCoordinatorLevel::atLeast($coordinatorLevel, 'admin');

        $eventCoordinators = collect();
        $allUsersForCoordinators = collect();
        if ($canManageEventCoordinators) {
            $eventCoordinators = DB::table('event_coordinators')
                ->join('users', 'event_coordinators.user_id', '=', 'users.id')
                ->where('event_coordinators.event_id', $event->event_id)
                ->select('users.id', 'users.name', 'users.email', 'users.username', 'users.status', 'users.last_login_at', 'users.last_reset_email_sent_at', 'event_coordinators.level')
                ->orderBy('users.name')
                ->get();

            $allUsersForCoordinators = DB::table('users')->select('id', 'name', 'email')->orderBy('name')->get();
        }

        // The Logs pane is an event-admin-only view (same ceiling as coordinator management)
        // — event-entry/event-view coordinators run donations day-to-day but don't get to
        // audit who did what. Capped at the 200 most recent entries; this is a console pane,
        // not the full paginated admin-wide log viewer (see LogController).
        $canViewEventLogs = $canManageEventCoordinators;
        $eventLogs = collect();
        if ($canViewEventLogs) {
            $eventLogs = DB::table('audit_logs')
                ->leftJoin('users', 'audit_logs.performed_by', '=', 'users.id')
                ->where('audit_logs.event_id', $event->event_id)
                ->select('audit_logs.action', 'audit_logs.ip_address', 'audit_logs.created_at', 'users.name as performed_by_name')
                ->orderBy('audit_logs.created_at', 'desc')
                ->limit(200)
                ->get();
        }

        // EFTPOS/Linkly accreditation pane — event-admin only (same tier as Settings/
        // Coordinators/Logs above). The terminal itself is a single shared resource (not
        // per-event), so pairing status/mode/Cloud ID are global; only the recent
        // transactions list is scoped to this event.
        $linklyTransactions = collect();
        $sciTransactions = collect();
        $eftTransactions = collect();
        if ($canEditEvent) {
            // Purchase/refund only — a Logon proves nothing about money moving and just
            // clutters a list that's meant to be this event's actual payment history.
            $linklyTransactions = LinklyTransaction::with('eftTerminal')->where('event_id', $event->event_id)
                ->whereIn('txn_type', ['purchase', 'refund'])
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
            // Combined Linkly + mx51 view — the two tables have different columns/status
            // vocabularies, so each row carries its own 'provider' tag and the blade branches
            // on that rather than trying to force one shared shape onto both.
            $sciTransactions = SciTransaction::with('eftTerminal')
                ->where('event_id', $event->event_id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
            $eftTransactions = $linklyTransactions->map(fn ($t) => (object) ['provider' => 'linkly', 'txn' => $t, 'created_at' => $t->created_at])
                ->concat($sciTransactions->map(fn ($t) => (object) ['provider' => 'cba_sci', 'txn' => $t, 'created_at' => $t->created_at]))
                ->sortByDesc('created_at')
                ->take(50)
                ->values();
        }
        // "All Donations" (below) folds the EFT purchase/refund history in directly instead
        // of keeping a second, overlapping transactions table on the EFTPOS pane — an
        // approved purchase's own donation row already IS that transaction, so it's looked
        // up here (by donation_id) purely to show its provider/terminal/reference and offer
        // Refund inline, never duplicated as a second row. A refund itself has no donation
        // row of its own (see CbaSciController::refund()/DonationController::refundEftCharge()
        // — it just flips the original donation to 'Cancelled'), and a purchase that never
        // became a donation (declined/cancelled/abandoned) has no row anywhere else — both
        // are shown as their own "orphan" rows so that history isn't lost from the merge.
        // Keyed by "donation_type:donation_id" rather than the bare id — devotee and guest
        // donations are separate tables that both restart their auto-increment from 1, so a
        // bare id would risk matching one type's row against the other type's transaction.
        $linklyPurchaseByDonation = $linklyTransactions->where('txn_type', 'purchase')->keyBy(fn ($t) => $t->donation_type . ':' . $t->donation_id);
        $sciPurchaseByDonation = $sciTransactions->where('txn_type', 'purchase')->keyBy(fn ($t) => $t->donation_type . ':' . $t->donation_id);
        $eftOrphanRows = $eftTransactions->filter(fn ($row) => $row->txn->txn_type === 'refund' || !$row->txn->donation_id)->values();

        // Terminals are a shared, independently-pairable registry, not one-per-event (see
        // App\Models\EftTerminal) — the console's own "EFT Terminal Settings" pane
        // @include's the exact same registry partial the standalone settings page and Admin
        // Settings do (see admin.partials.eft-terminal-registry), so pairing/adding a
        // terminal is implemented exactly once regardless of where it's reached from.
        $eftRegistryData = \App\Services\EftTerminalRegistryView::data();
        $eftTerminals = $eftRegistryData['eftTerminals'];
        $linklyMode = $eftRegistryData['linklyMode'];
        $cbaSciMode = $eftRegistryData['cbaSciMode'];
        $activeTerminals = $eftRegistryData['activeTerminals'];
        $inactiveTerminals = $eftRegistryData['inactiveTerminals'];
        $allOperational = $eftRegistryData['allOperational'];
        // Same admin tier as $canEditEvent — the registry partial's own Set Default/Remove
        // actions must stay literal-Admin-only regardless of who can reach this pane.
        $canManageRegistryLevel = \App\Services\EftTerminalAccess::canManageRegistry($user, $activeRole);
        $isSystemAdmin = $activeRole === 'Admin';

        // Cash Banking pane — same admin tier as Settings/Coordinators/EFTPOS/Logs above.
        $cashPreview = null;
        $cashBankings = collect();
        $cashSettlements = collect();
        if ($canEditEvent) {
            $cashPane = \App\Services\CashSettlementService::paneData('event', $event->event_id);
            $cashPreview = $cashPane['cashPreview'];
            $cashBankings = $cashPane['cashBankings'];
            $cashSettlements = $cashPane['cashSettlements'];
        }

        $temple = Setting::templeBranding();

        return view('admin.event-console', compact(
            'event',
            'options',
            'rows',
            'summary',
            'weeklyTrend',
            'monthlyTrend',
            'cashPreview',
            'cashBankings',
            'cashSettlements',
            'devotees',
            'enabledPaymentMethods',
            'globalPaymentMethods',
            'effectivePaymentMethods',
            'switchableEvents',
            'eventOptionsForJs',
            'canAddDonation',
            'canEditDonation',
            'canDeleteDonation',
            'canEditEvent',
            'canManageEventCoordinators',
            'eventCoordinators',
            'allUsersForCoordinators',
            'canViewEventLogs',
            'eventLogs',
            'activeRole',
            'temple',
            'linklyTransactions',
            'sciTransactions',
            'eftTransactions',
            'linklyPurchaseByDonation',
            'sciPurchaseByDonation',
            'eftOrphanRows',
            'eftTerminals',
            'activeTerminals',
            'inactiveTerminals',
            'allOperational',
            'canManageRegistryLevel',
            'isSystemAdmin',
            'linklyMode',
            'cbaSciMode'
        ));
    }
}
