<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Models\Setting;
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

        $isCoordinatorForEvent = $activeRole === 'Event Coordinator'
            && DB::table('event_coordinators')->where('user_id', $user->id)->where('event_id', $eventId)->exists();

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

        $eventOptionsForJs = $options->map(fn ($o) => [
            'id' => $o->id,
            'label' => $o->label,
            'amount' => $o->amount === null ? null : (float) $o->amount,
            'allow_quantity' => (bool) $o->allow_quantity,
        ])->values();

        $canAddDonation = $activeRole === 'Admin' || RolePermission::can($activeRole, 'donations', 'add') || $isCoordinatorForEvent;
        $canEditDonation = $activeRole === 'Admin' || RolePermission::can($activeRole, 'donations', 'edit') || $isCoordinatorForEvent;
        $canDeleteDonation = $activeRole === 'Admin' || RolePermission::can($activeRole, 'donations', 'delete');
        // Event settings (donation options, contacts, gallery, status, etc.) are edited via
        // the same Edit Event modal used on Manage Events — a bigger capability than just
        // recording donations, so a plain Event Coordinator doesn't get this link.
        $canEditEvent = $activeRole === 'Admin' || RolePermission::can($activeRole, 'events', 'edit');

        $temple = Setting::templeBranding();

        return view('admin.event-console', compact(
            'event',
            'options',
            'rows',
            'summary',
            'devotees',
            'enabledPaymentMethods',
            'eventOptionsForJs',
            'canAddDonation',
            'canEditDonation',
            'canDeleteDonation',
            'canEditEvent',
            'temple'
        ));
    }
}
