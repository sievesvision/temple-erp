@extends('admin.layouts.app')

@section('title', 'Transactions')

@section('page-css')
<style>
    .page-header {
        background: white; border-radius: 24px; padding: 24px 32px; margin-bottom: 20px;
        border: 1px solid rgba(184, 134, 58, 0.06); box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .page-header h1 { font-weight: 700; font-size: 1.8rem; color: #2d1f0e; margin: 0; }
    .page-header h1 i { color: #b8863a; margin-right: 12px; }
    .page-header .subtitle { color: #7b6b5a; font-size: 0.95rem; margin-top: 4px; }
    .hub-links { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
    .hub-link {
        background: white; border: 1px solid rgba(184, 134, 58, 0.12); border-radius: 14px;
        padding: 12px 20px; color: #2d1f0e; text-decoration: none; font-weight: 600; font-size: 0.88rem;
        display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    }
    .hub-link:hover { border-color: #b8863a; color: #b8863a; }
    .hub-link i { color: #b8863a; }
    .filter-bar {
        background: white; border-radius: 20px; padding: 16px 20px; margin-bottom: 20px;
        border: 1px solid rgba(184, 134, 58, 0.06); box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }

    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .kpi-card {
        background: white; border-radius: 20px; padding: 20px 22px; border: 1px solid rgba(184, 134, 58, 0.07);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .kpi-label { font-size: 0.74rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: #8A6A1E; }
    .kpi-value { font-size: 1.65rem; font-weight: 800; color: #1e1e2a; margin-top: 4px; letter-spacing: -0.4px; font-variant-numeric: tabular-nums; }
    .kpi-sub { font-size: 0.8rem; color: #7b6b5a; margin-top: 4px; }
    .kpi-sub.up { color: #1f9d6a; font-weight: 700; }
    .kpi-sub.down { color: #c94b4b; font-weight: 700; }

    .section-label { font-size: 0.78rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: #8A6A1E; margin: 28px 0 14px; }
    .section-label:first-of-type { margin-top: 0; }

    .insights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .insight-card {
        border-radius: 18px; padding: 16px 18px; display: flex; gap: 12px; align-items: flex-start;
        border: 1px solid rgba(0,0,0,0.04); box-shadow: 0 6px 18px rgba(0,0,0,0.02);
    }
    .insight-card.tone-positive { background: #f0fbf6; border-color: rgba(31,157,106,0.18); }
    .insight-card.tone-warning { background: #fff8ec; border-color: rgba(201,75,75,0.15); }
    .insight-card.tone-info { background: #f4f7fd; border-color: rgba(42,111,219,0.14); }
    .insight-icon {
        width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; flex-shrink: 0;
    }
    .tone-positive .insight-icon { background: #1f9d6a; color: white; }
    .tone-warning .insight-icon { background: #c94b4b; color: white; }
    .tone-info .insight-icon { background: #2a6fdb; color: white; }
    .insight-title { font-weight: 700; font-size: 0.92rem; color: #2d1f0e; }
    .insight-body { font-size: 0.82rem; color: #6b5f52; margin-top: 2px; line-height: 1.35; }

    .chart-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 16px; margin-bottom: 20px; }
    @media (max-width: 992px) { .chart-grid { grid-template-columns: 1fr; } }
    .chart-card {
        background: white; border-radius: 22px; border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02); padding: 20px 22px;
    }
    .chart-card h6 { font-weight: 700; color: #2d1f0e; margin-bottom: 14px; font-size: 0.92rem; }
    .chart-card canvas { max-height: 280px; }
    .mini-chart-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
    @media (max-width: 768px) { .mini-chart-row { grid-template-columns: 1fr; } }

    .table-card {
        background: white; border-radius: 24px; border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02); overflow: hidden; margin-bottom: 24px;
    }
    .table-card .card-header {
        background: transparent; border-bottom: 1px solid #f0ece6; padding: 18px 24px; font-weight: 600;
        font-size: 1.05rem; color: #2d1f0e; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
    }
    .table-card .table thead th { font-weight: 600; color: #5a4e3e; border-bottom: 2px solid #f0ece6; padding: 14px 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; background: #faf8f5; }
    .table-card .table tbody td { padding: 12px 16px; border-bottom: 1px solid #f5f0ea; color: #1e1e2a; font-weight: 500; vertical-align: middle; font-size: 0.88rem; }
    .table-card .table tbody tr:hover { background: #faf8f5; }
    .channel-pill {
        display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 20px; font-size: 0.74rem; font-weight: 700;
        background: #f3ebe0; color: #8A6A1E;
    }
    .channel-pill.channel-unknown { background: #f0ece6; color: #8a8276; }

    .section-label.collapsible { display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; }
    .section-label.collapsible .chevron { transition: transform 0.2s ease; font-size: 1rem; }
    .section-label.collapsible[aria-expanded="false"] .chevron { transform: rotate(-90deg); }

    .export-actions { display: flex; gap: 8px; }
    .export-btn {
        border: 1px solid rgba(184, 134, 58, 0.25); background: white; color: #8A6A1E; font-weight: 700;
        font-size: 0.82rem; border-radius: 10px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px;
        text-decoration: none;
    }
    .export-btn:hover { background: #faf4e8; color: #8A6A1E; }

    /* DataTables customization to match the page's own table styling */
    .dataTables_wrapper .dataTables_filter input { border-radius: 10px; border: 1px solid #e3dccf; padding: 5px 12px; margin-left: 6px; }
    .dataTables_wrapper .dataTables_length select { border-radius: 10px; border: 1px solid #e3dccf; padding: 4px 8px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 8px !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #b8863a !important; border-color: #b8863a !important; color: white !important; }
    .dataTables_wrapper .dataTables_info { color: #7b6b5a; font-size: 0.82rem; }
    .table-card .card-header-controls { padding: 12px 24px; border-bottom: 1px solid #f0ece6; }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1><i class="bi bi-graph-up-arrow"></i>Transactions</h1>
    <div class="subtitle">Every donation, ticket order and card payment, unified — revenue, trends and channel insights across every Event Portal, the Pooja Portal, Registers, Kiosks and POS counters.</div>
</div>

<div class="hub-links">
    <a href="{{ route('admin.donations.index') }}" class="hub-link"><i class="bi bi-cash-coin"></i>All Donations</a>
    <a href="{{ route('admin.tickets.index') }}" class="hub-link"><i class="bi bi-ticket-perforated-fill"></i>All Ticket Orders</a>
    <a href="{{ route('admin.reports.index') }}" class="hub-link"><i class="bi bi-bar-chart-fill"></i>Reports</a>
    <a href="{{ route('admin.logs.index') }}" class="hub-link"><i class="bi bi-journal-text"></i>Audit Logs</a>
    <span class="export-actions ms-auto">
        <a href="{{ route('admin.transactions.export.excel', request()->query()) }}" class="export-btn"><i class="bi bi-file-earmark-excel"></i>Export Excel</a>
        <a href="{{ route('admin.transactions.export.pdf', request()->query()) }}" class="export-btn"><i class="bi bi-file-earmark-pdf"></i>Export PDF</a>
    </span>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('admin.transactions.index') }}" class="row g-2 align-items-center">
        <div class="col-md-2">
            <select name="event_id" class="form-select rounded-3">
                <option value="">All Sources</option>
                <option value="tickets" {{ $filters['event_id'] === 'tickets' ? 'selected' : '' }}>Pooja Portal (Tickets)</option>
                @foreach($events as $e)
                    <option value="{{ $e->event_id }}" {{ (string) $filters['event_id'] === (string) $e->event_id ? 'selected' : '' }}>{{ $e->event_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="channel" class="form-select rounded-3">
                <option value="">Any Channel</option>
                @foreach($channels as $c)
                    <option value="{{ $c }}" {{ $filters['channel'] === $c ? 'selected' : '' }}>{{ \App\Services\TransactionChannel::label($c) }}</option>
                @endforeach
                <option value="unknown" {{ $filters['channel'] === 'unknown' ? 'selected' : '' }}>Unknown / Legacy</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="payment_method" class="form-select rounded-3">
                <option value="">Any Method</option>
                @foreach(['Cash','Bank Transfer','EFT Terminal','UPI','Cheque','Stripe'] as $m)
                    <option value="{{ $m }}" {{ $filters['payment_method'] === $m ? 'selected' : '' }}>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select rounded-3">
                <option value="">Any Status</option>
                @foreach(['Paid','Pending','Cancelled'] as $s)
                    <option value="{{ $s }}" {{ $filters['status'] === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="operator_id" class="form-select rounded-3">
                <option value="">Any Operator</option>
                @foreach($operators as $op)
                    <option value="{{ $op->id }}" {{ (string) $filters['operator_id'] === (string) $op->id ? 'selected' : '' }}>{{ $op->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="provider" class="form-select rounded-3">
                <option value="">Any Card Provider</option>
                <option value="linkly" {{ $providerFilter === 'linkly' ? 'selected' : '' }}>Linkly Cloud</option>
                <option value="cba_sci" {{ $providerFilter === 'cba_sci' ? 'selected' : '' }}>CBA Smart Terminal</option>
            </select>
        </div>
        <div class="col-md-2">
            <select id="quickRange" class="form-select rounded-3">
                <option value="">Quick Range…</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="this_week">This Week</option>
                <option value="last_7">Last 7 Days</option>
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="this_quarter">This Quarter</option>
                <option value="this_year">This Year</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="date_from" id="dateFrom" class="form-control rounded-3" value="{{ $filters['date_from'] }}" placeholder="From">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_to" id="dateTo" class="form-control rounded-3" value="{{ $filters['date_to'] }}" placeholder="To">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Filter</button>
            @if(array_filter($filters) || $providerFilter)
            <a href="{{ route('admin.transactions.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Revenue (Paid)</div>
        <div class="kpi-value">{{ $temple['currency'] ?? 'AUD' }} {{ number_format($stats['total_paid'], 2) }}</div>
        @if($stats['change_pct'] !== null)
        <div class="kpi-sub {{ $stats['change_pct'] >= 0 ? 'up' : 'down' }}">
            <i class="bi bi-arrow-{{ $stats['change_pct'] >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($stats['change_pct']) }}% vs previous period
        </div>
        @endif
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Paid Transactions</div>
        <div class="kpi-value">{{ number_format($stats['paid_count']) }}</div>
        <div class="kpi-sub">Avg {{ $temple['currency'] ?? 'AUD' }} {{ number_format($stats['average_txn'], 2) }} / transaction</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Pending</div>
        <div class="kpi-value">{{ $temple['currency'] ?? 'AUD' }} {{ number_format($stats['pending_amount'], 2) }}</div>
        <div class="kpi-sub">{{ number_format($stats['pending_count']) }} awaiting confirmation</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Cancelled</div>
        <div class="kpi-value">{{ number_format($stats['cancelled_count']) }}</div>
        <div class="kpi-sub">Of {{ number_format($stats['total_count']) }} total records</div>
    </div>
</div>

<div class="section-label collapsible" data-bs-toggle="collapse" data-bs-target="#insightsBody" aria-expanded="true" role="button">
    <span><i class="bi bi-stars me-1"></i>Insights</span>
    <i class="bi bi-chevron-down chevron"></i>
</div>
<div class="collapse show" id="insightsBody">
    <div class="insights-grid">
        @foreach($insights as $insight)
        <div class="insight-card tone-{{ $insight['tone'] }}">
            <div class="insight-icon"><i class="bi {{ $insight['icon'] }}"></i></div>
            <div>
                <div class="insight-title">{{ $insight['title'] }}</div>
                <div class="insight-body">{{ $insight['body'] }}</div>
            </div>
        </div>
        @endforeach
    </div>
</div>

<div class="chart-grid">
    <div class="chart-card">
        <h6><i class="bi bi-graph-up me-1"></i>Revenue Trend ({{ $stats['window_days'] }} days)</h6>
        <canvas id="trendChart"></canvas>
    </div>
    <div class="chart-card">
        <h6><i class="bi bi-trophy-fill me-1"></i>Top Sources</h6>
        <canvas id="topSourcesChart"></canvas>
    </div>
</div>

<div class="mini-chart-row">
    <div class="chart-card">
        <h6><i class="bi bi-credit-card-fill me-1"></i>Revenue by Payment Method</h6>
        <canvas id="methodChart"></canvas>
    </div>
    <div class="chart-card">
        <h6><i class="bi bi-shop me-1"></i>Revenue by Channel</h6>
        <canvas id="channelChart"></canvas>
    </div>
</div>

<div class="section-label"><i class="bi bi-receipt me-1"></i>Unified Ledger</div>
<div class="table-card">
    <div class="card-header">
        <span>Donations & Ticket Orders ({{ $rows->count() }}@if($rows->count() === 300)+@endif)</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle" id="ledgerTable">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Source</th>
                    <th>Type</th>
                    <th>Donor/Customer</th>
                    <th>Channel</th>
                    <th>Method</th>
                    <th>Amount</th>
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
                    <td><span class="channel-pill {{ $row->channel ? '' : 'channel-unknown' }}">{{ $row->channel_label }}</span></td>
                    <td>{{ $row->payment_method }}</td>
                    <td>{{ $temple['currency'] ?? 'AUD' }} {{ number_format($row->amount, 2) }}</td>
                    <td>
                        <span class="badge {{ $row->status === 'Paid' ? 'bg-success' : ($row->status === 'Cancelled' ? 'bg-danger' : 'bg-secondary') }}">{{ $row->status }}</span>
                    </td>
                    <td>{{ $row->operator_name ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-receipt fs-1 d-block mb-2 text-warning"></i>
                        No donations or ticket orders match these filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="section-label"><i class="bi bi-credit-card me-1"></i>Card Terminal Activity</div>
<div class="table-card">
    <div class="card-header">
        <span>EFT / mx51 Gateway Activity ({{ $gateway->count() }}@if($gateway->count() === 150)+@endif)</span>
        <span class="text-muted" style="font-size:0.78rem; font-weight:500;">Approvals already appear above as a Paid donation/order — this is terminal-level health, not additional revenue.</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle" id="gatewayTable">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Source</th>
                    <th>Provider</th>
                    <th>Terminal</th>
                    <th>Type</th>
                    <th>Channel</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Operator</th>
                </tr>
            </thead>
            <tbody>
                @forelse($gateway as $row)
                <tr>
                    <td>{{ $row->created_at?->format('d M Y, h:i A') }}</td>
                    <td>{{ $row->txn->event?->event_name ?? 'Pooja Portal (Tickets)' }}</td>
                    <td>{{ $row->provider === 'linkly' ? 'Linkly Cloud' : 'CBA Smart Terminal' }}</td>
                    <td>{{ $row->txn->eftTerminal?->label ?? '—' }}</td>
                    <td class="text-capitalize">{{ strtolower($row->txn->txn_type ?? '') }}</td>
                    <td><span class="channel-pill {{ ($row->txn->source_channel ?? null) ? '' : 'channel-unknown' }}">{{ \App\Services\TransactionChannel::label($row->txn->source_channel ?? null) }}</span></td>
                    <td>{{ $row->txn->currency_code ?? 'AUD' }} {{ number_format($row->txn->amount ?? 0, 2) }}</td>
                    <td>
                        @php $status = $row->txn->status ?? ''; @endphp
                        <span class="badge {{ in_array(strtoupper($status), ['APPROVED','FINALISED']) ? 'bg-success' : (in_array(strtoupper($status), ['DECLINED','FAILED','CANCELLED']) ? 'bg-danger' : 'bg-secondary') }}">{{ $status }}</span>
                    </td>
                    <td>{{ $row->txn->initiator?->name ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-credit-card fs-1 d-block mb-2 text-warning"></i>
                        No card terminal activity matches these filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('page-js')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
<script>
(function () {
    const gold = '#b8863a';
    const palette = ['#b8863a', '#2a6fdb', '#1f9d6a', '#c94b6e', '#8a6a1e', '#6f8fd0'];

    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: @json($charts['trend_labels']),
                datasets: [{
                    label: 'Paid Revenue',
                    data: @json($charts['trend_values']),
                    borderColor: gold,
                    backgroundColor: 'rgba(184,134,58,0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    const topSourcesCtx = document.getElementById('topSourcesChart');
    if (topSourcesCtx) {
        const sources = @json($charts['top_sources']);
        new Chart(topSourcesCtx, {
            type: 'bar',
            data: {
                labels: sources.map(s => s.label),
                datasets: [{
                    label: 'Revenue',
                    data: sources.map(s => s.total),
                    backgroundColor: palette,
                    borderRadius: 8,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    const methodCtx = document.getElementById('methodChart');
    if (methodCtx) {
        const methods = @json($charts['by_method']);
        new Chart(methodCtx, {
            type: 'doughnut',
            data: {
                labels: methods.map(m => m.label),
                datasets: [{ data: methods.map(m => m.total), backgroundColor: palette }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }

    const channelCtx = document.getElementById('channelChart');
    if (channelCtx) {
        const channels = @json($charts['by_channel']);
        new Chart(channelCtx, {
            type: 'doughnut',
            data: {
                labels: channels.map(c => c.label),
                datasets: [{ data: channels.map(c => c.total), backgroundColor: palette }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
})();
</script>
<script>
$(function () {
    // Quick date-range presets next to the From/To fields — picking one fills both date
    // inputs and submits the filter form immediately, same as manually typing a range.
    document.getElementById('quickRange').addEventListener('change', function () {
        const val = this.value;
        if (!val) return;
        const fmt = d => d.toISOString().slice(0, 10);
        const today = new Date();
        let from = new Date(today), to = new Date(today);

        switch (val) {
            case 'today':
                break;
            case 'yesterday':
                from.setDate(from.getDate() - 1);
                to.setDate(to.getDate() - 1);
                break;
            case 'this_week':
                from.setDate(today.getDate() - today.getDay());
                break;
            case 'last_7':
                from.setDate(today.getDate() - 6);
                break;
            case 'this_month':
                from = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            case 'last_month':
                from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                to = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'this_quarter':
                from = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3, 1);
                break;
            case 'this_year':
                from = new Date(today.getFullYear(), 0, 1);
                break;
        }

        document.getElementById('dateFrom').value = fmt(from);
        document.getElementById('dateTo').value = fmt(to);
        this.closest('form').submit();
    });

    // Unified Ledger / Card Terminal Activity tables — search box + pagination, matching
    // the DataTables convention already used on the other admin management pages.
    const tableOptions = {
        pageLength: 25,
        order: [],
        language: {
            emptyTable: 'No records found.',
            search: '',
            searchPlaceholder: 'Search this table…',
        },
    };
    if ($('#ledgerTable').length) {
        $('#ledgerTable').DataTable(tableOptions);
    }
    if ($('#gatewayTable').length) {
        $('#gatewayTable').DataTable(tableOptions);
    }
});
</script>
@endsection
