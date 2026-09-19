<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Services\EventCoordinatorLevel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A deliberately minimal, kiosk-style "point of sale" donation entry screen — one event,
 * one job: take the next donation as fast as possible. Guest donations only (no devotee
 * lookup — every entry taken here is a walk-up donor). No dashboard, no donations table, no
 * settings; the only history shown is what this browser session itself has recorded, via
 * sessionStorage on the client (nothing server-rendered to keep the page itself as light and
 * fast as the workflow it supports).
 *
 * Reachable by Admin, by any role RolePermission grants 'donations' add to (Committee/
 * Accountant), or by an Event Coordinator at entry/admin/pos level for this specific event —
 * a 'view'-level coordinator can't record donations at all, so gets no access here either.
 * A pos-level coordinator's ENTIRE experience is this page (see AuthController::login() and
 * EventConsoleController::show()'s redirect) — everyone else can additionally reach the full
 * console.
 */
class PosDonationController extends Controller
{
    public function show($eventId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        $coordinatorLevel = $activeRole === 'Event Coordinator'
            ? EventCoordinatorLevel::of((int) $eventId, $user->id)
            : null;

        $canUsePos = $activeRole === 'Admin'
            || RolePermission::can($activeRole, 'donations', 'add')
            || EventCoordinatorLevel::atLeast($coordinatorLevel, 'entry');

        if (!$canUsePos) {
            abort(403, 'Unauthorized access.');
        }

        $event = Event::find($eventId);
        if (!$event) {
            abort(404, 'Event not found.');
        }

        $options = $event->donationOptions;
        $eventOptionsForJs = $options->map(fn ($o) => [
            'id' => $o->id,
            'label' => $o->label,
            'amount' => $o->amount === null ? null : (float) $o->amount,
            'allow_quantity' => (bool) $o->allow_quantity,
        ])->values();

        $enabledPaymentMethods = json_decode(Setting::get('enabled_payment_methods', '["Cash","Bank Transfer","Cheque"]'), true) ?: [];
        $globalPaymentMethods = $enabledPaymentMethods;
        if ((bool) Setting::get('stripe_enabled', true)) {
            $globalPaymentMethods[] = 'Stripe';
        }
        $effectivePaymentMethods = $event->paymentMethodsOverride() ?? $globalPaymentMethods;

        // A "Switch Event" list for a coordinator assigned to more than one event — Admin/
        // Committee see every event, matching the console's own topbar dropdown.
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

        // A pos-level coordinator has nowhere else to go at all — no console, no "All
        // Events" list — so the only exit this page offers them is Logout.
        $canReturnToConsole = !($activeRole === 'Event Coordinator' && $coordinatorLevel === 'pos');

        $temple = Setting::templeBranding();

        // Power Fail recovery (Core Payments accreditation 4.1.2) must survive worse than a
        // page refresh — the browser tab itself can be gone entirely (closed, crashed, a
        // different device even), taking sessionStorage's own copy of the in-flight attempt
        // with it. The server's own ledger is the only thing guaranteed to still know about
        // it: any non-terminal Purchase for this event, from anyone, recent enough to still
        // plausibly be sitting on the terminal right now. Passed to the view so the page can
        // resume polling its *actual* Linkly session directly — no client-side memory needed.
        $pendingEftRecovery = \App\Models\LinklyTransaction::where('event_id', $event->event_id)
            ->where('txn_type', 'purchase')
            ->whereNotIn('status', \App\Models\LinklyTransaction::TERMINAL_STATUSES)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->first();

        return view('admin.event-pos-donation', compact(
            'event',
            'eventOptionsForJs',
            'effectivePaymentMethods',
            'switchableEvents',
            'canReturnToConsole',
            'temple',
            'pendingEftRecovery'
        ));
    }
}
