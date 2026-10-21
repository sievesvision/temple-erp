<?php

namespace App\Http\Controllers;

use App\Models\LinklyTransaction;
use App\Models\SciTransaction;
use App\Models\Setting;
use App\Services\TransactionChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * The organisation-wide Transactions dashboard: every payment record (devotee donations,
 * guest donations, ticket orders) unified into one revenue ledger, plus the separate
 * card-terminal (Linkly/mx51) gateway activity feed the original Phase 2C version of this
 * page showed. Revenue is deliberately counted ONLY from the donation/order tables — a
 * card payment that succeeds already becomes a 'Paid' row there (see
 * DonationController::createDonationIfApprovedPurchaseUnrecorded() /
 * TicketController::createOrderFromLedgerMeta()) — so the EFT/SCI ledger is shown as its
 * own "gateway activity" panel (approvals/declines/terminal health) rather than summed
 * into total revenue, which would double-count every successful card payment.
 *
 * source_channel (kiosk/register/pos/portal/staff_counter/public) is stamped at creation
 * time (see App\Services\TransactionChannel and its call sites) — it's null on anything
 * recorded before this existed, shown here as "Unknown" rather than guessed.
 */
class OrganisationReportController extends Controller
{
    /** @var array<string, array{date: string, event: bool, operator: ?string, label: string}> */
    private const REVENUE_TABLES = [
        'donations' => ['date' => 'donation_date', 'event' => true, 'operator' => null, 'label' => 'Devotee Donation'],
        'donations_without_logins' => ['date' => 'donation_date', 'event' => true, 'operator' => null, 'label' => 'Guest Donation'],
        'ticket_orders' => ['date' => 'order_date', 'event' => false, 'operator' => 'sold_by', 'label' => 'Ticket Order'],
    ];

    private const STATUSES = ['Paid', 'Pending', 'Cancelled'];

    private function isAdmin(): bool
    {
        $user = Auth::user();
        return $user && $user->role === 'Admin';
    }

