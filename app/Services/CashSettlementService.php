<?php

namespace App\Services;

use App\Models\CashBanking;
use App\Models\CashSettlement;
use App\Models\RolePermission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cash reconciliation for one scope ('event' + event_id, or 'tickets' + null event_id).
 * Cash Paid donations/ticket sales accumulate as "cash on hand" until an admin logs a
 * CashBanking deposit; running a settlement locks a snapshot of opening/received/banked/
 * closing balances so the next settlement's opening is just this one's closing — no need
 * to ever recompute from the full transaction history.
 *
 * Per-option/per-ticket-type figures in the breakdown are informational only (how much
 * cash each category has raised) — the single overall closing_balance is the actionable
 * "remaining cash to bank" number, since a banking deposit is always a lump sum, never
 * split across categories.
 */
class CashSettlementService
{
    /**
     * Who can count the till and run a settlement — the resource's own 'edit' tier, plus
     * the 'entry' tier of either hierarchy (event_coordinators' pos/entry both rank here;
     * ticket_controllers has no separate pos tier, so its entry-level — the one actually
     * working the counter — lands here too). Deliberately the SAME bar both the controller
     * uses to accept a POST and the page controllers use to decide whether to render the
     * settlement widget at all, so the two can never drift apart.
     */
    public static function canSettle(string $scope, ?int $eventId, ?User $user, ?string $activeRole): bool
    {
        if (!$user) {
            return false;
        }
        if ($activeRole === 'Admin') {
            return true;
        }

        if ($scope === 'event') {
            if (RolePermission::can($activeRole, 'events', 'edit')) {
                return true;
            }
            $level = $activeRole === 'Event Coordinator' && $eventId ? EventCoordinatorLevel::of($eventId, $user->id) : null;
            return EventCoordinatorLevel::atLeast($level, 'entry');
        }

        if (RolePermission::can($activeRole, 'tickets', 'edit')) {
            return true;
        }
        $level = $activeRole === 'Ticket Controller' ? TicketControllerLevel::of($user->id) : null;
        return TicketControllerLevel::atLeast($level, 'entry');
    }

    /**
     * Who can see past settlements/bankings and edit them — everyone canSettle() allows,
     * MINUS a 'pos'-level event coordinator specifically: a pos-level user runs the till
     * blind to what happened in previous periods (see the class docblock on
     * CashSettlementController), they just count what's in front of them right now.
     * ticket_controllers has no pos tier to exclude, so its entry level gets history too —
     * it's the only "actually on the counter" tier that module has.
     */
    public static function canViewHistory(string $scope, ?int $eventId, ?User $user, ?string $activeRole): bool
    {
        if (!self::canSettle($scope, $eventId, $user, $activeRole)) {
            return false;
        }
        if ($scope === 'event' && $activeRole === 'Event Coordinator' && $eventId) {
            return EventCoordinatorLevel::of($eventId, $user->id) !== 'pos';
        }
        return true;
    }

    public static function latestSettlement(string $scope, ?int $eventId): ?CashSettlement
    {
        return CashSettlement::where('scope', $scope)->where('event_id', $eventId)->latest('id')->first();
    }

    /**
     * A past settlement's closing_balance feeds the NEXT one's opening_balance — editing
     * till_count/amount_banked on anything but the most recent settlement would silently
     * desync every settlement after it, so that's only ever allowed on the latest row. The
     * variance_note is always editable regardless (an annotation, never part of the chain).
     */
    public static function canEditFigures(CashSettlement $settlement): bool
    {
        $latest = self::latestSettlement($settlement->scope, $settlement->event_id);
        return $latest && $latest->id === $settlement->id;
    }

    public static function currentOpeningBalance(string $scope, ?int $eventId): float
    {
        $last = self::latestSettlement($scope, $eventId);
        return $last ? (float) $last->closing_balance : 0.0;
    }

    /**
     * When the last settlement was run — shown to a pos-level user in place of full history
     * (see canViewHistory()). Purely informational; period membership itself is decided by
     * claimedTransactionKeys(), never by comparing this timestamp against anything.
     */
    public static function lastSettledAt(string $scope, ?int $eventId): ?Carbon
    {
        $last = self::latestSettlement($scope, $eventId);
        return $last ? $last->created_at->copy() : null;
    }

