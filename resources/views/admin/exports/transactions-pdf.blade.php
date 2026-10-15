<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26px 28px; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1e1e2a; font-size: 10px; }
    .header { display: table; width: 100%; margin-bottom: 10px; }
    .header .title { font-size: 18px; font-weight: bold; color: #2d1f0e; }
    .header .temple { font-size: 11px; color: #7b6b5a; margin-top: 2px; }
    .header .generated { font-size: 9px; color: #8a8276; margin-top: 2px; }
    .filters-box {
        background: #faf8f5; border: 1px solid #ecd9b8; border-radius: 4px; padding: 8px 12px;
        margin-bottom: 14px; font-size: 9px; color: #5a4e3e;
    }
    .filters-box strong { color: #8A6A1E; }
    .kpi-row { display: table; width: 100%; margin-bottom: 16px; }
    .kpi { display: table-cell; width: 25%; padding: 8px 10px; background: #faf8f5; border: 1px solid #f0ece6; }
    .kpi-label { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #8A6A1E; letter-spacing: 0.5px; }
    .kpi-value { font-size: 14px; font-weight: bold; color: #1e1e2a; margin-top: 2px; }
    h2.section { font-size: 12px; color: #2d1f0e; border-bottom: 2px solid #b8863a; padding-bottom: 4px; margin: 18px 0 8px; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    table.data thead th {
        background: #b8863a; color: #fff; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.3px;
        padding: 5px 6px; text-align: left;
    }
    table.data tbody td { padding: 4px 6px; border-bottom: 1px solid #f0ece6; font-size: 8.8px; }
    table.data tbody tr:nth-child(even) { background: #faf8f5; }
    .amount { text-align: right; font-variant-numeric: tabular-nums; }
    .truncated-note { font-size: 8.5px; color: #c94b4b; font-style: italic; margin-bottom: 8px; }
    .footer { position: fixed; bottom: -16px; left: 0; right: 0; font-size: 8px; color: #8a8276; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        <div class="title">Transactions Report</div>
        <div class="temple">{{ $temple['name'] }}</div>
        <div class="generated">Generated {{ $generatedAt }}</div>
    </div>

    <div class="filters-box">
        <strong>Applied Filters:</strong> {{ implode(' · ', $filterLines) }}
    </div>

    <div class="kpi-row">
        <div class="kpi">
            <div class="kpi-label">Revenue (Paid)</div>
            <div class="kpi-value">{{ $temple['currency'] }} {{ number_format($stats['total_paid'], 2) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Paid Transactions</div>
            <div class="kpi-value">{{ number_format($stats['paid_count']) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Pending</div>
            <div class="kpi-value">{{ $temple['currency'] }} {{ number_format($stats['pending_amount'], 2) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Cancelled</div>
            <div class="kpi-value">{{ number_format($stats['cancelled_count']) }}</div>
        </div>
    </div>

    <h2 class="section">Unified Ledger ({{ number_format($rows->count()) }})</h2>
    @if($rowsTruncated)
    <div class="truncated-note">Showing the first {{ number_format($rows->count()) }} records for this view — use the Excel export for the complete, unlimited dataset.</div>
    @endif
    <table class="data">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Source</th>
                <th>Type</th>
                <th>Donor/Customer</th>
                <th>Channel</th>
                <th>Method</th>
                <th class="amount">Amount</th>
                <th>Status</th>
                <th>Operator</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                <td>{{ $row->created_at->format('d M Y, h:i A') }}</td>
                <td>{{ $row->source_label }}</td>
                <td>{{ $row->type }}</td>
                <td>{{ $row->donor ?? '—' }}</td>
                <td>{{ $row->channel_label }}</td>
                <td>{{ $row->payment_method }}</td>
                <td class="amount">{{ $temple['currency'] }} {{ number_format($row->amount, 2) }}</td>
                <td>{{ $row->status }}</td>
                <td>{{ $row->operator_name ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="9">No records match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="section">Card Terminal Activity ({{ number_format($gateway->count()) }})</h2>
    @if($gatewayTruncated)
    <div class="truncated-note">Showing the first {{ number_format($gateway->count()) }} records for this view — use the Excel export for the complete, unlimited dataset.</div>
    @endif
    <table class="data">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Source</th>
                <th>Provider</th>
                <th>Terminal</th>
                <th>Type</th>
                <th>Channel</th>
                <th class="amount">Amount</th>
                <th>Status</th>
                <th>Operator</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gateway as $g)
            <tr>
                <td>{{ $g->created_at?->format('d M Y, h:i A') }}</td>
                <td>{{ $g->txn->event?->event_name ?? 'Pooja Portal (Tickets)' }}</td>
                <td>{{ $g->provider === 'linkly' ? 'Linkly Cloud' : 'CBA Smart Terminal' }}</td>
                <td>{{ $g->txn->eftTerminal?->label ?? '—' }}</td>
                <td>{{ strtolower($g->txn->txn_type ?? '') }}</td>
                <td>{{ \App\Services\TransactionChannel::label($g->txn->source_channel ?? null) }}</td>
                <td class="amount">{{ $g->txn->currency_code ?? 'AUD' }} {{ number_format($g->txn->amount ?? 0, 2) }}</td>
                <td>{{ $g->txn->status ?? '' }}</td>
                <td>{{ $g->txn->initiator?->name ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="9">No card terminal activity matches these filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">{{ $temple['name'] }} — Transactions Report — Generated {{ $generatedAt }}</div>
</body>
</html>