    /**
     * Shared filter-parsing for the dashboard view and both export actions, so an export
     * can never drift from what's actually on screen.
     */
    private function parseFilters(Request $request): array
    {
        $filters = [
            'event_id' => $request->get('event_id'), // numeric id, 'tickets', or blank
            'channel' => $request->get('channel'),
            'payment_method' => $request->get('payment_method'),
            'status' => $request->get('status'),
            'operator_id' => $request->get('operator_id'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        return [$filters, $request->get('provider')];
    }

    public function transactions(Request $request)
    {
        if (!$this->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        [$filters, $providerFilter] = $this->parseFilters($request);

        $stats = $this->computeStats($filters);
        $charts = $this->buildCharts($filters);
        $insights = $this->generateInsights($stats, $charts, $filters);
        $rows = $this->fetchLedgerRows($filters);
        $gateway = $this->fetchGatewayActivity($filters, $providerFilter);

        $events = DB::table('events')->orderByDesc('event_date')->select('event_id', 'event_name')->get();

        $operatorIds = DB::table('ticket_orders')->whereNotNull('sold_by')->distinct()->pluck('sold_by')
            ->merge(LinklyTransaction::whereNotNull('initiated_by')->distinct()->pluck('initiated_by'))
            ->merge(SciTransaction::whereNotNull('initiated_by')->distinct()->pluck('initiated_by'))
            ->unique();
        $operators = DB::table('users')->whereIn('id', $operatorIds)->orderBy('name')->select('id', 'name')->get();

        $channels = TransactionChannel::all();

        return view('admin.organisation-transactions', compact(
            'stats', 'charts', 'insights', 'rows', 'gateway', 'events', 'operators', 'channels',
            'filters', 'providerFilter'
        ));
    }

    /**
     * A plain-English summary of whichever filters are active, reused by both export
     * headers so the downloaded file is self-explanatory without the screen it came from.
     */
    private function describeFilters(array $filters, ?string $providerFilter): array
    {
        $lines = [];

        if (!empty($filters['event_id'])) {
            if ($filters['event_id'] === 'tickets') {
                $lines[] = 'Source: Pooja Portal (Tickets)';
            } else {
                $event = DB::table('events')->where('event_id', (int) $filters['event_id'])->first();
                $lines[] = 'Source: ' . ($event->event_name ?? 'Event #' . $filters['event_id']);
            }
        }
        if (!empty($filters['channel'])) {
            $lines[] = 'Channel: ' . ($filters['channel'] === 'unknown' ? 'Unknown / Legacy' : TransactionChannel::label($filters['channel']));
        }
        if (!empty($filters['payment_method'])) {
            $lines[] = 'Payment Method: ' . $filters['payment_method'];
        }
        if (!empty($filters['status'])) {
            $lines[] = 'Status: ' . $filters['status'];
        }
        if (!empty($filters['operator_id'])) {
            $operator = DB::table('users')->where('id', (int) $filters['operator_id'])->first();
            $lines[] = 'Operator: ' . ($operator->name ?? '#' . $filters['operator_id']);
        }
        if ($providerFilter) {
            $lines[] = 'Card Provider: ' . ($providerFilter === 'linkly' ? 'Linkly Cloud' : 'CBA Smart Terminal');
        }
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $lines[] = 'Date Range: ' . ($filters['date_from'] ?: 'Earliest') . ' to ' . ($filters['date_to'] ?: 'Today');
        }

        return $lines ?: ['No filters applied — showing all records.'];
    }

    /**
     * Full (uncapped) Excel export of the currently filtered view — a Summary sheet plus
     * one sheet per table, styled to match the existing event-donations export.
     */
    public function exportExcel(Request $request)
    {
        if (!$this->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        [$filters, $providerFilter] = $this->parseFilters($request);

        $stats = $this->computeStats($filters);
        $rows = $this->fetchLedgerRows($filters, null);
        $gateway = $this->fetchGatewayActivity($filters, $providerFilter, null);
        $filterLines = $this->describeFilters($filters, $providerFilter);
        $temple = Setting::templeBranding();
        $gold = 'B8863A';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // --- Summary sheet ---
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Summary');
        $summary->setCellValue('A1', $temple['name']);
        $summary->setCellValue('A2', 'Transactions Report');
        $summary->setCellValue('A3', 'Generated ' . now()->format('d M Y, h:i A'));
        $summary->mergeCells('A1:B1')->mergeCells('A2:B2')->mergeCells('A3:B3');
        $summary->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $summary->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB($gold);
        $summary->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB('7B6B5A');

        $r = 5;
        $summary->setCellValue("A{$r}", 'Applied Filters');
        $summary->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        foreach ($filterLines as $line) {
            $summary->setCellValue("A{$r}", $line);
            $r++;
        }

        $r += 1;
        $summaryRows = [
            ['Revenue (Paid)', $temple['currency'] . ' ' . number_format($stats['total_paid'], 2)],
            ['Paid Transactions', number_format($stats['paid_count'])],
            ['Average / Transaction', $temple['currency'] . ' ' . number_format($stats['average_txn'], 2)],
            ['Pending Amount', $temple['currency'] . ' ' . number_format($stats['pending_amount'], 2)],
            ['Pending Count', number_format($stats['pending_count'])],
            ['Cancelled Count', number_format($stats['cancelled_count'])],
            ['Total Records', number_format($stats['total_count'])],
        ];
        foreach ($summaryRows as [$label, $value]) {
            $summary->setCellValue("A{$r}", $label);
            $summary->setCellValue("B{$r}", $value);
            $summary->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
        }
        $summary->getColumnDimension('A')->setWidth(28);
        $summary->getColumnDimension('B')->setWidth(28);

        // --- Unified Ledger sheet ---
        $ledger = $spreadsheet->createSheet();
        $ledger->setTitle('Unified Ledger');
        $ledgerHeaders = ['Date/Time', 'Source', 'Type', 'Donor/Customer', 'Channel', 'Method', 'Amount', 'Status', 'Operator'];
        foreach ($ledgerHeaders as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $ledger->setCellValue("{$col}1", $h);
        }
        $ledger->getStyle('A1:I1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $ledger->getStyle('A1:I1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($gold);
        $row = 2;
        foreach ($rows as $txn) {
            $ledger->setCellValue("A{$row}", $txn->created_at->format('d M Y h:i A'));
            $ledger->setCellValue("B{$row}", $txn->source_label);
            $ledger->setCellValue("C{$row}", $txn->type);
            $ledger->setCellValue("D{$row}", $txn->donor ?? '—');
            $ledger->setCellValue("E{$row}", $txn->channel_label);
            $ledger->setCellValue("F{$row}", $txn->payment_method);
            $ledger->setCellValue("G{$row}", (float) $txn->amount);
            $ledger->setCellValue("H{$row}", $txn->status);
            $ledger->setCellValue("I{$row}", $txn->operator_name ?? '—');
            $row++;
        }
        if ($row > 2) {
            $ledger->getStyle("G2:G" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (range('A', 'I') as $col) {
            $ledger->getColumnDimension($col)->setWidth(18);
        }
        $ledger->freezePane('A2');

        // --- Gateway Activity sheet ---
        $gw = $spreadsheet->createSheet();
        $gw->setTitle('Gateway Activity');
        $gwHeaders = ['Date/Time', 'Source', 'Provider', 'Terminal', 'Type', 'Channel', 'Amount', 'Status', 'Operator'];
        foreach ($gwHeaders as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $gw->setCellValue("{$col}1", $h);
        }
        $gw->getStyle('A1:I1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $gw->getStyle('A1:I1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($gold);
        $row = 2;
        foreach ($gateway as $g) {
            $gw->setCellValue("A{$row}", $g->created_at?->format('d M Y h:i A'));
            $gw->setCellValue("B{$row}", $g->txn->event?->event_name ?? 'Pooja Portal (Tickets)');
            $gw->setCellValue("C{$row}", $g->provider === 'linkly' ? 'Linkly Cloud' : 'CBA Smart Terminal');
            $gw->setCellValue("D{$row}", $g->txn->eftTerminal?->label ?? '—');
            $gw->setCellValue("E{$row}", strtolower($g->txn->txn_type ?? ''));
            $gw->setCellValue("F{$row}", TransactionChannel::label($g->txn->source_channel ?? null));
            $gw->setCellValue("G{$row}", (float) ($g->txn->amount ?? 0));
            $gw->setCellValue("H{$row}", $g->txn->status ?? '');
            $gw->setCellValue("I{$row}", $g->txn->initiator?->name ?? '—');
            $row++;
        }
        if ($row > 2) {
            $gw->getStyle("G2:G" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (range('A', 'I') as $col) {
            $gw->getColumnDimension($col)->setWidth(18);
        }
        $gw->freezePane('A2');

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'transactions-export-' . now()->format('Y-m-d-His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * PDF export of the currently filtered view — capped at a generous row count per table
     * since a printable report has practical page limits; the Excel export above has no cap
     * for anyone who needs the complete dataset.
     */
    public function exportPdf(Request $request)
    {
        if (!$this->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        [$filters, $providerFilter] = $this->parseFilters($request);
        $pdfRowCap = 1000;

        $stats = $this->computeStats($filters);
        $rows = $this->fetchLedgerRows($filters, $pdfRowCap);
        $gateway = $this->fetchGatewayActivity($filters, $providerFilter, $pdfRowCap);
        $filterLines = $this->describeFilters($filters, $providerFilter);
        $temple = Setting::templeBranding();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.exports.transactions-pdf', [
            'temple' => $temple,
            'stats' => $stats,
            'rows' => $rows,
            'gateway' => $gateway,
            'filterLines' => $filterLines,
            'rowsTruncated' => $rows->count() >= $pdfRowCap,
            'gatewayTruncated' => $gateway->count() >= $pdfRowCap,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'landscape');

        $filename = 'transactions-export-' . now()->format('Y-m-d-His') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * One table's filtered query builder — common filters only (channel/payment_method/
     * status/date range); event_id and operator are applied by the caller since not every
     * table carries those columns the same way.
     */
    private function filteredQuery(string $table, array $meta, array $filters)
    {
        $query = DB::table($table);

        if (!empty($filters['channel'])) {
            if ($filters['channel'] === 'unknown') {
                $query->whereNull('source_channel');
            } else {
                $query->where('source_channel', $filters['channel']);
            }
        }
        if (!empty($filters['payment_method'])) {
            $pm = $filters['payment_method'];
            // "Bank Transfer" is the canonical label shown in the UI; donations/guest
            // donations store the guest-side form's own "Bank" for the same method.
            if ($pm === 'Bank Transfer') {
                $query->whereIn('payment_method', ['Bank Transfer', 'Bank']);
            } else {
                $query->where('payment_method', $pm);
            }
        }
        if (!empty($filters['status'])) {
            $query->where('payment_status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate($meta['date'], '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate($meta['date'], '<=', $filters['date_to']);
        }
        if ($meta['event']) {
            if (($filters['event_id'] ?? null) === 'tickets') {
                // Donations/guest donations are always event-scoped (nullable for "General
                // Fund") — "tickets" as a source only ever means the ticket_orders table, so
                // this excludes every row from an event-scoped table entirely.
                $query->whereRaw('1 = 0');
            } elseif (!empty($filters['event_id'])) {
                $query->where('event_id', (int) $filters['event_id']);
            }
        } elseif (!empty($filters['event_id']) && $filters['event_id'] !== 'tickets') {
            // A specific event was chosen but this table (ticket_orders) is never
            // event-scoped — nothing here belongs to that event.
            $query->whereRaw('1 = 0');
        }
        // An operator filter only means something where the table actually attributes one
        // (ticket_orders.sold_by) — donations/guest donations have no such column, so a
        // chosen operator correctly excludes them rather than silently ignoring the filter.
        if (!empty($filters['operator_id'])) {
            if ($meta['operator']) {
                $query->where($meta['operator'], (int) $filters['operator_id']);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    private function computeStats(array $filters): array
    {
        $totalPaid = 0.0;
        $paidCount = 0;
        $pendingAmount = 0.0;
        $pendingCount = 0;
        $cancelledCount = 0;
        $totalCount = 0;

        foreach (self::REVENUE_TABLES as $table => $meta) {
            $base = $this->filteredQuery($table, $meta, $filters);
            $totalCount += (clone $base)->count();

            $paid = (clone $base)->where('payment_status', 'Paid');
            $totalPaid += (float) $paid->sum($table === 'ticket_orders' ? 'total_amount' : 'amount');
            $paidCount += (clone $paid)->count();

            $pending = (clone $base)->where('payment_status', 'Pending');
            $pendingAmount += (float) $pending->sum($table === 'ticket_orders' ? 'total_amount' : 'amount');
            $pendingCount += (clone $pending)->count();

            $cancelledCount += (clone $base)->where('payment_status', 'Cancelled')->count();
        }

        // Previous-period comparison: the same number of days immediately before the
        // selected range (or before "now" when no range was chosen, against the last 30
        // days by default) — gives the insight engine and the trend KPI something to
        // compare against rather than a bare, context-free total.
        $to = $filters['date_to'] ? \Carbon\Carbon::parse($filters['date_to']) : now();
        $from = $filters['date_from'] ? \Carbon\Carbon::parse($filters['date_from']) : now()->subDays(29);
        $days = max(1, (int) round($from->diffInDays($to)) + 1);
        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);

        $prevFilters = $filters;
        $prevFilters['date_from'] = $prevFrom->toDateString();
        $prevFilters['date_to'] = $prevTo->toDateString();
        $prevPaid = 0.0;
        foreach (self::REVENUE_TABLES as $table => $meta) {
            $prevPaid += (float) $this->filteredQuery($table, $meta, $prevFilters)
                ->where('payment_status', 'Paid')
                ->sum($table === 'ticket_orders' ? 'total_amount' : 'amount');
        }

        $changePct = $prevPaid > 0 ? round((($totalPaid - $prevPaid) / $prevPaid) * 100, 1) : null;

        return [
            'total_paid' => $totalPaid,
            'paid_count' => $paidCount,
            'pending_amount' => $pendingAmount,
            'pending_count' => $pendingCount,
            'cancelled_count' => $cancelledCount,
            'total_count' => $totalCount,
            'average_txn' => $paidCount > 0 ? $totalPaid / $paidCount : 0.0,
            'prev_period_paid' => $prevPaid,
            'change_pct' => $changePct,
            'window_days' => $days,
            'window_from' => $from->toDateString(),
            'window_to' => $to->toDateString(),
        ];
    }

    private function buildCharts(array $filters): array
    {
        // Revenue trend — one bucket per day across the selected (or default 30-day) window.
        $to = $filters['date_to'] ? \Carbon\Carbon::parse($filters['date_to']) : now();
        $from = $filters['date_from'] ? \Carbon\Carbon::parse($filters['date_from']) : now()->subDays(29);
        $days = min(180, max(1, (int) round($from->diffInDays($to)) + 1)); // cap the chart at 180 points

        $dailyTotals = collect(range(0, $days - 1))->mapWithKeys(fn ($i) => [$from->copy()->addDays($i)->toDateString() => 0.0]);
        foreach (self::REVENUE_TABLES as $table => $meta) {
            $amountCol = $table === 'ticket_orders' ? 'total_amount' : 'amount';
            $rows = $this->filteredQuery($table, $meta, $filters)
                ->where('payment_status', 'Paid')
                ->selectRaw("DATE({$meta['date']}) as d, SUM({$amountCol}) as total")
                ->groupBy('d')->get();
            foreach ($rows as $row) {
                if (isset($dailyTotals[$row->d])) {
                    $dailyTotals[$row->d] += (float) $row->total;
                }
            }
        }

        // Breakdown by channel / payment method / status — merged across all 3 tables.
        $byChannel = [];
        $byMethod = [];
        $byStatus = [];
        $byEvent = [];

        foreach (self::REVENUE_TABLES as $table => $meta) {
            $amountCol = $table === 'ticket_orders' ? 'total_amount' : 'amount';

            foreach ($this->filteredQuery($table, $meta, $filters)->where('payment_status', 'Paid')
                ->selectRaw("COALESCE(source_channel, 'unknown') as k, SUM({$amountCol}) as total")
                ->groupBy('k')->get() as $row) {
                $byChannel[$row->k] = ($byChannel[$row->k] ?? 0) + (float) $row->total;
            }

            // Partial Pledge Payments: for donations/guest donations, this ONE breakdown
            // explodes from the real donation_payments ledger instead of the parent row's
            // own payment_method — a pledge paid across several methods/installments is
            // divided correctly here instead of bucketed under 'Split'. filteredQuery()'s
            // own event/date-range/channel/status filtering is reused unchanged to decide
            // WHICH donations are in scope; only the grouped total pivots to their real
            // payments. Every other aggregation in this method (by channel, by status
            // count, by event) keeps reading the parent row directly — their totals are
            // already correct even for a split donation (status/channel are genuinely
            // donation-level facts, and a fully-paid split donation already mirrors
            // payment_status='Paid' and its full pledged amount once).
            if ($table === 'ticket_orders') {
                foreach ($this->filteredQuery($table, $meta, $filters)->where('payment_status', 'Paid')
                    ->selectRaw("payment_method as k, SUM({$amountCol}) as total")
                    ->groupBy('k')->get() as $row) {
                    $label = in_array($row->k, ['Bank', 'Bank Transfer'], true) ? 'Bank Transfer' : $row->k;
                    $byMethod[$label] = ($byMethod[$label] ?? 0) + (float) $row->total;
                }
            } else {
                $donationType = $table === 'donations' ? 'devotee' : 'guest';
                $paidIds = $this->filteredQuery($table, $meta, $filters)->where('payment_status', 'Paid')->pluck('id');
                foreach (DB::table('donation_payments')
                    ->where('donation_type', $donationType)->whereIn('donation_id', $paidIds)
                    ->where('payment_status', 'Completed')
                    ->selectRaw('payment_method as k, SUM(amount) as total')
                    ->groupBy('k')->get() as $row) {
                    $label = in_array($row->k, ['Bank', 'Bank Transfer'], true) ? 'Bank Transfer' : $row->k;
                    $byMethod[$label] = ($byMethod[$label] ?? 0) + (float) $row->total;
                }
            }

            foreach ($this->filteredQuery($table, $meta, $filters)
                ->selectRaw('payment_status as k, COUNT(*) as total')
                ->groupBy('k')->get() as $row) {
                $byStatus[$row->k] = ($byStatus[$row->k] ?? 0) + (int) $row->total;
            }

            if ($meta['event']) {
                foreach ($this->filteredQuery($table, $meta, $filters)->where('payment_status', 'Paid')
                    ->whereNotNull('event_id')
                    ->selectRaw("event_id as k, SUM({$amountCol}) as total")
                    ->groupBy('k')->get() as $row) {
                    $byEvent[$row->k] = ($byEvent[$row->k] ?? 0) + (float) $row->total;
                }
            } else {
                $ticketsTotal = (float) $this->filteredQuery($table, $meta, $filters)->where('payment_status', 'Paid')->sum($amountCol);
                if ($ticketsTotal > 0) {
                    $byEvent['tickets'] = ($byEvent['tickets'] ?? 0) + $ticketsTotal;
                }
            }
        }

        $eventNames = DB::table('events')->whereIn('event_id', array_filter(array_keys($byEvent), 'is_numeric'))
            ->pluck('event_name', 'event_id');
        $topSources = collect($byEvent)->map(fn ($total, $key) => [
            'label' => $key === 'tickets' ? 'Pooja Portal (Tickets)' : ($eventNames[$key] ?? "Event #{$key}"),
            'total' => $total,
        ])->sortByDesc('total')->take(6)->values();

        return [
            'trend_labels' => $dailyTotals->keys()->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d M'))->values(),
            'trend_values' => $dailyTotals->values(),
            'by_channel' => collect($byChannel)->map(fn ($v, $k) => ['label' => TransactionChannel::label($k === 'unknown' ? null : $k), 'total' => $v])->values(),
            'by_method' => collect($byMethod)->map(fn ($v, $k) => ['label' => $k, 'total' => $v])->sortByDesc('total')->values(),
            'by_status' => collect($byStatus)->map(fn ($v, $k) => ['label' => $k, 'total' => $v])->values(),
            'top_sources' => $topSources,
        ];
    }

    /**
     * Deterministic, explainable "data analyst" callouts — every number here is a plain
     * statistical comparison (period-over-period %, share-of-total, a simple rate
     * threshold), never a fabricated or model-generated claim, so every insight can be
     * traced straight back to the stats/charts already computed above.
     */
    private function generateInsights(array $stats, array $charts, array $filters): array
    {
        $insights = [];

        if ($stats['change_pct'] !== null) {
            $up = $stats['change_pct'] >= 0;
            $insights[] = [
                'tone' => $up ? 'positive' : 'warning',
                'icon' => $up ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow',
                'title' => ($up ? 'Revenue is up ' : 'Revenue is down ') . abs($stats['change_pct']) . '%',
                'body' => 'Compared to the previous ' . $stats['window_days'] . '-day period ('
                    . \Carbon\Carbon::parse($stats['window_from'])->subDays($stats['window_days'])->format('d M')
                    . ' – ' . \Carbon\Carbon::parse($stats['window_from'])->subDay()->format('d M') . ').',
            ];
        }

        $methodTotal = collect($charts['by_method'])->sum('total');
        $topMethod = collect($charts['by_method'])->sortByDesc('total')->first();
        if ($topMethod && $methodTotal > 0) {
            $share = round(($topMethod['total'] / $methodTotal) * 100);
            if ($share >= 50) {
                $insights[] = [
                    'tone' => 'info',
                    'icon' => 'bi-pie-chart-fill',
                    'title' => $topMethod['label'] . ' leads at ' . $share . '% of revenue',
                    'body' => 'The dominant payment method in this view — worth keeping that terminal/process well-staffed during busy periods.',
                ];
            }
        }

        $knownChannelTotal = collect($charts['by_channel'])->where('label', '!=', 'Unknown')->sum('total');
        $unknownTotal = collect($charts['by_channel'])->firstWhere('label', 'Unknown')['total'] ?? 0;
        $grandTotal = $knownChannelTotal + $unknownTotal;
        if ($unknownTotal > 0 && $grandTotal > 0) {
            $share = round(($unknownTotal / $grandTotal) * 100);
            if ($share >= 30) {
                $insights[] = [
                    'tone' => 'info',
                    'icon' => 'bi-question-circle-fill',
                    'title' => $share . '% of revenue has no recorded channel',
                    'body' => 'These are payments recorded before channel tracking existed — new transactions are tagged automatically (Portal, POS, Register, Kiosk, Staff Counter or Public).',
                ];
            }
        } elseif ($knownChannelTotal > 0) {
            $topChannel = collect($charts['by_channel'])->where('label', '!=', 'Unknown')->sortByDesc('total')->first();
            if ($topChannel) {
                $share = round(($topChannel['total'] / $grandTotal) * 100);
                $insights[] = [
                    'tone' => 'positive',
                    'icon' => 'bi-shop',
                    'title' => $topChannel['label'] . ' is the busiest channel',
                    'body' => 'Responsible for ' . $share . '% of tracked revenue in this view.',
                ];
            }
        }

        $pendingShare = $stats['total_count'] > 0 ? round(($stats['pending_count'] / $stats['total_count']) * 100) : 0;
        if ($stats['pending_count'] > 0 && $pendingShare >= 15) {
            $insights[] = [
                'tone' => 'warning',
                'icon' => 'bi-hourglass-split',
                'title' => $stats['pending_count'] . ' payments (' . $pendingShare . '%) are still Pending',
                'body' => 'Mostly Bank Transfer / Cash pledges awaiting confirmation — review and approve from Donations or Ticket Orders to keep this share low.',
            ];
        }

        if (empty($filters['event_id']) && $charts['top_sources']->isNotEmpty()) {
            $leader = $charts['top_sources']->first();
            $runnerUp = $charts['top_sources']->skip(1)->first();
            if ($runnerUp && $runnerUp['total'] > 0) {
                $multiple = round($leader['total'] / $runnerUp['total'], 1);
                if ($multiple >= 1.3) {
                    $insights[] = [
                        'tone' => 'info',
                        'icon' => 'bi-trophy-fill',
                        'title' => $leader['label'] . ' is pacing ahead',
                        'body' => 'Raising ' . $multiple . '× the revenue of the next-best source (' . $runnerUp['label'] . ') in this view.',
                    ];
                }
            }
        }

        if (empty($insights)) {
            $insights[] = [
                'tone' => 'info',
                'icon' => 'bi-info-circle-fill',
                'title' => 'Not enough data yet for a trend callout',
                'body' => 'Insights appear here once there is enough paid activity in the selected filters to compare against.',
            ];
        }

        return $insights;
    }

    /**
     * $limit caps both the per-table fetch and the final combined set — null (used by the
     * export actions, which need the complete filtered set, not just the dashboard preview)
     * removes both caps.
     */
    private function fetchLedgerRows(array $filters, ?int $limit = 300): Collection
    {
        $rows = collect();
        foreach (self::REVENUE_TABLES as $table => $meta) {
            $amountCol = $table === 'ticket_orders' ? 'total_amount' : 'amount';
            $nameCol = $table === 'ticket_orders' ? 'customer_name' : ($table === 'donations' ? null : 'donor_name');

            $query = $this->filteredQuery($table, $meta, $filters)->orderByDesc('created_at');
            if ($limit !== null) {
                $query->limit($limit);
            }
            foreach ($query->get() as $r) {
                $rows->push((object) [
                    'created_at' => \Carbon\Carbon::parse($r->created_at),
                    'type' => $meta['label'],
                    'event_id' => $meta['event'] ? ($r->event_id ?? null) : null,
                    'is_tickets' => !$meta['event'],
                    'donor' => $nameCol ? ($r->{$nameCol} ?? null) : null,
                    'amount' => (float) $r->{$amountCol},
                    'payment_method' => in_array($r->payment_method, ['Bank', 'Bank Transfer'], true) ? 'Bank Transfer' : $r->payment_method,
                    'status' => $r->payment_status,
                    'channel' => $r->source_channel ?? null,
                    'operator_id' => $meta['operator'] ? ($r->{$meta['operator']} ?? null) : null,
                ]);
            }
        }

        $eventNames = DB::table('events')->pluck('event_name', 'event_id');
        $operatorNames = DB::table('users')->pluck('name', 'id');

        $sorted = $rows->sortByDesc('created_at');
        if ($limit !== null) {
            $sorted = $sorted->take($limit);
        }

        return $sorted->values()->map(function ($row) use ($eventNames, $operatorNames) {
            $row->source_label = $row->is_tickets ? 'Pooja Portal (Tickets)' : ($row->event_id ? ($eventNames[$row->event_id] ?? 'Event #' . $row->event_id) : 'General Fund');
            $row->channel_label = TransactionChannel::label($row->channel);
            $row->operator_name = $row->operator_id ? ($operatorNames[$row->operator_id] ?? null) : null;
            return $row;
        });
    }

    private function fetchGatewayActivity(array $filters, ?string $providerFilter, ?int $limit = 150): Collection
    {
        $applyCommonFilters = function ($query) use ($filters) {
            if (($filters['event_id'] ?? null) === 'tickets') {
                $query->whereNull('event_id');
            } elseif (!empty($filters['event_id'])) {
                $query->where('event_id', (int) $filters['event_id']);
            }
            if (!empty($filters['operator_id'])) {
                $query->where('initiated_by', (int) $filters['operator_id']);
            }
            if (!empty($filters['channel'])) {
                $filters['channel'] === 'unknown' ? $query->whereNull('source_channel') : $query->where('source_channel', $filters['channel']);
            }
            if (!empty($filters['status'])) {
                // The gateway ledger's own statuses (approved/declined/...) differ from the
                // revenue tables' Paid/Pending/Cancelled — map the shared filter sensibly.
                $map = ['Paid' => ['approved', 'finalised'], 'Pending' => ['initiated', 'in_progress'], 'Cancelled' => ['declined', 'failed', 'cancelled']];
                if (isset($map[$filters['status']])) {
                    $query->whereIn('status', $map[$filters['status']]);
                }
            }
            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }
            return $query;
        };

        $linklyTransactions = collect();
        if (!$providerFilter || $providerFilter === 'linkly') {
            $linklyQuery = $applyCommonFilters(
                LinklyTransaction::with(['eftTerminal', 'initiator', 'event'])->whereIn('txn_type', ['purchase', 'refund'])
            )->orderBy('created_at', 'desc');
            if ($limit !== null) {
                $linklyQuery->limit($limit);
            }
            $linklyTransactions = $linklyQuery->get();
        }

        $sciTransactions = collect();
        if (!$providerFilter || $providerFilter === 'cba_sci') {
            $sciQuery = $applyCommonFilters(
                SciTransaction::with(['eftTerminal', 'initiator', 'event'])
            )->orderBy('created_at', 'desc');
            if ($limit !== null) {
                $sciQuery->limit($limit);
            }
            $sciTransactions = $sciQuery->get();
        }

        $combined = $linklyTransactions->map(fn ($t) => (object) ['provider' => 'linkly', 'txn' => $t, 'created_at' => $t->created_at])
            ->concat($sciTransactions->map(fn ($t) => (object) ['provider' => 'cba_sci', 'txn' => $t, 'created_at' => $t->created_at]))
            ->sortByDesc('created_at');

        if ($limit !== null) {
            $combined = $combined->take($limit);
        }

        return $combined
            ->values();
    }
}