    /**
     * Every {type, id} this scope's settlements have ever claimed, flattened into one
     * lookup set — "has this transaction already been counted in a past settlement" is the
     * one question every period-membership check in this class reduces to. type is
     * 'devotee'|'guest'|'ticket_order' (matching EventDonationBreakdown's own donation_type,
     * or 'ticket_order' for tickets scope) or 'banking' for the CashBanking deposit row a
     * settlement created alongside itself.
     *
     * Settled by exact id, not by comparing timestamps — see run()'s own docblock for why: a
     * plain `timestamp` column only has one-second resolution, nowhere near enough to order
     * two settlements run the same day, or a transaction recorded moments after one was
     * finalized (the donation_date/order_date business date doesn't help either, since
     * that's routinely backdated — "yesterday's" cash entered today still needs to land in
     * yesterday's settlement, which an id-exact snapshot does automatically since it's taken
     * at the moment the settlement is actually run, whatever date the transaction itself
     * carries).
     */
    private static function claimedTransactionKeys(string $scope, ?int $eventId): array
    {
        return array_fill_keys(array_keys(self::settlementKeyMap($scope, $eventId)), true);
    }

    /**
     * The same snapshot as claimedTransactionKeys(), but naming WHICH settlement claimed
     * each {type, id} (as "type:id" => settlement id) rather than just whether one did —
     * used by the Portal's All Transactions pane to tag every row with the settlement it
     * belongs to (or "current" when absent from this map), so a Settlement filter there can
     * work entirely client-side over the already-rendered rows.
     */
    public static function settlementKeyMap(string $scope, ?int $eventId): array
    {
        $map = [];
        CashSettlement::where('scope', $scope)->where('event_id', $eventId)->get(['id', 'transaction_refs'])
            ->each(function ($settlement) use (&$map) {
                foreach ((array) $settlement->transaction_refs as $ref) {
                    $map[$ref['type'] . ':' . $ref['id']] = $settlement->id;
                }
            });

        return $map;
    }

    /**
     * @return array{total: float, breakdown: array<int, array{label: string, cash_received: float}>}
     */
    public static function cashReceivedBreakdown(string $scope, ?int $eventId, array $claimedKeys): array
    {
        if ($scope === 'event') {
            return self::eventCashBreakdown((int) $eventId, $claimedKeys);
        }

        return self::ticketsCashBreakdown($claimedKeys);
    }

    /**
     * Partial Pledge Payments: every Completed `donation_payments` row belonging to this
     * event's own donations, not yet claimed at the PAYMENT level (separate from — and in
     * addition to — the existing donation-level claiming; see run()'s own note on why both
     * granularities are needed). This is what eventCashBreakdown()/paymentMethodBreakdown()
     * explode from instead of the donation row's own single flattened payment_method, so a
     * pledge paid across several methods/installments counts correctly no matter what its
     * own `donation_status` currently is.
     */
    private static function eventCompletedPayments(int $eventId, array $claimedKeys): \Illuminate\Support\Collection
    {
        $data = EventDonationBreakdown::forEvent($eventId);
        $donationsById = $data['rows']->keyBy(fn ($r) => $r->donation_type . ':' . $r->id);
        $devoteeIds = $data['rows']->where('donation_type', 'devotee')->pluck('id');
        $guestIds = $data['rows']->where('donation_type', 'guest')->pluck('id');

        return DB::table('donation_payments')
            ->where('payment_status', 'Completed')
            ->where(function ($q) use ($devoteeIds, $guestIds) {
                $q->where(fn ($q2) => $q2->where('donation_type', 'devotee')->whereIn('donation_id', $devoteeIds))
                    ->orWhere(fn ($q2) => $q2->where('donation_type', 'guest')->whereIn('donation_id', $guestIds));
            })
            ->get()
            ->filter(fn ($p) => !isset($claimedKeys['payment:' . $p->id]))
            ->map(function ($p) use ($donationsById) {
                $p->donation = $donationsById[$p->donation_type . ':' . $p->donation_id] ?? null;
                return $p;
            })
            ->filter(fn ($p) => $p->donation !== null)
            ->values();
    }

