<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Models\Setting;
use App\Services\EventCoordinatorLevel;
use App\Services\EventDonationBreakdown;
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
                ->select('users.id', 'users.name', 'users.email', 'users.status', 'users.last_login_at', 'users.last_reset_email_sent_at', 'event_coordinators.level')
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

        $temple = Setting::templeBranding();

        return view('admin.event-console', compact(
            'event',
            'options',
            'rows',
            'summary',
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
            'temple'
        ));
    }
}
