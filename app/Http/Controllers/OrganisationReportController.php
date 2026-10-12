<?php

namespace App\Http\Controllers;

use App\Models\LinklyTransaction;
use App\Models\SciTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2C: the one gap in the existing reporting surface — donations
 * (DonationController::manageDonations()) and ticket orders (TicketController::
 * manageTickets()) are already organisation-wide (no event_id scoping at all); the EFT/SCI
 * transaction LEDGER, however, only ever existed per-event (EventConsoleController) or
 * tickets-only (event_id IS NULL, inside TicketController) — nowhere combined across
 * every event. This page is exactly that: the same purchase/refund query
 * EventConsoleController already runs, just without the event_id filter, merged the same
 * Linkly+SCI way and given its own event/date/provider/operator filters.
 *
 * Deliberately not a rebuild of the donations/tickets/audit-log pages themselves — this
 * page links out to those (see the view) rather than re-implementing what already works.
 */
class OrganisationReportController extends Controller
{
    private function isAdmin(): bool
    {
        $user = Auth::user();
        return $user && $user->role === 'Admin';
    }

    public function transactions(Request $request)
    {
        if (!$this->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $eventFilter = $request->get('event_id'); // numeric id, 'tickets', or blank for all
        $providerFilter = $request->get('provider'); // 'linkly' | 'cba_sci' | blank
        $operatorFilter = $request->get('operator_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $applyCommonFilters = function ($query) use ($eventFilter, $operatorFilter, $dateFrom, $dateTo) {
            if ($eventFilter === 'tickets') {
                $query->whereNull('event_id');
            } elseif ($eventFilter) {
                $query->where('event_id', (int) $eventFilter);
            }
            if ($operatorFilter) {
                $query->where('initiated_by', (int) $operatorFilter);
            }
            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }
            return $query;
        };

        $linklyTransactions = collect();
        if (!$providerFilter || $providerFilter === 'linkly') {
            $linklyTransactions = $applyCommonFilters(
                LinklyTransaction::with(['eftTerminal', 'initiator', 'event'])->whereIn('txn_type', ['purchase', 'refund'])
            )->orderBy('created_at', 'desc')->limit(300)->get();
        }

        $sciTransactions = collect();
        if (!$providerFilter || $providerFilter === 'cba_sci') {
            $sciTransactions = $applyCommonFilters(
                SciTransaction::with(['eftTerminal', 'initiator', 'event'])
            )->orderBy('created_at', 'desc')->limit(300)->get();
        }

        $transactions = $linklyTransactions->map(fn ($t) => (object) ['provider' => 'linkly', 'txn' => $t, 'created_at' => $t->created_at])
            ->concat($sciTransactions->map(fn ($t) => (object) ['provider' => 'cba_sci', 'txn' => $t, 'created_at' => $t->created_at]))
            ->sortByDesc('created_at')
            ->take(300)
            ->values();

        $events = DB::table('events')->orderByDesc('event_date')->select('event_id', 'event_name')->get();

        // Only users who've actually initiated a transaction somewhere — not every system
        // user — so the filter dropdown stays short and relevant.
        $operators = DB::table('users')
            ->whereIn('id', LinklyTransaction::whereNotNull('initiated_by')->distinct()->pluck('initiated_by')
                ->merge(SciTransaction::whereNotNull('initiated_by')->distinct()->pluck('initiated_by')))
            ->orderBy('name')->select('id', 'name')->get();

        return view('admin.organisation-transactions', compact(
            'transactions', 'events', 'operators', 'eventFilter', 'providerFilter', 'operatorFilter', 'dateFrom', 'dateTo'
        ));
    }
}