    /**
     * Splits a set of payments' amounts across this event's own donation options, scaled
     * per payment by (this payment's amount / its donation's full pledged amount) — reduces
     * to exactly today's figure when a donation's one payment covers its whole amount, and
     * gives a defensible best-effort split when a donation was paid in several
     * installments (matches this breakdown's own long-standing "informational only, not
     * the actionable number" framing — see the class docblock).
     */
    private static function explodeByOption(\Illuminate\Support\Collection $payments, \Illuminate\Support\Collection $options): array
    {
        $byOptionLabel = [];
        $otherTotal = 0.0;
        $total = 0.0;
        foreach ($payments as $p) {
            $donation = $p->donation;
            $amount = (float) $p->amount;
            $total += $amount;
            $scale = $donation->amount > 0 ? $amount / $donation->amount : 0;
            foreach ($options as $opt) {
                $optAmt = ($donation->option_amounts[$opt->id] ?? 0) * $scale;
                if ($optAmt > 0) {
                    $byOptionLabel[$opt->label] = ($byOptionLabel[$opt->label] ?? 0) + $optAmt;
                }
            }
            $otherTotal += ($donation->other_amount ?? 0) * $scale;
        }

        $breakdown = [];
        foreach ($options as $opt) {
            if (!empty($byOptionLabel[$opt->label])) {
                $breakdown[] = ['label' => $opt->label, 'cash_received' => round($byOptionLabel[$opt->label], 2)];
            }
        }
        if ($otherTotal > 0.0) {
            $breakdown[] = ['label' => 'Other', 'cash_received' => round($otherTotal, 2)];
        }

        return ['total' => round($total, 2), 'breakdown' => $breakdown];
    }

    private static function eventCashBreakdown(int $eventId, array $claimedKeys): array
    {
        $options = EventDonationBreakdown::forEvent($eventId)['options'];
        $cashPayments = self::eventCompletedPayments($eventId, $claimedKeys)
            ->filter(fn ($p) => $p->payment_method === 'Cash');

        return self::explodeByOption($cashPayments, $options);
    }

    /**
     * All payment methods (not just Cash) received this period — what the Settlement
     * Summary's "Received by Payment Type" tiles use, so a split-method pledge shows its
     * real per-method amounts instead of being lumped under its parent's 'Split' mirror.
     * Event scope only — tickets scope has no pledge/ledger concept, so callers keep using
     * `$currentTransactions` directly for that scope exactly as before.
     */
    public static function paymentMethodBreakdown(string $scope, ?int $eventId): array
    {
        if ($scope !== 'event' || !$eventId) {
            return ['total' => 0.0, 'by_method' => []];
        }

        $claimedKeys = self::claimedTransactionKeys($scope, $eventId);
        $payments = self::eventCompletedPayments($eventId, $claimedKeys);
        $byMethod = $payments->groupBy(fn ($p) => $p->payment_method ?: 'Unknown')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
            ->sortDesc();

        return ['total' => round((float) $byMethod->sum(), 2), 'by_method' => $byMethod->all()];
    }

    private static function ticketsCashBreakdown(array $claimedKeys): array
    {
        $items = DB::table('ticket_order_items')
            ->join('ticket_orders', 'ticket_orders.id', '=', 'ticket_order_items.ticket_order_id')
            ->where('ticket_orders.payment_method', 'Cash')
            ->where('ticket_orders.payment_status', 'Paid')
            ->select('ticket_order_items.ticket_name', 'ticket_order_items.line_total', 'ticket_orders.id as order_id')
            ->get()
            ->filter(fn ($r) => !isset($claimedKeys['ticket_order:' . $r->order_id]));

        $grouped = $items->groupBy('ticket_name')->map(fn ($rows) => round((float) $rows->sum('line_total'), 2));

        $breakdown = $grouped->map(fn ($amt, $label) => ['label' => $label, 'cash_received' => $amt])->values()->all();
        $total = round((float) $grouped->sum(), 2);

        return ['total' => $total, 'breakdown' => $breakdown];
    }

