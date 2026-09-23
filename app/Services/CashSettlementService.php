<?php

namespace App\Services;

use App\Models\CashBanking;
use App\Models\CashSettlement;
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
     * Everything an Event Console / Ticket Console pane needs to render the Cash Banking
     * section — called directly from EventConsoleController::show() / TicketController::
     * manageTickets() and merged into their existing view data, rather than this feature
     * living on its own separate page.
     */
    public static function paneData(string $scope, ?int $eventId): array
    {
        return [
            'cashPreview' => self::preview($scope, $eventId),
            'cashBankings' => CashBanking::where('scope', $scope)->where('event_id', $eventId)
                ->with('recordedBy')->latest('banked_date')->latest('id')->limit(50)->get(),
            'cashSettlements' => CashSettlement::where('scope', $scope)->where('event_id', $eventId)
                ->with('performedBy')->latest('period_end')->latest('id')->limit(50)->get(),
        ];
    }

    /**
     * Locks the current preview() figures into a permanent CashSettlement row.
     */
    public static function run(string $scope, ?int $eventId, int $performedBy, ?Carbon $periodEnd = null): CashSettlement
    {
        $data = self::preview($scope, $eventId, $periodEnd);

        return DB::transaction(function () use ($data, $performedBy) {
            $settlement = CashSettlement::create([
                'scope' => $data['scope'],
                'event_id' => $data['event_id'],
                'period_start' => $data['period_start']->toDateString(),
                'period_end' => $data['period_end']->toDateString(),
                'opening_balance' => $data['opening_balance'],
                'cash_received' => $data['cash_received'],
                'amount_banked' => $data['amount_banked'],
                'closing_balance' => $data['closing_balance'],
                'breakdown' => $data['breakdown'],
                'performed_by' => $performedBy,
            ]);

            $label = $data['scope'] === 'event' ? "event #{$data['event_id']}" : 'tickets';
            AuditLogService::log(
                "Ran cash settlement for {$label}: closing balance {$data['closing_balance']} remaining to bank",
                $performedBy,
                $data['event_id']
            );

            return $settlement;
        });
    }
}
