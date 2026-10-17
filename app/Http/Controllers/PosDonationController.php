<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Services\EventCoordinatorLevel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A deliberately minimal, counter-style "point of sale" donation entry screen — one event,
 * one job: take the next donation as fast as possible. Every entry taken here is still a
 * walk-up guest donation (no login, no linked devotee record) — searchDevotee() below only
 * prefills the form from a past record's own name/email/mobile, it never looks up or writes
 * to an actual account. No dashboard, no donations table, no settings; the only history
 * shown is what this browser session itself has recorded, via sessionStorage on the client
 * (nothing server-rendered to keep the page itself as light and fast as the workflow it
 * supports).
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
    /**
     * Same bar as the POS page itself — shared so the search endpoint can never end up
     * reachable by someone who couldn't open the page it's embedded in.
     */
    private function canUsePos(\App\Models\User $user, ?string $activeRole, $eventId): bool
    {
        $coordinatorLevel = $activeRole === 'Event Coordinator'
            ? EventCoordinatorLevel::of((int) $eventId, $user->id)
            : null;

        return $activeRole === 'Admin'
            || RolePermission::can($activeRole, 'donations', 'add')
            || EventCoordinatorLevel::atLeast($coordinatorLevel, 'entry');
    }

    public function show(\Illuminate\Http\Request $request, $eventId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        $coordinatorLevel = $activeRole === 'Event Coordinator'
            ? EventCoordinatorLevel::of((int) $eventId, $user->id)
            : null;

        if (!$this->canUsePos($user, $activeRole, $eventId)) {
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

        // The "Manage PIN" topbar icon only makes sense for an account the POS PIN feature
        // actually applies to — showing it to everyone else would just be a dead-end 403.
        $canManagePosPin = (bool) app(AuthController::class)->possiblePosDestinations($user);

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

        // Every currently PAIRED terminal — the operator picks which one they're using (saved
        // both to this browser and against their own account, see event-pos-donation.blade.
        // php's terminal picker and EftTerminalController::selectForMe()), so two stations on
        // two different terminals can each run this same event's POS concurrently, or one on
        // this event and one on the Ticket POS. An unpaired
        // terminal can't take a payment at all, so it's left off this list entirely rather
        // than offered as a selectable-but-broken option (it still shows on the EFT
        // Terminal Settings page, grouped under "Inactive").
        $linklyMode = \App\Services\LinklyConfigService::mode();
        // Register-aware: a paired register's own configured terminal for THIS event wins
        // first (a station's hardware fact, see EftTerminal::defaultForContext()), falling
        // back to the per-operator default (EftTerminal::default()) when nothing's
        // configured for this register/event pair, or no register is paired at all.
        $register = $request->attributes->get('register');
        $myDefaultTerminalId = \App\Models\EftTerminal::defaultForContext($register, 'event', (int) $eventId)?->id;
        $eftTerminalsForJs = \App\Models\EftTerminal::orderByDesc('is_default')->orderBy('label')->get()
            ->filter(fn ($t) => $t->isPairedFor($linklyMode))
            ->map(function ($t) use ($linklyMode, $myDefaultTerminalId) {
                $status = $t->lastKnownStatus();
                return [
                    'id' => $t->id,
                    'label' => $t->label,
                    'provider' => $t->provider,
                    'is_default' => $t->id === $myDefaultTerminalId,
                    'paired' => $t->isPairedFor($linklyMode),
                    'status' => $status['state'],
                    'status_at' => $status['at']?->diffForHumans(),
                ];
            })
            ->values();

        $eftMinimumAmount = \App\Services\EftTransactionLimits::minimumAmount();

        // Settle Till widget — a pos-level coordinator's only way to ever reach this at
        // all (they never see the console); entry/admin see the same widget here too if
        // they're using the POS page, just with full history (see CashSettlementService::
        // canSettle()/canViewHistory()).
        $canSettleCash = \App\Services\CashSettlementService::canSettle('event', $event->event_id, $user, $activeRole);
        $canViewCashHistory = \App\Services\CashSettlementService::canViewHistory('event', $event->event_id, $user, $activeRole);
        $cashPreview = null;
        $cashBankings = collect();
        $cashSettlements = collect();
        $currentTransactions = collect();
        $lastSettlementDate = null;
        if ($canSettleCash) {
            $cashPane = \App\Services\CashSettlementService::paneData('event', $event->event_id, $canViewCashHistory);
            $cashPreview = $cashPane['cashPreview'];
            $cashBankings = $cashPane['cashBankings'];
            $cashSettlements = $cashPane['cashSettlements'];
            $currentTransactions = $cashPane['currentTransactions'];
            $lastSettlementDate = $cashPane['lastSettlementDate'];
        }
        // Same bar EventConsoleController's own $canEditDonation uses — a pos-level
        // coordinator already qualifies (EventCoordinatorLevel ranks pos alongside entry),
        // they just never had a page to reach it from before this widget existed.
        $canEditCurrentTransactions = $activeRole === 'Admin'
            || RolePermission::can($activeRole, 'donations', 'edit')
            || EventCoordinatorLevel::atLeast($coordinatorLevel, 'entry');

        // Donation Summary card's "Pending Payments" button — fetched independently of
        // $canSettleCash (which $currentTransactions above is gated by) so it's still
        // correct for the rare role that can edit donations without also being able to
        // settle cash. Guest donations only, same as the edit_id completion flow below —
        // a Pending devotee donation has no "complete in POS" action to offer here.
        $pendingPayments = $canEditCurrentTransactions
            ? \App\Services\CashSettlementService::currentPeriodTransactions('event', $event->event_id)
                ->filter(fn ($r) => $r->payment_status === 'Pending' && $r->donation_type === 'guest')
                ->values()
            : collect();

        // "Complete this pending transaction" — reached from the Current Period
        // Transactions table's edit link (?edit_id=), never typed by hand, but
        // re-verified server-side rather than trusted from the query string: must still
        // be $canEditCurrentTransactions, must belong to THIS event, and must still be
        // Pending (a guest donation only — the POS page has no devotee lookup at all, see
        // this controller's own docblock, so a devotee donation's edit_id is just ignored).
        $editDonation = null;
        if ($canEditCurrentTransactions && $request->filled('edit_id')) {
            $editDonation = DB::table('donations_without_logins')
                ->where('id', (int) $request->input('edit_id'))
                ->where('event_id', $event->event_id)
                ->where('payment_status', 'Pending')
                ->first();
        }

        return view('admin.event-pos-donation', compact(
            'event',
            'eventOptionsForJs',
            'effectivePaymentMethods',
            'switchableEvents',
            'canReturnToConsole',
            'temple',
            'pendingEftRecovery',
            'eftTerminalsForJs',
            'canSettleCash',
            'canViewCashHistory',
            'cashPreview',
            'cashBankings',
            'cashSettlements',
            'currentTransactions',
            'lastSettlementDate',
            'canEditCurrentTransactions',
            'pendingPayments',
            'editDonation',
            'canManagePosPin',
            'eftMinimumAmount'
        ));
    }

    /**
     * "Find Existing Devotee" — prefills the Donor Details form from either a real
     * registered devotee (users+devotees) or a past non-anonymous guest donation that
     * carried a real name and email (the minimum this feature treats as "identifiable" —
     * anonymous donations and the "well wisher" placeholder the anonymous-no-details flow
     * writes are excluded entirely, never shown here).
     *
     * Deliberately read-only and purely client-side afterwards: selecting a result just
     * copies its name/email/mobile into the current form fields (see the Blade view's own
     * JS). It never links this donation to the matched record or touches it in any way, so
     * an older transaction's own donor_name/email/mobile is never altered by a later
     * donation reusing a similar-looking name.
     */
    public function searchDevotee(\Illuminate\Http\Request $request, $eventId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        if (!$this->canUsePos($user, $activeRole, $eventId)) {
            abort(403, 'Unauthorized access.');
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $devotees = DB::table('devotees')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->whereNotNull('users.email')
            ->where(function ($w) use ($like) {
                $w->where('users.name', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('users.mobile', 'like', $like);
            })
            ->orderBy('users.name')
            ->limit(10)
            ->get(['devotees.devotee_id', 'users.name', 'users.email', 'users.mobile'])
            ->map(fn ($d) => (object) [
                'ref' => 'devotee-' . $d->devotee_id,
                'name' => $d->name,
                'email' => $d->email,
                'mobile' => $d->mobile,
            ]);

        $guests = DB::table('donations_without_logins')
            ->where('is_anonymous', false)
            ->whereNotNull('donor_name')
            ->whereNotNull('email')
            ->where('donor_name', '!=', 'The well wisher')
            ->where('email', '!=', 'admin@hasq.org')
            ->where(function ($w) use ($like) {
                $w->where('donor_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            })
            ->selectRaw('MAX(id) as id, donor_name, email, mobile')
            ->groupBy('donor_name', 'email', 'mobile')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($g) => (object) [
                'ref' => 'guest-' . $g->id,
                'name' => $g->donor_name,
                'email' => $g->email,
                'mobile' => $g->mobile,
            ]);

        // Registered devotees win a dedupe tie (more authoritative than a one-off guest
        // entry) when the same email shows up on both sides.
        $results = $devotees->concat($guests)
            ->unique(fn ($r) => strtolower($r->email))
            ->take(10)
            ->values()
            ->map(fn ($r) => [
                'ref' => $r->ref,
                'name' => $r->name,
                'email' => $r->email,
                'mobile' => $r->mobile,
                'masked_email' => $this->maskEmail($r->email),
                'masked_mobile' => $r->mobile ? $this->maskMobile($r->mobile) : null,
                'has_mobile' => (bool) $r->mobile,
            ]);

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * "sa****@example.com" — enough to recognise your own search match, not enough to read
     * over someone's shoulder at a counter.
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        $local = $parts[0];
        $domain = $parts[1] ?? '';
        $visible = mb_substr($local, 0, 2);
        $maskLength = max(2, mb_strlen($local) - mb_strlen($visible));

        return $visible . str_repeat('•', $maskLength) . ($domain !== '' ? '@' . $domain : '');
    }

    /** "•••• ••678" — same idea as maskEmail(), last 3 digits only. */
    private function maskMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if ($digits === '') {
            return $mobile;
        }

        return '•••• ••' . mb_substr($digits, -3);
    }
}