    /**
     * The individual Paid/Pending/Partially Paid transactions NOT YET claimed by any
     * settlement — any payment method (Cancelled/Failed never show). Event scope returns
     * devotee + guest donation rows (normalized by EventDonationBreakdown, same shape the
     * console's own All Transactions pane uses, so the exact same edit routes — admin.
     * donations.updateDevotee/updateGuest — work unchanged here); tickets scope returns
     * ticket_orders rows (no edit route exists for those yet, so the pane only ever shows
     * them read-only). The till-count figures themselves (cashReceivedBreakdown()/
     * preview()) stay Cash-only and are computed separately — this is for review.
     *
     * Partial Pledge Payments: 'Partially Paid' included so a pledge still being paid off
     * in installments is finally visible here at all (it was silently excluded before —
     * not just miscounted) — but a Partially Paid row is never actually claimed when a
     * settlement runs (see run()'s own note), so it correctly keeps reappearing every
     * period for as long as it still has an outstanding balance.
     */
    public static function currentPeriodTransactions(string $scope, ?int $eventId): \Illuminate\Support\Collection
    {
        $claimedKeys = self::claimedTransactionKeys($scope, $eventId);

        if ($scope === 'event') {
            $data = EventDonationBreakdown::forEvent((int) $eventId);
            return $data['rows']
                ->filter(fn ($r) => in_array($r->payment_status, ['Paid', 'Pending', 'Partially Paid'], true))
                ->filter(fn ($r) => !isset($claimedKeys[$r->donation_type . ':' . $r->id]))
                ->values();
        }

        $orders = collect(DB::table('ticket_orders')->where('payment_method', 'Cash')->where('payment_status', 'Paid')->orderByDesc('created_at')->get())
            ->filter(fn ($r) => !isset($claimedKeys['ticket_order:' . $r->id]))
            ->values();

        // Batched (not one query per row) — attached so the order-view popup can list
        // what was actually bought without a separate AJAX call; everything else reading
        // this collection already ignores unknown properties.
        $itemsByOrder = DB::table('ticket_order_items')
            ->whereIn('ticket_order_id', $orders->pluck('id'))
            ->orderBy('id')
            ->get(['ticket_order_id', 'ticket_name', 'quantity', 'unit_price', 'line_total'])
            ->groupBy('ticket_order_id');

        return $orders->map(function ($order) use ($itemsByOrder) {
            $order->items = $itemsByOrder->get($order->id, collect())->values();
            return $order;
        });
    }

    /**
     * The exact transactions one already-locked settlement claimed at the moment it was run
     * — only ever reachable by someone canViewHistory() already lets see past settlements
     * at all; never editable.
     */
    public static function transactionsForSettlement(CashSettlement $settlement): \Illuminate\Support\Collection
    {
        $wanted = collect($settlement->transaction_refs ?? [])
            ->reject(fn ($ref) => $ref['type'] === 'banking')
            ->map(fn ($ref) => $ref['type'] . ':' . $ref['id'])
            ->flip();

        if ($settlement->scope === 'event') {
            $data = EventDonationBreakdown::forEvent((int) $settlement->event_id);
            return $data['rows']->filter(fn ($r) => $wanted->has($r->donation_type . ':' . $r->id))->values();
        }

        $ids = collect($settlement->transaction_refs ?? [])->where('type', 'ticket_order')->pluck('id');
        return collect(DB::table('ticket_orders')->whereIn('id', $ids)->orderByDesc('created_at')->get());
    }

    public static function bankedInPeriod(string $scope, ?int $eventId, array $claimedKeys): float
    {
        return (float) CashBanking::where('scope', $scope)->where('event_id', $eventId)->get(['id', 'amount'])
            ->filter(fn ($b) => !isset($claimedKeys['banking:' . $b->id]))
            ->sum('amount');
    }

    /**
     * Not-yet-saved preview of the next settlement's figures — used both to render the
     * live "Cash Banking" pane and as the basis for run() when the admin confirms it.
     */
    public static function preview(string $scope, ?int $eventId, ?Carbon $periodEnd = null): array
    {
        $periodEnd = $periodEnd ?? now();
        $claimedKeys = self::claimedTransactionKeys($scope, $eventId);

        // Cosmetic only (what gets stored/displayed as this settlement's period_start) —
        // the actual figures below come from claimedTransactionKeys(), not from this date.
        $last = self::latestSettlement($scope, $eventId);
        $lastEndDate = $last ? Carbon::parse($last->period_end) : null;
        $displayPeriodStart = $lastEndDate ? $lastEndDate->copy()->addDay() : Carbon::createFromDate(2000, 1, 1);
        if ($displayPeriodStart->gt($periodEnd)) {
            $displayPeriodStart = $periodEnd->copy();
        }

        $opening = self::currentOpeningBalance($scope, $eventId);
        $received = self::cashReceivedBreakdown($scope, $eventId, $claimedKeys);
        $banked = self::bankedInPeriod($scope, $eventId, $claimedKeys);
        $closing = round($opening + $received['total'] - $banked, 2);

        return [
            'scope' => $scope,
            'event_id' => $eventId,
            'period_start' => $displayPeriodStart,
            'period_end' => $periodEnd,
            'opening_balance' => $opening,
            'cash_received' => $received['total'],
            'amount_banked' => round($banked, 2),
            'closing_balance' => $closing,
            'breakdown' => $received['breakdown'],
        ];
    }

