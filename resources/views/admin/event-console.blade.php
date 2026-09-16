<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Console · {{ $event->event_name }}</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <style>
        :root {
            --gold: #b8863a;
            --gold-light: #e0ac4f;
            --teal: #0f9d6a;
            --navy: #1e293b;
            --navy-dark: #0f172a;
            --ink: #1e2530;
            --muted: #64748b;
            --cream: #f6f7f9;
            --card-line: #e8eaee;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { overflow-x: hidden; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--cream); color: var(--ink); }

        .console-topbar {
            background: linear-gradient(135deg, var(--navy), var(--navy-dark));
            color: white;
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 40;
            box-shadow: 0 4px 18px rgba(15,23,42,0.18);
        }
        .console-topbar .back-link { color: rgba(255,255,255,0.6); font-size: 0.82rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .console-topbar .back-link:hover { color: white; }
        .console-topbar h1 { font-size: 1.25rem; font-weight: 800; margin: 2px 0 0; letter-spacing: -0.01em; }
        .topbar-left { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .topbar-brand { display: flex; align-items: center; gap: 10px; }
        .topbar-logo { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: #fff; padding: 2px; flex-shrink: 0; }
        .topbar-temple-name { font-weight: 800; font-size: 0.92rem; line-height: 1.2; }
        .topbar-temple-sub { font-size: 0.7rem; color: rgba(255,255,255,0.55); line-height: 1.2; }
        .topbar-divider { width: 1px; height: 32px; background: rgba(255,255,255,0.15); }
        .console-tabs { display: flex; gap: 8px; flex-wrap: wrap; background: rgba(255,255,255,0.06); padding: 6px; border-radius: 18px; }
        .console-tab-btn {
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.75);
            padding: 12px 22px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .console-tab-btn.active { background: linear-gradient(135deg, var(--gold), var(--gold-light)); color: white; box-shadow: 0 6px 16px rgba(184,134,58,0.35); }
        .console-tab-btn:not(.active):hover { background: rgba(255,255,255,0.08); color: white; }
        .btn-fullscreen { background: rgba(255,255,255,0.12); border: none; color: white; padding: 12px 20px; border-radius: 40px; font-weight: 700; font-size: 0.85rem; }
        .btn-fullscreen:hover { background: rgba(255,255,255,0.2); color: white; }

        .console-body { padding: 22px clamp(16px, 2.2vw, 32px); max-width: 100%; margin: 0 auto 100px; }
        .pane-narrow { max-width: 1080px; margin: 0 auto; }
        .console-pane { display: none; }
        .console-pane.active { display: block; animation: fadeIn 0.25s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        .console-card { background: white; border-radius: 10px; padding: 22px; box-shadow: 0 1px 3px rgba(15,23,42,0.05), 0 6px 18px rgba(15,23,42,0.04); margin-bottom: 18px; border: 1px solid var(--card-line); }

        .stat-tile { background: white; border-radius: 14px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(15,23,42,0.05), 0 6px 18px rgba(15,23,42,0.04); border: 1px solid var(--card-line); display: flex; align-items: center; gap: 14px; min-width: 0; }
        .stat-tile .stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: white; flex-shrink: 0; }
        .stat-tile .stat-text { min-width: 0; }
        .stat-tile .label { color: var(--muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
        .stat-tile .value { font-size: 1.35rem; font-weight: 800; color: var(--ink); margin-top: 2px; overflow-wrap: break-word; }

        .option-breakdown-scroll { display: flex; gap: 10px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 2px 2px 10px; margin-bottom: 4px; }
        .option-chip-stat { flex: 0 0 auto; min-width: 150px; max-width: 220px; background: white; border: 1px solid var(--card-line); border-radius: 12px; padding: 12px 16px; }
        .option-chip-stat .label { color: var(--muted); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .option-chip-stat .value { font-size: 1.05rem; font-weight: 800; color: var(--ink); margin-top: 2px; }

        /* Quick Entry — grouped into shaded panels (Donor / Donation / Payment), each with a
           colored left-bar title, so the form reads as distinct sections instead of one flat
           stack of identical-looking fields on a white card. */
        .qe-panel { background: #f7f9fb; border: 1px solid #e3e8ee; border-radius: 10px; padding: 20px; margin-bottom: 18px; }
        .qe-panel:last-child { margin-bottom: 0; }
        .qe-panel-title { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--ink); margin-bottom: 16px; }
        .qe-panel-title .bar { width: 4px; height: 16px; border-radius: 2px; flex-shrink: 0; }

        .quick-entry-toggle { display: flex; gap: 10px; margin-bottom: 20px; }
        .quick-entry-toggle button { flex: 1; padding: 16px; min-height: 54px; border-radius: 8px; border: 1.5px solid #dde3ea; background: #f7f9fb; font-weight: 700; font-size: 1.02rem; color: var(--muted); transition: 0.2s; }
        .quick-entry-toggle button.active { border-color: var(--gold); background: var(--gold); color: white; box-shadow: 0 4px 14px rgba(184,134,58,0.28); }

        .qe-field { margin-bottom: 1.1rem; }
        .qe-field:last-child { margin-bottom: 0; }

        /* The "left-shaded label" input group: a tinted label cell fused to the input, both
           inside one bordered control — reads as a single professional form control rather
           than a plain borderless input floating on the page. Stacks (label on top) on
           narrow phones; sits to the left from tablet width up. */
        .qe-input-group { display: flex; flex-direction: column; border: 1.5px solid #d5dce4; border-radius: 8px; background: #fff; overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s; }
        .qe-input-group:focus-within { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(184,134,58,0.14); }
        .qe-input-group .qe-input-label { background: #eef2f6; color: #51606f; font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 9px 14px; border-bottom: 1.5px solid #d5dce4; }
        .qe-input-group input, .qe-input-group select, .qe-input-group textarea { border: none; background: transparent; padding: 14px; font-size: 1.02rem; width: 100%; font-family: inherit; min-height: 50px; }
        .qe-input-group textarea { min-height: auto; }
        .qe-input-group input:focus, .qe-input-group select:focus, .qe-input-group textarea:focus { outline: none; }
        @media (min-width: 576px) {
            .qe-input-group { flex-direction: row; align-items: stretch; }
            .qe-input-group .qe-input-label { border-bottom: none; border-right: 1.5px solid #d5dce4; display: flex; align-items: center; min-width: 150px; flex-shrink: 0; }
            .qe-input-group input, .qe-input-group select, .qe-input-group textarea { flex: 1; min-width: 0; }
        }

        .quick-amount-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .quick-amount-btn { background: #fff; border: 1.5px solid #e3d9bf; color: var(--gold); font-weight: 700; padding: 12px 20px; min-height: 46px; min-width: 64px; border-radius: 8px; font-size: 1rem; transition: 0.15s; }
        .quick-amount-btn:hover { background: #fbf6ea; }
        .quick-amount-btn.active { background: var(--gold); border-color: var(--gold); color: white; box-shadow: 0 4px 12px rgba(184,134,58,0.35); }

        .devotee-combobox-wrap { position: relative; }
        .devotee-combobox-results { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #d5dce4; border-radius: 8px; max-height: 280px; overflow-y: auto; z-index: 20; box-shadow: 0 16px 40px rgba(0,0,0,0.12); display: none; margin-top: 6px; }
        .devotee-combobox-results.show { display: block; }
        .devotee-combobox-item { padding: 14px 18px; cursor: pointer; border-bottom: 1px solid #eef2f6; }
        .devotee-combobox-item:hover, .devotee-combobox-item.highlighted { background: #f7f9fb; }

        .qe-toast { position: fixed; bottom: 100px; right: 24px; background: var(--teal); color: white; padding: 18px 26px; border-radius: 16px; font-weight: 700; box-shadow: 0 14px 34px rgba(0,0,0,0.18); z-index: 999; display: none; font-size: 1.05rem; }
        .qe-toast.error { background: #dc3545; }

        .table-scroll-wrap { overflow: auto; max-height: calc(100vh - 250px); border-radius: 16px; }
        table.console-table { width: 100%; border-collapse: collapse; }
        table.console-table th, table.console-table td { padding: 11px 14px; text-align: left; font-size: 0.84rem; border-bottom: 1px solid #eef1f5; white-space: nowrap; }
        table.console-table th { background: #f8fafc; font-weight: 700; color: var(--muted); text-transform: uppercase; font-size: 0.66rem; letter-spacing: 0.05em; position: sticky; top: 0; z-index: 5; box-shadow: inset 0 -1px 0 var(--card-line); }
        table.console-table td.col-name { white-space: normal; min-width: 150px; font-weight: 600; }
        table.console-table td.col-amount, table.console-table th.col-amount { text-align: right; font-variant-numeric: tabular-nums; }
        table.console-table td.col-amount.total { font-weight: 800; color: var(--ink); }
        table.console-table td.col-txn { max-width: 130px; overflow: hidden; text-overflow: ellipsis; font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.78rem; color: var(--muted); }
        table.console-table tbody tr:nth-child(even) { background: #fbfcfd; }
        table.console-table tbody tr:hover { background: #f4f8fb; }
        .status-pill { display: inline-block; padding: 3px 11px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
        .status-pill.status-paid { background: rgba(15,157,106,0.1); color: var(--teal); }
        .status-pill.status-pending { background: rgba(224,166,56,0.14); color: #b7791f; }
        .status-pill.status-cancelled, .status-pill.status-failed { background: rgba(220,53,69,0.1); color: #dc3545; }
        .btn-refresh { background: white; border: 1px solid var(--card-line); padding: 9px 20px; border-radius: 10px; font-weight: 600; font-size: 0.85rem; color: var(--ink); }
        .btn-refresh:hover { background: #f1f5f9; }
        .btn-export { background: linear-gradient(135deg, #1f9d6a, #34b380); border: none; padding: 9px 20px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; color: white; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-export:hover { color: white; opacity: 0.92; }

        .btn-action-edit, .btn-action-delete, .btn-action-resend, .btn-action-approve, .btn-action-checkstatus {
            border: none; padding: 7px 14px; border-radius: 40px; font-weight: 700; font-size: 0.72rem; transition: 0.2s;
            display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; margin: 2px;
        }
        .btn-action-edit { background: rgba(184,134,58,0.1); color: var(--gold); }
        .btn-action-edit:hover { background: var(--gold); color: white; }
        .btn-action-delete { background: rgba(220,53,69,0.1); color: #dc3545; }
        .btn-action-delete:hover { background: #dc3545; color: white; }
        .btn-action-resend { background: rgba(42,111,219,0.1); color: #2a6fdb; }
        .btn-action-resend:hover { background: #2a6fdb; color: white; }
        .btn-action-approve { background: rgba(15,157,106,0.1); color: var(--teal); }
        .btn-action-approve:hover { background: var(--teal); color: white; }
        .btn-action-checkstatus { background: rgba(139,92,246,0.1); color: #8b5cf6; }
        .btn-action-checkstatus:hover { background: #8b5cf6; color: white; }

        .fullscreen-hint { position: fixed; top: 90px; left: 50%; transform: translateX(-50%); background: rgba(34,32,28,0.9); color: white; padding: 10px 22px; border-radius: 40px; font-size: 0.85rem; font-weight: 600; z-index: 200; box-shadow: 0 10px 24px rgba(0,0,0,0.2); }

        /* --- Manage Donations workspace (Quick Entry pane) --- */
        .qe-page-header { margin-bottom: 16px; }
        .qe-page-header h2 { font-size: 1.4rem; font-weight: 800; color: var(--ink); margin: 0 0 3px; }
        .qe-page-header p { color: var(--muted); font-size: 0.87rem; margin: 0; }

        .qe-grid { display: grid; grid-template-columns: 1fr; gap: 18px; align-items: start; }
        @media (min-width: 992px) { .qe-grid { grid-template-columns: 1.9fr 1fr; } }

        .qe-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .qe-card-header .icon-circle { width: 42px; height: 42px; border-radius: 50%; background: rgba(184,134,58,0.12); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
        .qe-card-header h3 { margin: 0; font-size: 1.02rem; font-weight: 800; color: var(--ink); }
        .qe-card-header p { margin: 0; font-size: 0.78rem; color: var(--muted); }

        .qe-checkbox-field { display: flex; align-items: flex-start; gap: 10px; padding: 14px 16px; background: #f7f9fb; border: 1px solid #e3e8ee; border-radius: 8px; margin-bottom: 1.1rem; }
        .qe-checkbox-field input[type="checkbox"] { width: 20px; height: 20px; margin-top: 2px; accent-color: var(--gold); flex-shrink: 0; cursor: pointer; }
        .qe-checkbox-field label { font-weight: 700; font-size: 0.88rem; color: var(--ink); cursor: pointer; margin: 0; }
        .qe-checkbox-field .qe-checkbox-note { display: block; font-weight: 400; font-size: 0.78rem; color: var(--muted); margin-top: 2px; }

        .qe-qty-wrap { display: flex; align-items: center; gap: 10px; margin-top: 10px; max-width: 200px; }
        .qe-qty-wrap label { font-size: 0.8rem; font-weight: 700; color: var(--muted); margin: 0; white-space: nowrap; }

        .qe-actions { display: flex; gap: 12px; margin-top: 22px; }
        .qe-btn-reset { flex: 0 0 auto; padding: 14px 26px; border-radius: 8px; border: 1.5px solid #dde3ea; background: #fff; color: var(--muted); font-weight: 700; font-size: 0.95rem; }
        .qe-btn-reset:hover { background: #f7f9fb; }
        .qe-btn-save { flex: 1; padding: 14px 26px; border-radius: 8px; border: none; background: linear-gradient(135deg, var(--gold), var(--gold-light)); color: white; font-weight: 800; font-size: 1.02rem; box-shadow: 0 8px 20px rgba(184,134,58,0.28); }
        .qe-btn-save:disabled { opacity: 0.6; }

        .qe-summary-card h4 { font-size: 0.98rem; font-weight: 800; margin: 0 0 14px; display: flex; align-items: center; gap: 8px; color: var(--ink); }
        .qe-summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .qe-summary-tile { background: #f7f9fb; border: 1px solid #e3e8ee; border-radius: 8px; padding: 14px; min-width: 0; }
        .qe-summary-tile .label { display: flex; align-items: center; gap: 5px; color: var(--muted); font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px; }
        .qe-summary-tile .value { font-size: 1.1rem; font-weight: 800; color: var(--ink); overflow-wrap: break-word; }
        .qe-view-pending-btn { display: block; width: 100%; text-align: center; margin-top: 4px; padding: 10px; border-radius: 8px; border: 1.5px solid #e3d9bf; background: #fff; color: var(--gold); font-weight: 700; font-size: 0.85rem; cursor: pointer; }
        .qe-view-pending-btn:hover { background: #fbf6ea; }

        .qe-quote-card { background: linear-gradient(135deg, #fdf6ea, #fbeed6); border: 1px solid #f0e2c4; border-radius: 10px; padding: 20px; text-align: center; margin-top: 16px; }
        .qe-quote-card i { font-size: 1.3rem; color: var(--gold); margin-bottom: 8px; display: block; }
        .qe-quote-card p { font-style: italic; color: #7a6a4a; font-size: 0.86rem; margin: 0; line-height: 1.5; }

        .qe-recent-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .qe-recent-header h4 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ink); }
        .qe-recent-controls { display: flex; gap: 8px; flex-wrap: wrap; }
        .qe-search-input, .qe-filter-select { padding: 9px 14px; border-radius: 8px; border: 1.5px solid #d5dce4; font-size: 0.85rem; }
        .qe-search-input { min-width: 220px; }
    </style>
</head>
<body>
    <div class="console-topbar">
        <div class="topbar-left">
            <div class="topbar-brand">
                @if($temple['logo'] ?? null)<img src="{{ $temple['logo'] }}" class="topbar-logo" alt="">@endif
                <div>
                    <div class="topbar-temple-name">{{ $temple['name'] ?? 'Temple' }}</div>
                    @if($temple['subtitle'] ?? null)<div class="topbar-temple-sub">{{ $temple['subtitle'] }}</div>@endif
                </div>
            </div>
            <div class="topbar-divider"></div>
            <div>
                @php $backRoute = session('active_role', auth()->user()->role ?? null) === 'Event Coordinator' ? 'event-coordinator.my-events' : 'admin.events.index'; @endphp
                <a href="{{ route($backRoute) }}" class="back-link"><i class="bi bi-arrow-left"></i>Back to {{ $backRoute === 'event-coordinator.my-events' ? 'My Events' : 'Events' }}</a>
                <h1>{{ $event->event_name }}</h1>
            </div>
        </div>
        <div class="console-tabs">
            @if($canAddDonation)
            <button type="button" class="console-tab-btn active" data-pane="pane-entry"><i class="bi bi-lightning-charge-fill"></i> Quick Entry</button>
            @endif
            <button type="button" class="console-tab-btn {{ $canAddDonation ? '' : 'active' }}" data-pane="pane-table"><i class="bi bi-table"></i> Donations</button>
        </div>
        <button type="button" class="btn-fullscreen" id="fullscreenBtn"><i class="bi bi-arrows-fullscreen me-1"></i>Fullscreen</button>
    </div>

    <div class="console-body">
        <!-- DONATIONS TABLE (dashboard stats live on top so there's no separate Dashboard tab) -->
        <div class="console-pane {{ $canAddDonation ? '' : 'active' }}" id="pane-table">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:var(--teal);"><i class="bi bi-cash-coin"></i></div><div class="stat-text"><div class="label">Paid Total</div><div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['paid_total'], 2) }}</div></div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:#e0a638;"><i class="bi bi-hourglass-split"></i></div><div class="stat-text"><div class="label">Pending Total</div><div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['pending_total'], 2) }}</div></div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:var(--gold);"><i class="bi bi-check2-circle"></i></div><div class="stat-text"><div class="label">Paid Donations</div><div class="value">{{ $summary['paid_count'] }}</div></div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:#8b5cf6;"><i class="bi bi-people-fill"></i></div><div class="stat-text"><div class="label">Total Donations</div><div class="value">{{ $summary['donation_count'] }}</div></div></div>
                </div>
            </div>
            @if($options->count())
            <div class="option-breakdown-scroll">
                @foreach($options as $opt)
                <div class="option-chip-stat">
                    <div class="label">{{ $opt->label }}</div>
                    <div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['option_totals'][$opt->id] ?? 0, 2) }}</div>
                </div>
                @endforeach
            </div>
            @endif
            <div class="d-flex justify-content-end gap-2 mb-2">
                <a href="{{ route('admin.donations.export', ['event_id' => $event->event_id]) }}" class="btn-export"><i class="bi bi-file-earmark-excel-fill"></i>Export to Excel</a>
                <button type="button" class="btn-refresh" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
            </div>
            <div class="console-card" style="padding:0;">
                <div class="table-scroll-wrap">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>Type</th><th>ID</th><th>Name</th><th>Contact</th>
                            @foreach($options as $opt)<th class="col-amount">{{ $opt->label }}</th>@endforeach
                            <th class="col-amount">Other</th><th class="col-amount">Total</th><th>Payment</th><th>Txn ID</th><th>Date</th><th>Status</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->donation_type === 'devotee' ? 'Devotee' : 'Guest' }}</td>
                            <td><strong>{{ $row->display_id }}</strong></td>
                            <td class="col-name">{{ $row->display_name }}</td>
                            <td>
                                @if($row->mobile)<div>{{ $row->mobile }}</div>@endif
                                @if($row->email)<div class="text-muted" style="font-size:0.78rem;">{{ $row->email }}</div>@endif
                                @if(!$row->mobile && !$row->email)—@endif
                            </td>
                            @foreach($options as $opt)
                            <td class="col-amount">@if(($row->option_amounts[$opt->id] ?? 0) > 0){{ number_format($row->option_amounts[$opt->id], 2) }}@else — @endif</td>
                            @endforeach
                            <td class="col-amount">@if($row->other_amount > 0){{ number_format($row->other_amount, 2) }}@else — @endif</td>
                            <td class="col-amount total">{{ number_format($row->amount, 2) }}</td>
                            <td>{{ $row->payment_method }}</td>
                            <td class="col-txn" title="{{ $row->transaction_id }}">{{ $row->transaction_id ?: '—' }}</td>
                            <td>{{ date('d M Y', strtotime($row->donation_date)) }}</td>
                            <td><span class="status-pill status-{{ strtolower($row->payment_status) }}">{{ $row->payment_status }}</span></td>
                            <td class="text-end">
                                @include('admin.partials.donation-actions', ['row' => $row])
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ 8 + $options->count() }}" class="text-center text-muted py-5">No donations recorded for this event yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        @if($canAddDonation)
        <!-- QUICK ENTRY / MANAGE DONATIONS WORKSPACE (default pane; Guest is the default mode) -->
        <div class="console-pane active" id="pane-entry">
            <div class="qe-page-header">
                <h2>Manage Donations</h2>
                <p>Add, view and manage donations for {{ $event->event_name }}</p>
            </div>

            <div class="qe-grid">
                <div>
                    <div class="console-card">
                        <div class="qe-card-header">
                            <div class="icon-circle"><i class="bi bi-person-plus-fill"></i></div>
                            <div>
                                <h3>Log New Donation</h3>
                                <p>Record a new donation received</p>
                            </div>
                        </div>

                        <div class="quick-entry-toggle">
                            <button type="button" id="qeToggleDevotee"><i class="bi bi-person-check-fill me-1"></i>Existing Devotee</button>
                            <button type="button" class="active" id="qeToggleGuest"><i class="bi bi-person-heart me-1"></i>Guest</button>
                        </div>

                        <div class="qe-panel">
                            <div class="qe-panel-title"><span class="bar" style="background:var(--gold);"></span>Donor Details</div>

                            <div id="qeDevoteeFields" style="display:none;">
                                <div class="qe-field devotee-combobox-wrap">
                                    <div class="qe-input-group">
                                        <span class="qe-input-label">Search Devotee</span>
                                        <input type="text" id="qeDevoteeSearch" placeholder="Name, email, or mobile...">
                                    </div>
                                    <input type="hidden" id="qeDevoteeId">
                                    <div class="devotee-combobox-results" id="qeDevoteeResults"></div>
                                </div>
                            </div>

                            <div id="qeGuestFields">
                                <div class="row g-3">
                                    <div class="col-md-6 qe-field">
                                        <div class="qe-input-group">
                                            <span class="qe-input-label">Donor Name</span>
                                            <input type="text" id="qeGuestName" placeholder="Full name">
                                        </div>
                                    </div>
                                    <div class="col-md-6 qe-field">
                                        <div class="qe-input-group">
                                            <span class="qe-input-label">Email</span>
                                            <input type="email" id="qeGuestEmail" placeholder="Optional">
                                        </div>
                                    </div>
                                </div>
                                <div class="qe-field">
                                    <div class="qe-input-group">
                                        <span class="qe-input-label">Mobile</span>
                                        <input type="text" id="qeGuestMobile" placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="qe-panel">
                            <div class="qe-panel-title"><span class="bar" style="background:var(--teal);"></span>Donation Details</div>

                            <div class="qe-field">
                                <div class="qe-input-group">
                                    <span class="qe-input-label">Donation Type</span>
                                    <select id="qeDonationType"></select>
                                </div>
                                <div class="qe-qty-wrap" id="qeQtyWrap" style="display:none;">
                                    <label>Quantity</label>
                                    <input type="number" min="1" value="1" class="form-control" id="qeQty">
                                </div>
                            </div>

                            <div class="qe-field">
                                <div class="qe-input-group">
                                    <span class="qe-input-label">Amount</span>
                                    <input type="number" step="0.01" id="qeAmount" placeholder="0.00">
                                </div>
                                <div class="quick-amount-row" id="qeQuickAmounts"></div>
                            </div>

                            <div class="qe-checkbox-field">
                                <input type="checkbox" id="qeGeneralDonation">
                                <label for="qeGeneralDonation">General Donation (Optional)<span class="qe-checkbox-note">Mark as general donation (not for a specific purpose)</span></label>
                            </div>
                        </div>

                        <div class="qe-panel">
                            <div class="qe-panel-title"><span class="bar" style="background:#8b5cf6;"></span>Payment Details</div>
                            <div class="row g-3">
                                <div class="col-md-6 qe-field">
                                    <div class="qe-input-group">
                                        <span class="qe-input-label">Method</span>
                                        <select id="qePaymentMethod"></select>
                                    </div>
                                </div>
                                <div class="col-md-6 qe-field">
                                    <div class="qe-input-group">
                                        <span class="qe-input-label">Reference</span>
                                        <input type="text" id="qeTransactionId" placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 qe-field">
                                    <div class="qe-input-group">
                                        <span class="qe-input-label">Date</span>
                                        <input type="date" id="qeDonationDate">
                                    </div>
                                </div>
                                <div class="col-md-6 qe-field">
                                    <div class="qe-input-group">
                                        <span class="qe-input-label">Status</span>
                                        <select id="qePaymentStatus">
                                            <option value="Paid">Received</option>
                                            <option value="Pending">Pending</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="qe-panel">
                            <div class="qe-panel-title"><span class="bar" style="background:var(--muted);"></span>Additional Information</div>
                            <div class="qe-field">
                                <div class="qe-input-group">
                                    <span class="qe-input-label">Notes</span>
                                    <textarea id="qeDetails" rows="2" placeholder="Optional — e.g. in memory of..., family name, special request..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="qe-actions">
                            <button type="button" class="qe-btn-reset" id="qeResetBtn"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                            <button type="button" class="qe-btn-save" id="qeSaveBtn"><i class="bi bi-lightning-charge-fill me-2"></i>Save &amp; Next</button>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="console-card qe-summary-card">
                        <h4><i class="bi bi-pie-chart-fill" style="color:var(--gold);"></i>Donation Summary</h4>
                        <div class="qe-summary-grid">
                            <div class="qe-summary-tile">
                                <div class="label"><i class="bi bi-cash-coin"></i>Total Collected</div>
                                <div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['paid_total'], 2) }}</div>
                            </div>
                            <div class="qe-summary-tile">
                                <div class="label"><i class="bi bi-people-fill"></i>Total Donors</div>
                                <div class="value">{{ $summary['total_donors'] }}</div>
                            </div>
                            <div class="qe-summary-tile">
                                <div class="label"><i class="bi bi-calendar-check"></i>Today's Collection</div>
                                <div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['today_total'], 2) }}</div>
                            </div>
                            <div class="qe-summary-tile">
                                <div class="label"><i class="bi bi-hourglass-split"></i>Pending Transfers</div>
                                <div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['pending_total'], 2) }}</div>
                            </div>
                        </div>
                        <button type="button" class="qe-view-pending-btn" onclick='document.querySelector(".console-tab-btn[data-pane=pane-table]").click()'>View Pending ({{ $summary['pending_count'] }})</button>
                    </div>

                    <div class="qe-quote-card">
                        <i class="bi bi-flower1"></i>
                        <p>&ldquo;A small contribution creates a lasting legacy.&rdquo;</p>
                    </div>
                </div>
            </div>

            <div class="console-card mt-3">
                <div class="qe-recent-header">
                    <h4><i class="bi bi-clock-history me-1" style="color:var(--gold);"></i>Recent Donations</h4>
                    <div class="qe-recent-controls">
                        <input type="text" class="qe-search-input" id="qeRecentSearch" placeholder="Search by name, email, mobile...">
                        <select class="qe-filter-select" id="qeRecentFilter">
                            <option value="">All Donations</option>
                            <option value="Paid">Received</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                </div>
                <div class="table-scroll-wrap" style="max-height:420px;">
                <table class="console-table" id="qeRecentTable">
                    <thead>
                        <tr>
                            <th>Date</th><th>Donor Name</th><th>Type</th><th class="col-amount">Amount</th><th>Payment Method</th><th>Status</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows->take(25) as $row)
                        <tr data-status="{{ $row->payment_status }}" data-search="{{ strtolower($row->display_name . ' ' . ($row->email ?? '') . ' ' . ($row->mobile ?? '')) }}">
                            <td>{{ date('d M Y', strtotime($row->donation_date)) }}</td>
                            <td class="col-name">{{ $row->display_name }}</td>
                            <td>{{ $row->donation_type === 'devotee' ? 'Devotee' : 'Guest' }}</td>
                            <td class="col-amount total">{{ $temple['currency'] ?? '' }} {{ number_format($row->amount, 2) }}</td>
                            <td>{{ $row->payment_method }}</td>
                            <td><span class="status-pill status-{{ strtolower($row->payment_status) }}">{{ $row->payment_status === 'Paid' ? 'Received' : $row->payment_status }}</span></td>
                            <td class="text-end">@include('admin.partials.donation-actions', ['row' => $row])</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">No donations recorded for this event yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @if($rows->count() > 25)
                <div class="text-center mt-3">
                    <button type="button" class="btn-refresh" onclick='document.querySelector(".console-tab-btn[data-pane=pane-table]").click()'>View All {{ $rows->count() }} Donations</button>
                </div>
                @endif
            </div>
        </div>
        @endif

    </div>

    <div class="qe-toast" id="qeToast"></div>

    <!-- EDIT MODALS (devotee + guest) — same fields as the main Manage Donations page -->
    @if($canEditDonation)
        @foreach($rows->where('donation_type', 'devotee') as $row)
        <div class="modal fade" id="editDevoteeDonationModal{{ $row->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form action="{{ route('admin.donations.updateDevotee', $row->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Devotee Donation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Devotee</label>
                                <input type="text" class="form-control rounded-3" value="{{ $row->display_name }}" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donation Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control rounded-3" value="{{ $row->amount }}" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Mode</label>
                                    <select name="payment_mode" class="form-select rounded-3" required>
                                        @foreach(['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'Stripe'] as $mode)
                                            <option value="{{ $mode }}" {{ $row->payment_method === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Status</label>
                                    <select name="payment_status" class="form-select rounded-3" required>
                                        @foreach(['Paid', 'Pending', 'Cancelled', 'Failed'] as $status)
                                            <option value="{{ $status }}" {{ $row->payment_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="event_id" value="{{ $event->event_id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control rounded-3" value="{{ $row->transaction_id }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donation Date</label>
                                <input type="date" name="donation_date" class="form-control rounded-3" value="{{ $row->donation_date }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donation Option / Purpose</label>
                                <input type="text" name="purpose" class="form-control rounded-3" value="{{ $row->purpose }}">
                                @if($options->count())
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($options as $opt)
                                    <span class="option-chip" style="background:#faf5eb;border:1px solid #f0ece6;color:#7b6b5a;font-size:.75rem;padding:4px 12px;border-radius:40px;cursor:pointer;" onclick="appendOptionChipEc(this, 'input[name=purpose]')">{{ $opt->label }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Details / Remarks</label>
                                <textarea name="remarks" rows="2" class="form-control rounded-3">{{ $row->remarks }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                            <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endforeach

        @foreach($rows->where('donation_type', 'guest') as $row)
        <div class="modal fade" id="editGuestDonationModal{{ $row->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form action="{{ route('admin.donations.updateGuest', $row->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Guest Donation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donor Full Name</label>
                                <input type="text" name="donor_name" class="form-control rounded-3" value="{{ $row->donor_name }}" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input type="email" name="email" class="form-control rounded-3" value="{{ $row->email }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Mobile</label>
                                    <input type="text" name="mobile" class="form-control rounded-3" value="{{ $row->mobile }}">
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Donation Amount</label>
                                    <input type="number" step="0.01" name="amount" class="form-control rounded-3" value="{{ $row->amount }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Donation Date</label>
                                    <input type="date" name="donation_date" class="form-control rounded-3" value="{{ $row->donation_date }}" required>
                                </div>
                            </div>
                            <input type="hidden" name="event_id" value="{{ $event->event_id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Purpose</label>
                                <input type="text" name="purpose" class="form-control rounded-3" value="{{ $row->purpose }}" required>
                                @if($options->count())
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($options as $opt)
                                    <span class="option-chip" style="background:#faf5eb;border:1px solid #f0ece6;color:#7b6b5a;font-size:.75rem;padding:4px 12px;border-radius:40px;cursor:pointer;" onclick="appendOptionChipEc(this, 'input[name=purpose]')">{{ $opt->label }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Details / Dedication</label>
                                <textarea name="purpose_details" rows="2" class="form-control rounded-3">{{ $row->purpose_details }}</textarea>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Method</label>
                                    <select name="payment_method" class="form-select rounded-3" required>
                                        @foreach(['Cash', 'UPI', 'Bank', 'Stripe'] as $method)
                                            <option value="{{ $method }}" {{ $row->payment_method === $method ? 'selected' : '' }}>{{ $method }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Status</label>
                                    <select name="payment_status" class="form-select rounded-3" required>
                                        @foreach(['Paid', 'Pending', 'Cancelled', 'Failed'] as $status)
                                            <option value="{{ $status }}" {{ $row->payment_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control rounded-3" value="{{ $row->transaction_id }}">
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                            <button type="submit" class="btn btn-primary text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #2a6fdb, #548ee8); border:none;">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    @endif

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        function appendOptionChipEc(chipEl, selector) {
            const scope = chipEl.closest('.modal-body') || document;
            const input = scope.querySelector(selector);
            if (!input) { return; }
            const label = chipEl.textContent.trim();
            const existing = input.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            if (!existing.includes(label)) { existing.push(label); }
            input.value = existing.join(', ');
        }

        // Tab switching
        document.querySelectorAll('.console-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.console-tab-btn').forEach(function (b) { b.classList.remove('active'); });
                document.querySelectorAll('.console-pane').forEach(function (p) { p.classList.remove('active'); });
                this.classList.add('active');
                document.getElementById(this.dataset.pane).classList.add('active');
            });
        });

        // Fullscreen: explicit button, plus a one-tap-anywhere fallback so the console
        // effectively "opens in fullscreen" as soon as the admin starts using it — browsers
        // block requestFullscreen() from firing automatically on page load with no user
        // gesture, so the very first tap on the page is used to satisfy that requirement.
        function goFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(function () {});
            }
        }
        document.getElementById('fullscreenBtn').addEventListener('click', function () {
            if (!document.fullscreenElement) { goFullscreen(); } else { document.exitFullscreen(); }
        });
        if (!document.fullscreenElement) {
            const hint = document.createElement('div');
            hint.className = 'fullscreen-hint';
            hint.textContent = 'Tap anywhere to enter fullscreen';
            document.body.appendChild(hint);
            document.body.addEventListener('click', function onceHandler() {
                goFullscreen();
                hint.remove();
                document.body.removeEventListener('click', onceHandler);
            }, { once: true });
            setTimeout(function () { hint.remove(); }, 4000);
        }

        @if($canAddDonation)
        const DEVOTEES = @json($devotees);
        const EVENT_OPTIONS = @json($eventOptionsForJs);
        const ENABLED_PAYMENT_METHODS = @json($enabledPaymentMethods);
        const CSRF_TOKEN = @json(csrf_token());
        const STORE_DEVOTEE_URL = @json(route('admin.events.console.storeDevotee', $event->event_id));
        const STORE_GUEST_URL = @json(route('admin.events.console.storeGuest', $event->event_id));
        const EVENT_ID = {{ $event->event_id }};
        const QUICK_AMOUNTS = [101, 501, 1001, 2001];

        let qeMode = 'guest';
        const toggleDevoteeBtn = document.getElementById('qeToggleDevotee');
        const toggleGuestBtn = document.getElementById('qeToggleGuest');
        const devoteeFields = document.getElementById('qeDevoteeFields');
        const guestFields = document.getElementById('qeGuestFields');

        toggleDevoteeBtn.addEventListener('click', function () {
            qeMode = 'devotee';
            toggleDevoteeBtn.classList.add('active');
            toggleGuestBtn.classList.remove('active');
            devoteeFields.style.display = '';
            guestFields.style.display = 'none';
        });
        toggleGuestBtn.addEventListener('click', function () {
            qeMode = 'guest';
            toggleGuestBtn.classList.add('active');
            toggleDevoteeBtn.classList.remove('active');
            guestFields.style.display = '';
            devoteeFields.style.display = 'none';
        });

        // Payment method select, respecting the configured enabled list.
        const paymentSelect = document.getElementById('qePaymentMethod');
        (ENABLED_PAYMENT_METHODS.length ? ENABLED_PAYMENT_METHODS : ['Cash']).forEach(function (m) {
            const opt = document.createElement('option');
            opt.value = m === 'Bank Transfer' ? 'Bank Transfer' : m;
            opt.textContent = m;
            paymentSelect.appendChild(opt);
        });

        // Devotee search combobox — client-side filter over a pre-loaded array.
        const devoteeSearch = document.getElementById('qeDevoteeSearch');
        const devoteeResults = document.getElementById('qeDevoteeResults');
        const devoteeIdInput = document.getElementById('qeDevoteeId');

        devoteeSearch.addEventListener('input', function () {
            devoteeIdInput.value = '';
            const q = this.value.trim().toLowerCase();
            if (q.length < 2) { devoteeResults.classList.remove('show'); return; }
            const matches = DEVOTEES.filter(function (d) {
                return (d.name && d.name.toLowerCase().includes(q))
                    || (d.email && d.email.toLowerCase().includes(q))
                    || (d.mobile && d.mobile.toLowerCase().includes(q));
            }).slice(0, 15);
            if (!matches.length) { devoteeResults.classList.remove('show'); return; }
            devoteeResults.innerHTML = matches.map(function (d) {
                return '<div class="devotee-combobox-item" data-id="' + d.devotee_id + '" data-name="' + escapeHtmlQe(d.name) + '">'
                    + '<div class="fw-semibold">' + escapeHtmlQe(d.name) + '</div>'
                    + '<div class="text-muted small">' + escapeHtmlQe(d.email || '') + (d.mobile ? ' · ' + escapeHtmlQe(d.mobile) : '') + '</div></div>';
            }).join('');
            devoteeResults.classList.add('show');
        });
        devoteeResults.addEventListener('click', function (e) {
            const item = e.target.closest('.devotee-combobox-item');
            if (!item) { return; }
            devoteeIdInput.value = item.dataset.id;
            devoteeSearch.value = item.dataset.name;
            devoteeResults.classList.remove('show');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.devotee-combobox-wrap')) { devoteeResults.classList.remove('show'); }
        });

        function escapeHtmlQe(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        // Quick-amount preset buttons for the manual Amount field.
        const amountInput = document.getElementById('qeAmount');
        const quickAmountsRow = document.getElementById('qeQuickAmounts');
        QUICK_AMOUNTS.forEach(function (amt) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'quick-amount-btn';
            btn.textContent = amt;
            btn.addEventListener('click', function () {
                amountInput.value = amt.toFixed(2);
                amountInput.dispatchEvent(new Event('input'));
                quickAmountsRow.querySelectorAll('.quick-amount-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
            });
            quickAmountsRow.appendChild(btn);
        });
        amountInput.addEventListener('input', function () {
            quickAmountsRow.querySelectorAll('.quick-amount-btn').forEach(function (b) {
                b.classList.toggle('active', parseFloat(b.textContent) === parseFloat(amountInput.value));
            });
        });

        // Donation Type — a single dropdown of the event's configured options (backend-driven,
        // same EVENT_OPTIONS data the old multi-tier checkboxes used) plus a "General Donation"
        // default. Only one type is picked at a time, feeding the one Amount field/quick-amount
        // row above — no per-type duplicate controls.
        const donationTypeSelect = document.getElementById('qeDonationType');
        const qtyWrap = document.getElementById('qeQtyWrap');
        const qtyInput = document.getElementById('qeQty');
        const generalDonationCheckbox = document.getElementById('qeGeneralDonation');
        let selections = [];
        let purposeValue = 'Event Donation';

        (function populateDonationTypes() {
            const generalOpt = document.createElement('option');
            generalOpt.value = '';
            generalOpt.textContent = 'General Donation';
            donationTypeSelect.appendChild(generalOpt);
            EVENT_OPTIONS.forEach(function (opt, idx) {
                const o = document.createElement('option');
                o.value = idx;
                o.textContent = opt.label + (opt.amount !== null ? ' (' + opt.amount.toFixed(2) + (opt.allow_quantity ? ' each)' : ')') : ' (any amount)');
                donationTypeSelect.appendChild(o);
            });
        })();

        function currentOption() {
            if (generalDonationCheckbox.checked || donationTypeSelect.value === '') { return null; }
            return EVENT_OPTIONS[donationTypeSelect.value];
        }

        // Recompute the submitted purpose/selection whenever the type, qty, or amount changes.
        function recalcDonationType() {
            const opt = currentOption();
            const amount = parseFloat(amountInput.value) || 0;

            if (!opt) {
                qtyWrap.style.display = 'none';
                purposeValue = 'Event Donation';
                selections = [];
                return;
            }

            const showQty = opt.allow_quantity && opt.amount !== null;
            qtyWrap.style.display = showQty ? 'flex' : 'none';
            const qty = showQty ? (parseInt(qtyInput.value, 10) || 1) : null;

            purposeValue = opt.label + (showQty && qty > 1 ? ' (x' + qty + ')' : '');
            selections = amount > 0 ? [{ option_id: opt.id, label: purposeValue, quantity: qty, amount: amount }] : [];
        }

        // Selecting a type (or changing qty) pre-fills the Amount field for a fixed-price
        // option — the admin can still override it manually afterwards.
        donationTypeSelect.addEventListener('change', function () {
            const opt = currentOption();
            if (opt && opt.amount !== null) {
                qtyInput.value = 1;
                amountInput.value = opt.amount.toFixed(2);
                amountInput.dispatchEvent(new Event('input'));
            }
            recalcDonationType();
        });
        qtyInput.addEventListener('input', function () {
            const opt = currentOption();
            if (opt && opt.amount !== null) {
                amountInput.value = (opt.amount * (parseInt(qtyInput.value, 10) || 1)).toFixed(2);
                amountInput.dispatchEvent(new Event('input'));
            }
            recalcDonationType();
        });
        amountInput.addEventListener('input', recalcDonationType);

        // The "General Donation" checkbox is a shortcut for picking no specific type — it
        // locks the Donation Type dropdown back to General while checked.
        generalDonationCheckbox.addEventListener('change', function () {
            donationTypeSelect.disabled = this.checked;
            if (this.checked) { donationTypeSelect.value = ''; }
            recalcDonationType();
        });

        function showToast(message, isError) {
            const toast = document.getElementById('qeToast');
            toast.textContent = message;
            toast.classList.toggle('error', !!isError);
            toast.style.display = 'block';
            setTimeout(function () { toast.style.display = 'none'; }, 2500);
        }

        const donationDateInput = document.getElementById('qeDonationDate');
        const paymentStatusSelect = document.getElementById('qePaymentStatus');

        function resetQuickEntry() {
            devoteeSearch.value = '';
            devoteeIdInput.value = '';
            document.getElementById('qeGuestName').value = '';
            document.getElementById('qeGuestEmail').value = '';
            document.getElementById('qeGuestMobile').value = '';
            document.getElementById('qeTransactionId').value = '';
            document.getElementById('qeDetails').value = '';
            amountInput.value = '';
            quickAmountsRow.querySelectorAll('.quick-amount-btn').forEach(function (b) { b.classList.remove('active'); });
            donationTypeSelect.value = '';
            donationTypeSelect.disabled = false;
            generalDonationCheckbox.checked = false;
            qtyInput.value = 1;
            qtyWrap.style.display = 'none';
            donationDateInput.value = new Date().toISOString().slice(0, 10);
            paymentStatusSelect.value = 'Paid';
            selections = [];
            purposeValue = 'Event Donation';
        }
        resetQuickEntry();
        document.getElementById('qeResetBtn').addEventListener('click', resetQuickEntry);

        document.getElementById('qeSaveBtn').addEventListener('click', function () {
            const amount = parseFloat(amountInput.value);
            if (!amount || amount <= 0) { showToast('Enter a valid amount.', true); return; }

            const btn = this;
            btn.disabled = true;

            const body = new URLSearchParams();
            body.set('event_id', EVENT_ID);
            body.set('amount', amount.toFixed(2));
            body.set('transaction_id', document.getElementById('qeTransactionId').value);
            body.set('selections_json', JSON.stringify(selections));
            body.set('donation_date', donationDateInput.value || new Date().toISOString().slice(0, 10));
            body.set('payment_status', paymentStatusSelect.value);

            let url;
            if (qeMode === 'devotee') {
                if (!devoteeIdInput.value) { showToast('Search and select a devotee first.', true); btn.disabled = false; return; }
                url = STORE_DEVOTEE_URL;
                body.set('devotee_id', devoteeIdInput.value);
                // Devotee donations only accept Cash/UPI/Bank Transfer/Cheque.
                body.set('payment_mode', paymentSelect.value);
                body.set('purpose', purposeValue);
                body.set('remarks', document.getElementById('qeDetails').value);
            } else {
                const name = document.getElementById('qeGuestName').value.trim();
                if (!name) { showToast('Enter the donor name.', true); btn.disabled = false; return; }
                url = STORE_GUEST_URL;
                body.set('donor_name', name);
                body.set('email', document.getElementById('qeGuestEmail').value);
                body.set('mobile', document.getElementById('qeGuestMobile').value);
                // Guest donations only accept Cash/UPI/Bank — translate the shared payment
                // method list's "Bank Transfer" label to the value this route actually accepts.
                body.set('payment_method', paymentSelect.value === 'Bank Transfer' ? 'Bank' : paymentSelect.value);
                body.set('purpose', purposeValue);
                body.set('purpose_details', document.getElementById('qeDetails').value);
            }

            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    btn.disabled = false;
                    if (result.status >= 200 && result.status < 300 && result.data.success) {
                        showToast(result.data.message || 'Saved.');
                        resetQuickEntry();
                    } else {
                        showToast(result.data.message || 'Failed to save.', true);
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    showToast('Network error — please try again.', true);
                });
        });

        // Recent Donations: client-side search + status filter over the already-rendered rows.
        (function () {
            const searchInput = document.getElementById('qeRecentSearch');
            const filterSelect = document.getElementById('qeRecentFilter');
            const rows = document.querySelectorAll('#qeRecentTable tbody tr[data-search]');
            function applyFilters() {
                const q = searchInput.value.trim().toLowerCase();
                const status = filterSelect.value;
                rows.forEach(function (row) {
                    const matchesSearch = !q || row.dataset.search.includes(q);
                    const matchesStatus = !status || row.dataset.status === status;
                    row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
                });
            }
            if (searchInput && filterSelect) {
                searchInput.addEventListener('input', applyFilters);
                filterSelect.addEventListener('change', applyFilters);
            }
        })();
        @endif
    </script>
</body>
</html>
