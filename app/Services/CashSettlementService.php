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
        return CashSettlement::where('scope', $scope)->where('event_id', $eventId)->latest('period_end')->latest('id')->first();
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
        $last = CashSettlement::where('scope', $scope)->where('event_id', $eventId)->latest('period_end')->first();
        return $last ? (float) $last->closing_balance : 0.0;
    }

    public static function lastPeriodEnd(string $scope, ?int $eventId): ?Carbon
    {
        $last = CashSettlement::where('scope', $scope)->where('event_id', $eventId)->latest('period_end')->first();
        return $last ? Carbon::parse($last->period_end) : null;
    }

    /**
     * @return array{total: float, breakdown: array<int, array{label: string, cash_received: float}>}
     */
    public static function cashReceivedBreakdown(string $scope, ?int $eventId, Carbon $from, Carbon $to): array
    {
        if ($scope === 'event') {
            return self::eventCashBreakdown((int) $eventId, $from, $to);
        }

        return self::ticketsCashBreakdown($from, $to);
    }

    private static function eventCashBreakdown(int $eventId, Carbon $from, Carbon $to): array
    {
        $data = EventDonationBreakdown::forEvent($eventId);
        $options = $data['options'];
        $cashRows = $data['rows']
            ->filter(fn ($r) => $r->payment_method === 'Cash' && $r->payment_status === 'Paid')
            ->filter(fn ($r) => Carbon::parse($r->donation_date)->between($from, $to));

        $breakdown = [];
        $total = 0.0;
        foreach ($options as $opt) {
            $amt = (float) $cashRows->sum(fn ($r) => $r->option_amounts[$opt->id] ?? 0);
            if ($amt > 0) {
                $breakdown[] = ['label' => $opt->label, 'cash_received' => round($amt, 2)];
            }
            $total += $amt;
        }
        $otherAmt = (float) $cashRows->sum('other_amount');
        if ($otherAmt > 0) {
            $breakdown[] = ['label' => 'Other', 'cash_received' => round($otherAmt, 2)];
        }
        $total += $otherAmt;

        return ['total' => round($total, 2), 'breakdown' => $breakdown];
    }

    private static function ticketsCashBreakdown(Carbon $from, Carbon $to): array
    {
        $items = DB::table('ticket_order_items')
            ->join('ticket_orders', 'ticket_orders.id', '=', 'ticket_order_items.ticket_order_id')
            ->where('ticket_orders.payment_method', 'Cash')
            ->where('ticket_orders.payment_status', 'Paid')
            ->whereBetween('ticket_orders.order_date', [$from->toDateString(), $to->toDateString()])
            ->select('ticket_order_items.ticket_name', 'ticket_order_items.line_total')
            ->get();

        $grouped = $items->groupBy('ticket_name')->map(fn ($rows) => round((float) $rows->sum('line_total'), 2));

        $breakdown = $grouped->map(fn ($amt, $label) => ['label' => $label, 'cash_received' => $amt])->values()->all();
        $total = round((float) $grouped->sum(), 2);

        return ['total' => $total, 'breakdown' => $breakdown];
    }

    public static function bankedInPeriod(string $scope, ?int $eventId, Carbon $from, Carbon $to): float
    {
        // The 'date' cast on CashBanking::banked_date round-trips through Carbon, which
        // stores it with a "00:00:00" time component — comparing against bare "Y-m-d"
        // strings would lexicographically exclude same-day rows (the longer, timed string
        // sorts after the bare date), so the bounds need the same full-datetime shape.
        return (float) CashBanking::where('scope', $scope)
            ->where('event_id', $eventId)
            ->whereBetween('banked_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('amount');
    }

    /**
     * Not-yet-saved preview of the next settlement's figures — used both to render the
     * live "Cash Banking" pane and as the basis for run() when the admin confirms it.
     */
    public static function preview(string $scope, ?int $eventId, ?Carbon $periodEnd = null): array
    {
        $periodEnd = $periodEnd ?? now();
        $lastEnd = self::lastPeriodEnd($scope, $eventId);
        // First-ever settlement for this scope starts from the very beginning of time
        // rather than requiring a manual "first period" date.
        $periodStart = $lastEnd ? $lastEnd->copy()->addDay() : Carbon::createFromDate(2000, 1, 1);
        if ($periodStart->gt($periodEnd)) {
            $periodStart = $periodEnd->copy();
        }

        $opening = self::currentOpeningBalance($scope, $eventId);
        $received = self::cashReceivedBreakdown($scope, $eventId, $periodStart, $periodEnd);
        $banked = self::bankedInPeriod($scope, $eventId, $periodStart, $periodEnd);
        $closing = round($opening + $received['total'] - $banked, 2);

        return [
            'scope' => $scope,
            'event_id' => $eventId,
            'period_start' => $periodStart,
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
     * template change accidentally leaking them.
     */
    public static function paneData(string $scope, ?int $eventId, bool $includeHistory = true): array
    {
        return [
            'cashPreview' => self::preview($scope, $eventId),
            'cashBankings' => $includeHistory
                ? CashBanking::where('scope', $scope)->where('event_id', $eventId)->with('recordedBy')->latest('banked_date')->latest('id')->limit(50)->get()
                : collect(),
            'cashSettlements' => $includeHistory
                ? CashSettlement::where('scope', $scope)->where('event_id', $eventId)->with('performedBy')->latest('period_end')->latest('id')->limit(50)->get()
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

        return DB::transaction(function () use ($data, $performedBy, $tillCount, $amountBanked, $variance, $closing, $varianceNote, $scope, $eventId) {
            if ($amountBanked > 0) {
                CashBanking::create([
                    'scope' => $scope,
                    'event_id' => $eventId,
                    'amount' => $amountBanked,
                    'banked_date' => $data['period_end']->toDateString(),
                    'note' => 'Recorded as part of a till settlement',
                    'recorded_by' => $performedBy,
                ]);
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