    /**
     * Everything an Event Console / Ticket Console pane (or a POS page's Settle Till
     * widget) needs to render — called from EventConsoleController::show() /
     * TicketController::manageTickets()/posShow() / PosDonationController::show() and
     * merged into their existing view data. $includeHistory is false for a pos-level
     * event coordinator (see canViewHistory()) — past bankings/settlements are simply
     * never fetched for them, not just hidden in the view, so there's no chance of a
     * template change accidentally leaking them. currentTransactions here is ALWAYS the
     * live, not-yet-settled period — the Settle Till widget only ever works the till in
     * front of it right now; browsing past settlements' own transactions by filter lives on
     * the Portal's All Transactions pane instead (EventConsoleController::show()), not here.
     */
    public static function paneData(string $scope, ?int $eventId, bool $includeHistory = true): array
    {
        return [
            'cashPreview' => self::preview($scope, $eventId),
            'currentTransactions' => self::currentPeriodTransactions($scope, $eventId),
            // Not gated by $includeHistory — this is a single fact (when was it last
            // settled, how much carried forward), not the settlement/banking history
            // itself, and a pos-level user is allowed to know it (see the pane's own
            // fallback message for a user who can't see full history).
            'lastSettlementDate' => self::lastSettledAt($scope, $eventId),
            'cashBankings' => $includeHistory
                ? CashBanking::where('scope', $scope)->where('event_id', $eventId)->with('recordedBy')->latest('created_at')->limit(50)->get()
                : collect(),
            'cashSettlements' => $includeHistory
                ? CashSettlement::where('scope', $scope)->where('event_id', $eventId)->with('performedBy')->latest('created_at')->limit(50)->get()
                : collect(),
        ];
    }

