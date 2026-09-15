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
        $summary = [
            'paid_total' => $paidRows->sum('amount'),
            'paid_count' => $paidRows->count(),
            'pending_total' => $pendingRows->sum('amount'),
            'pending_count' => $pendingRows->count(),
            'donation_count' => $rows->count(),
            'option_totals' => $options->mapWithKeys(fn ($opt) => [
                $opt->id => $paidRows->sum(fn ($r) => $r->option_amounts[$opt->id] ?? 0),
            ]),
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
            'temple'
        ));
    }
}