    /**
     * Locks in a settlement from the operator's own physical count — till_count is what
     * they counted in the till just now, amount_banked is how much of that they're taking
     * to the bank. closing_balance (what carries forward as the next period's opening) is
     * till_count - amount_banked: the ACTUAL remainder, by count, not the theoretical
     * opening + received - banked figure — so a shortage or overage is absorbed into this
     * settlement's variance rather than silently compounding into every period after it.
     */
    public static function run(string $scope, ?int $eventId, int $performedBy, float $tillCount, float $amountBanked, ?string $varianceNote, ?Carbon $periodEnd = null): CashSettlement
    {
        $data = self::preview($scope, $eventId, $periodEnd);
        $expected = round($data['opening_balance'] + $data['cash_received'], 2);
        $variance = round($tillCount - $expected, 2);
        $closing = round($tillCount - $amountBanked, 2);

        // Snapshot — by exact id, taken right now — of every transaction and (if any)
        // not-yet-claimed banking deposit this settlement is locking in, so later lookups
        // (transactionsForSettlement(), the next currentPeriodTransactions() call) never
        // need to re-derive "was this in this settlement's period" from a date or a
        // `timestamp` column's one-second resolution — see claimedTransactionKeys()'s own
        // docblock for why that's unreliable.
        //
        // Partial Pledge Payments: a Partially Paid donation is deliberately NEVER given a
        // donation-level ref — it must keep reappearing in every future
        // currentPeriodTransactions() review until it's actually resolved, not vanish
        // after its first installment. Its Completed payment(s) still get their own
        // PAYMENT-level ref below, so the money itself is never double-counted even though
        // the donation as a whole stays "open."
        $claimedKeys = self::claimedTransactionKeys($scope, $eventId);
        $refs = self::currentPeriodTransactions($scope, $eventId)
            ->filter(fn ($r) => $scope !== 'event' || ($r->donation_status ?? null) !== 'Partially Paid')
            ->map(fn ($r) => ['type' => $scope === 'event' ? $r->donation_type : 'ticket_order', 'id' => $r->id])
            ->values()->all();
        $unclaimedBankingIds = CashBanking::where('scope', $scope)->where('event_id', $eventId)->get(['id'])
            ->filter(fn ($b) => !isset($claimedKeys['banking:' . $b->id]))
            ->pluck('id');
        foreach ($unclaimedBankingIds as $bankingId) {
            $refs[] = ['type' => 'banking', 'id' => $bankingId];
        }
        // Payment-level refs — every Completed payment (any method) for this event not yet
        // claimed at the payment level. This is what eventCashBreakdown()/
        // paymentMethodBreakdown() check against next time, so a Cash (or any-method)
        // installment is counted exactly once, ever, independent of whether its donation
        // is fully resolved. No equivalent for tickets scope (no ledger concept there).
        if ($scope === 'event' && $eventId) {
            foreach (self::eventCompletedPayments((int) $eventId, $claimedKeys) as $payment) {
                $refs[] = ['type' => 'payment', 'id' => $payment->id];
            }
        }

        return DB::transaction(function () use ($data, $performedBy, $tillCount, $amountBanked, $variance, $closing, $varianceNote, $scope, $eventId, $refs) {
            if ($amountBanked > 0) {
                $banking = CashBanking::create([
                    'scope' => $scope,
                    'event_id' => $eventId,
                    'amount' => $amountBanked,
                    'banked_date' => $data['period_end']->toDateString(),
                    'note' => 'Recorded as part of a till settlement',
                    'recorded_by' => $performedBy,
                ]);
                $refs[] = ['type' => 'banking', 'id' => $banking->id];
            }

            $settlement = CashSettlement::create([
                'scope' => $data['scope'],
                'event_id' => $data['event_id'],
                'period_start' => $data['period_start']->toDateString(),
                'period_end' => $data['period_end']->toDateString(),
                'opening_balance' => $data['opening_balance'],
                'cash_received' => $data['cash_received'],
                'amount_banked' => $amountBanked,
                'till_count' => $tillCount,
                'closing_balance' => $closing,
                'variance' => $variance,
                'variance_note' => $varianceNote,
                'breakdown' => $data['breakdown'],
                'performed_by' => $performedBy,
                'transaction_refs' => $refs,
            ]);

            $label = $data['scope'] === 'event' ? "event #{$data['event_id']}" : 'tickets';
            $varianceText = abs($variance) >= 0.01 ? ", variance {$variance}" : '';
            AuditLogService::log(
                "Ran cash settlement for {$label}: till counted {$tillCount}, banked {$amountBanked}, carried forward {$closing}{$varianceText}",
                $performedBy,
                $data['event_id']
            );

            return $settlement;
        });
    }

    /**
     * Updates an existing settlement. variance_note is always editable (a note never
     * affects the ledger chain); till_count/amount_banked only when canEditFigures() allows
     * it — the controller re-checks that before calling this, this is just the write.
     */
    public static function update(CashSettlement $settlement, array $fields, int $performedBy): CashSettlement
    {
        if (array_key_exists('till_count', $fields) || array_key_exists('amount_banked', $fields)) {
            $tillCount = (float) ($fields['till_count'] ?? $settlement->till_count);
            $amountBanked = (float) ($fields['amount_banked'] ?? $settlement->amount_banked);
            $expected = round((float) $settlement->opening_balance + (float) $settlement->cash_received, 2);
            $settlement->till_count = $tillCount;
            $settlement->amount_banked = $amountBanked;
            $settlement->variance = round($tillCount - $expected, 2);
            $settlement->closing_balance = round($tillCount - $amountBanked, 2);
        }
        if (array_key_exists('variance_note', $fields)) {
            $settlement->variance_note = $fields['variance_note'];
        }
        $settlement->save();

        $label = $settlement->scope === 'event' ? "event #{$settlement->event_id}" : 'tickets';
        AuditLogService::log("Updated cash settlement #{$settlement->id} for {$label}", $performedBy, $settlement->event_id);

        return $settlement;
    }
}
