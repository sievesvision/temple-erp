<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket Console</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/ibm-plex-mono/ibm-plex-mono.css') }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A; --maroon-dark: #4A0A12; --gold: #C89B3C; --gold-hover: #A67C2B;
            --cream: #F9F3E7; --white: #FFFFFF; --border: #F0E5D6; --text-primary: #1F2A37;
            --text-secondary: #6B7280; --success: #10B981; --warning: #F59E0B; --error: #EF4444;
            --success-bg: #ECFDF5; --pending-bg: #FFF7ED; --error-bg: #FEF2F2;
            --serif: 'Playfair Display', Georgia, serif;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { overflow-x: hidden; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; background: var(--cream); color: var(--text-primary); }
        h1, h2, h3, h4 { font-family: var(--serif); }
        button, input, select, textarea { font-family: inherit; }

        .app-shell-wrap { display: flex; flex-direction: column; height: 100vh; }
        .app-shell { flex: 1; min-height: 0; display: flex; }
        .app-sidebar { width: 240px; flex-shrink: 0; background: var(--white); border-right: 1px solid var(--border); display: flex; flex-direction: column; justify-content: space-between; overflow-y: auto; transition: transform 0.25s ease; }
        .sidebar-nav { padding: 20px 14px; display: flex; flex-direction: column; gap: 4px; }
        .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; border: none; background: none; color: var(--text-secondary); font-weight: 600; font-size: 0.92rem; text-decoration: none; text-align: left; cursor: pointer; transition: 0.15s; }
        .sidebar-link i { font-size: 1.05rem; width: 20px; text-align: center; flex-shrink: 0; }
        .sidebar-link:hover { background: var(--cream); color: var(--text-primary); }
        .sidebar-link.active { background: var(--gold); color: white; box-shadow: 0 4px 12px rgba(200,155,60,0.35); }
        .app-main { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow-y: auto; }

        .console-topbar { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 14px 24px; display: flex; align-items: center; gap: 16px; flex-shrink: 0; z-index: 40; box-shadow: 0 4px 18px rgba(74,10,18,0.25); }
        .sidebar-toggle { display: none; background: rgba(255,255,255,0.12); border: none; color: white; width: 38px; height: 38px; border-radius: 10px; font-size: 1.1rem; flex-shrink: 0; }
        .topbar-brand { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .topbar-logo { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; background: #fff; padding: 2px; flex-shrink: 0; }
        .topbar-temple-name { font-weight: 800; font-size: 1rem; line-height: 1.2; font-family: var(--serif); }
        .topbar-temple-sub { font-size: 0.72rem; color: rgba(255,255,255,0.6); line-height: 1.2; }
        .topbar-title { flex: 1; min-width: 0; text-align: center; }
        .topbar-title h1 { font-size: clamp(1.05rem, 2.4vw, 1.6rem); font-weight: 800; color: var(--gold); margin: 0; }
        .topbar-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
        .admin-pill { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.25); color: white; padding: 7px 14px; border-radius: 999px; font-weight: 700; font-size: 0.85rem; }
        .admin-pill:hover, .admin-pill:focus { background: rgba(255,255,255,0.18); color: white; }

        .console-body { padding: 24px clamp(16px, 2.2vw, 32px); max-width: 100%; }
        .console-pane { display: none; }
        .console-pane.active { display: block; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; flex-wrap: wrap; }
        .page-header-icon { width: 46px; height: 46px; border-radius: 50%; background: var(--maroon); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .page-header h2 { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; }
        .page-header p { color: var(--text-secondary); font-size: 0.87rem; margin: 2px 0 0; }
        .page-header-actions { margin-left: auto; display: flex; gap: 10px; flex-wrap: wrap; }

        .card-panel { background: var(--white); border: 1px solid var(--border); border-radius: 14px; padding: 24px; box-shadow: 0 1px 3px rgba(31,42,55,0.04); }
        .field-row { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px; }
        .field-row.two-col { grid-template-columns: 1fr; }
        @media (min-width: 560px) { .field-row.two-col { grid-template-columns: 1fr 1fr; } }
        .field-label { display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-primary); margin-bottom: 6px; }
        .field-group input, .field-group select, .field-group textarea { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.94rem; color: var(--text-primary); background: var(--white); min-height: 46px; }
        .field-group textarea { resize: vertical; min-height: 70px; }
        .field-group input:focus, .field-group select:focus, .field-group textarea:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(200,155,60,0.15); }
        .field-group input[type="color"] { padding: 4px; min-height: 46px; }

        .btn-save { padding: 12px 24px; border-radius: 10px; border: none; background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; font-size: 0.95rem; box-shadow: 0 8px 20px rgba(200,155,60,0.32); }

        .table-scroll-wrap { overflow: auto; border-radius: 10px; }
        table.console-table { width: 100%; border-collapse: collapse; }
        table.console-table th, table.console-table td { padding: 10px 10px; text-align: left; font-size: 0.85rem; border-bottom: 1px solid var(--border); white-space: nowrap; }
        table.console-table th { background: var(--cream); font-weight: 700; color: var(--text-secondary); text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.02em; position: sticky; top: 0; z-index: 5; }
        table.console-table td.col-name { white-space: normal; font-weight: 600; }
        table.console-table td.col-amount, table.console-table th.col-amount { text-align: right; font-family: 'IBM Plex Mono', 'Inter', monospace; font-variant-numeric: tabular-nums; }
        table.console-table td.col-txn { max-width: 110px; overflow: hidden; text-overflow: ellipsis; font-family: 'IBM Plex Mono', monospace; font-size: 0.74rem; color: var(--text-secondary); }
        table.console-table tbody tr:hover { background: var(--cream); }
        .status-pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
        .status-pill.status-paid { background: var(--success-bg); color: var(--success); }
        .status-pill.status-pending { background: var(--pending-bg); color: var(--warning); }
        .status-pill.status-cancelled, .status-pill.status-failed { background: var(--error-bg); color: var(--error); }

        .btn-action-edit, .btn-action-delete, .btn-action-resend, .btn-action-checkstatus { border: none; width: 32px; height: 32px; padding: 0; border-radius: 50%; font-size: 0; transition: 0.15s; display: inline-flex; align-items: center; justify-content: center; margin: 2px; }
        .btn-action-edit i, .btn-action-delete i, .btn-action-resend i, .btn-action-checkstatus i { font-size: 0.92rem; }
        .btn-action-edit { background: rgba(200,155,60,0.14); color: var(--gold-hover); }
        .btn-action-edit:hover { background: var(--gold); color: white; }
        .btn-action-delete { background: var(--error-bg); color: var(--error); }
        .btn-action-delete:hover { background: var(--error); color: white; }
        .btn-action-resend { background: rgba(42,111,219,0.1); color: #2a6fdb; }
        .btn-action-resend:hover { background: #2a6fdb; color: white; }
        .btn-action-checkstatus { background: var(--pending-bg); color: var(--warning); }
        .btn-action-checkstatus:hover { background: var(--warning); color: white; }

        .stat-tile { background: var(--white); border-radius: 14px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(31,42,55,0.04); border: 1px solid var(--border); display: flex; align-items: center; gap: 14px; min-width: 0; }
        .stat-tile .stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: white; flex-shrink: 0; }
        .stat-tile .label { color: var(--text-secondary); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
        .stat-tile .value { font-family: 'IBM Plex Mono', 'Inter', monospace; font-variant-numeric: tabular-nums; font-size: 1.3rem; font-weight: 700; color: var(--text-primary); margin-top: 2px; }

        .ticket-type-card { border: 1.5px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--white); }
        .ticket-type-swatch { height: 60px; background-size: cover; background-position: center; }
        .ticket-type-body { padding: 12px 14px; }

        @media (max-width: 1023px) {
            .sidebar-toggle { display: inline-flex; align-items: center; justify-content: center; }
            .app-sidebar { position: fixed; left: 0; top: 70px; height: calc(100vh - 70px); transform: translateX(-100%); box-shadow: 0 0 40px rgba(0,0,0,0.2); z-index: 50; }
            .app-sidebar.open { transform: translateX(0); }
            .sidebar-backdrop { display: none; position: fixed; left: 0; right: 0; top: 70px; bottom: 0; background: rgba(31,42,55,0.4); z-index: 45; }
            .sidebar-backdrop.show { display: block; }
        }
    </style>
</head>
<body>
    @php $temple = \App\Models\Setting::templeBranding(); @endphp
    <div class="app-shell-wrap">
        <header class="console-topbar">
            <button type="button" class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div class="topbar-brand">
                @if($temple['logo'] ?? null)<img src="{{ $temple['logo'] }}" class="topbar-logo" alt="">@endif
                <div>
                    <div class="topbar-temple-name">{{ $temple['name'] ?? 'Temple' }}</div>
                    @if($temple['subtitle'] ?? null)<div class="topbar-temple-sub">{{ $temple['subtitle'] }}</div>@endif
                </div>
            </div>
            <div class="topbar-title"><h1>Ticket Console</h1></div>
            <div class="topbar-right">
                <a href="{{ route('admin.tickets.pos') }}" target="_blank" class="admin-pill"><i class="bi bi-box-arrow-up-right"></i><span>Ticket Kiosk</span></a>
                <div class="dropdown">
                    <button class="admin-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i><span>{{ \Illuminate\Support\Str::limit(auth()->user()->name ?? 'User', 14) }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-house-door-fill me-2"></i>Main Dashboard</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('logout') }}"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <div class="app-shell">
            <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
            <aside class="app-sidebar" id="appSidebar">
                <div class="sidebar-nav">
                    <button type="button" class="sidebar-link active" data-pane="pane-dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></button>
                    <button type="button" class="sidebar-link" data-pane="pane-types"><i class="bi bi-ticket-perforated-fill"></i><span>Ticket Types</span></button>
                    <button type="button" class="sidebar-link" data-pane="pane-sales"><i class="bi bi-receipt"></i><span>Ticket Sales</span></button>
                    @if($canManageConsole)
                    <button type="button" class="sidebar-link" data-pane="pane-settings"><i class="bi bi-gear-fill"></i><span>Settings</span></button>
                    <button type="button" class="sidebar-link" data-pane="pane-eftpos"><i class="bi bi-credit-card-2-front-fill"></i><span>EFTPOS</span></button>
                    <button type="button" class="sidebar-link" data-pane="pane-controllers"><i class="bi bi-people-fill"></i><span>Ticket Controllers</span></button>
                    <button type="button" class="sidebar-link" data-pane="pane-logs"><i class="bi bi-journal-text"></i><span>Logs</span></button>
                    @endif
                </div>
            </aside>

            <main class="app-main">
                <div class="console-body">
                    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                    <!-- DASHBOARD -->
                    <div class="console-pane active" id="pane-dashboard">
                        <div class="page-header">
                            <div class="page-header-icon"><i class="bi bi-speedometer2"></i></div>
                            <div><h2>Dashboard</h2><p>Ticket sales at a glance.</p></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-3">
                                <div class="stat-tile"><div class="stat-icon" style="background:var(--success);"><i class="bi bi-cash-coin"></i></div><div class="stat-text"><div class="label">Total Sold</div><div class="value">{{ number_format($totalSold, 2) }}</div></div></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-tile"><div class="stat-icon" style="background:var(--gold);"><i class="bi bi-calendar-check"></i></div><div class="stat-text"><div class="label">Today</div><div class="value">{{ number_format($todayTotal, 2) }}</div></div></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-tile"><div class="stat-icon" style="background:var(--maroon);"><i class="bi bi-receipt"></i></div><div class="stat-text"><div class="label">Orders</div><div class="value">{{ $orders->count() }}</div></div></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-tile"><div class="stat-icon" style="background:#2a6fdb;"><i class="bi bi-ticket-perforated-fill"></i></div><div class="stat-text"><div class="label">Ticket Types</div><div class="value">{{ $tickets->count() }}</div></div></div>
                            </div>
                        </div>
                        <div class="card-panel" style="padding:0;">
                            <div class="p-3 pb-0"><h5 class="mb-0"><i class="bi bi-clock-history me-1"></i>Recent Orders</h5></div>
                            <div class="table-scroll-wrap" style="max-height:360px;">
                                <table class="console-table">
                                    <thead><tr><th>Order #</th><th>Customer</th><th class="col-amount">Total</th><th>Payment</th><th>Date</th><th>Status</th></tr></thead>
                                    <tbody>
                                        @forelse($orders->take(10) as $order)
                                        <tr>
                                            <td>#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                                            <td>{{ $order->customer_name ?: '—' }}</td>
                                            <td class="col-amount">{{ number_format($order->total_amount, 2) }}</td>
                                            <td>{{ $order->payment_method }}</td>
                                            <td>{{ $order->order_date->format('d M Y') }}</td>
                                            <td><span class="status-pill status-{{ strtolower($order->payment_status) }}">{{ $order->payment_status }}</span></td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="6" class="text-center text-muted py-4">No ticket orders yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TICKET TYPES -->
                    <div class="console-pane" id="pane-types">
                        <div class="page-header">
                            <div class="page-header-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
                            <div><h2>Ticket Types</h2><p>The catalog sold from the Ticket Kiosk.</p></div>
                            @if($canAdd)
                            <div class="page-header-actions">
                                <button type="button" class="btn-save" data-bs-toggle="modal" data-bs-target="#addTicketModal"><i class="bi bi-plus-lg me-1"></i>Add Ticket Type</button>
                            </div>
                            @endif
                        </div>
                        <div class="row g-3">
                            @forelse($tickets as $ticket)
                            <div class="col-sm-6 col-lg-4 col-xl-3">
                                <div class="ticket-type-card">
                                    <div class="ticket-type-swatch" style="{{ $ticket->image ? 'background-image:url(' . e($ticket->image) . ');' : 'background:' . ($ticket->background_color ?: 'var(--gold)') . ';' }}"></div>
                                    <div class="ticket-type-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-bold">{{ $ticket->name }}</div>
                                                <div class="small text-muted">{{ number_format($ticket->price, 2) }}</div>
                                            </div>
                                            <span class="status-pill status-{{ $ticket->status === 'Active' ? 'paid' : 'cancelled' }}">{{ $ticket->status }}</span>
                                        </div>
                                        <div class="mt-2 text-end">
                                            @if($canEdit)
                                            <button type="button" class="btn-action-edit" data-bs-toggle="modal" data-bs-target="#editTicketModal{{ $ticket->id }}"><i class="bi bi-pencil-fill"></i></button>
                                            @endif
                                            @if($canDelete)
                                            <form action="{{ route('admin.tickets.delete', $ticket->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this ticket type?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-action-delete"><i class="bi bi-trash-fill"></i></button>
                                            </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <div class="col-12"><div class="card-panel text-center text-muted py-5">No ticket types yet — add one to get started.</div></div>
                            @endforelse
                        </div>
                    </div>

                    <!-- TICKET SALES -->
                    <div class="console-pane" id="pane-sales">
                        <div class="page-header">
                            <div class="page-header-icon"><i class="bi bi-receipt"></i></div>
                            <div><h2>Ticket Sales</h2><p>Every order sold from the Ticket Kiosk.</p></div>
                        </div>
                        <div class="card-panel" style="padding:0;">
                            <div class="table-scroll-wrap" style="max-height: calc(100vh - 300px);">
                                <table class="console-table">
                                    <thead>
                                        <tr><th>Order #</th><th>Customer</th><th>Items</th><th class="col-amount">Total</th><th>Payment</th><th>Txn ID</th><th>Sold By</th><th>Date</th><th>Status</th><th class="text-end">Actions</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse($orders as $order)
                                        <tr>
                                            <td>#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                                            <td>{{ $order->customer_name ?: '—' }}</td>
                                            <td class="small text-muted">
                                                @foreach($order->items as $item){{ $item->quantity }}x {{ $item->ticket_name }}@if(!$loop->last), @endif @endforeach
                                            </td>
                                            <td class="col-amount">{{ number_format($order->total_amount, 2) }}</td>
                                            <td>{{ $order->payment_method }}</td>
                                            <td class="col-txn">{{ $order->transaction_id ?: '—' }}</td>
                                            <td class="small">{{ $order->seller->name ?? '—' }}</td>
                                            <td>{{ $order->order_date->format('d M Y') }}</td>
                                            <td><span class="status-pill status-{{ strtolower($order->payment_status) }}">{{ $order->payment_status }}</span></td>
                                            <td class="text-end"><a href="{{ route('admin.tickets.print', $order->id) }}" target="_blank" class="btn-action-checkstatus" title="Reprint stubs"><i class="bi bi-printer-fill"></i></a></td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="10" class="text-center text-muted py-4">No ticket orders yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @if($canManageConsole)
                    <!-- SETTINGS -->
                    <div class="console-pane" id="pane-settings">
                        <div class="page-header">
                            <div class="page-header-icon"><i class="bi bi-gear-fill"></i></div>
                            <div><h2>Settings</h2><p>Ticket Kiosk configuration.</p></div>
                        </div>
                        <div class="card-panel">
                            <div class="fw-bold mb-2">Payment Methods for Ticket Kiosk</div>
                            <p class="text-muted small mb-3">EFT Terminal is always offered whenever a terminal is paired. Choose which of the others the kiosk should accept — leave "Use global settings" checked to inherit the same list donations use (Admin &gt; Settings &gt; Payment Methods).</p>
                            <form action="{{ route('admin.tickets.settings.update') }}" method="POST" id="ticketSettingsForm">
                                @csrf
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="useGlobalPaymentMethods" name="use_global_payment_methods" value="1" {{ $ticketPaymentMethodsOverride === null ? 'checked' : '' }}>
                                    <label class="form-check-label" for="useGlobalPaymentMethods">Use global payment settings</label>
                                </div>
                                <div id="ticketPaymentMethodChoices" style="{{ $ticketPaymentMethodsOverride === null ? 'display:none;' : '' }}">
                                    @foreach(['Cash', 'UPI', 'Bank Transfer'] as $method)
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="payment_methods[]" value="{{ $method }}" id="pm_{{ \Illuminate\Support\Str::slug($method) }}" {{ in_array($method, $ticketPaymentMethodsOverride ?? [], true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="pm_{{ \Illuminate\Support\Str::slug($method) }}">{{ $method }}</label>
                                    </div>
                                    @endforeach
                                </div>
                                <button type="submit" class="btn-save mt-3">Save Settings</button>
                            </form>
                        </div>

                        <div class="card-panel mt-3">
                            <div class="fw-bold mb-2">This Computer's EFT Terminal</div>
                            <p class="text-muted small mb-3">Which physical terminal <strong>this computer</strong> uses when selling tickets — saved only in this browser, not on the server, so two kiosk computers can each be set to a different terminal and sell concurrently without interfering. Setting it here takes effect on the Ticket Kiosk page on this same computer immediately.</p>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-6">
                                    <label class="field-label">Terminal for this computer</label>
                                    <select class="form-select" id="thisComputerTerminalSelect">
                                        <option value="">— Choose a terminal —</option>
                                        @foreach($eftTerminals as $terminal)
                                        <option value="{{ $terminal->id }}">{{ $terminal->label }}{{ $terminal->is_default ? ' (default)' : '' }} — {{ $terminal->isPaired($linklyMode) ? 'Paired' : 'Not paired' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn-save" id="saveThisComputerTerminalBtn">Save for This Computer</button>
                                </div>
                                <div class="col-md-3">
                                    <span class="text-muted small" id="thisComputerTerminalStatus"></span>
                                </div>
                            </div>
                        </div>

                        <div class="card-panel mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold">Registered EFT Terminals</div>
                                <a href="{{ route('eft.pairing-guide') }}" target="_blank" class="small"><i class="bi bi-question-circle me-1"></i>Help</a>
                            </div>
                            <p class="text-muted small mb-3">Every terminal below is available to be assigned to a computer above. Pairing and refunds happen from the EFTPOS pane; add a brand new terminal here.</p>
                            @foreach($eftTerminals as $terminal)
                            @php $lastKnown = $terminal->lastKnownStatus(); @endphp
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2 pb-2 border-bottom">
                                <strong>{{ $terminal->label }}</strong>
                                <span class="text-muted small">({{ $terminal->key }})</span>
                                @if($terminal->is_default)<span class="badge bg-primary">Default</span>@endif
                                <span class="status-pill status-{{ $terminal->isPaired($linklyMode) ? 'paid' : 'cancelled' }}">{{ $terminal->isPaired($linklyMode) ? 'Paired' : 'Not paired' }}</span>
                                @if($lastKnown['state'] === 'online')
                                <span class="status-pill status-paid">Online</span>
                                @elseif($lastKnown['state'] === 'offline')
                                <span class="status-pill status-cancelled">Offline</span>
                                @else
                                <span class="status-pill status-pending">Not checked</span>
                                @endif
                                @if($lastKnown['at'])
                                <span class="text-muted" style="font-size:0.72rem;">({{ $lastKnown['at']->diffForHumans() }})</span>
                                @endif
                            </div>
                            @endforeach
                            <form action="{{ route('admin.eft-terminals.store') }}" method="POST" class="row g-2 align-items-end mt-2">
                                @csrf
                                <input type="hidden" name="return_context" value="ticket-console">
                                <div class="col-md-4">
                                    <label class="field-label">Key (unique, no spaces)</label>
                                    <input type="text" name="key" class="form-control" placeholder="e.g. ticket-counter-2" maxlength="40" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="field-label">Label</label>
                                    <input type="text" name="label" class="form-control" placeholder="e.g. Ticket Counter 2" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn-save w-100">Add Terminal</button>
                                </div>
                            </form>
                            <p class="text-muted small mt-2 mb-0">Pair a newly added terminal from the EFTPOS pane before assigning it to a computer above.</p>
                        </div>
                    </div>

                    <!-- EFTPOS -->
                    <div class="console-pane" id="pane-eftpos">
                        <div class="page-header">
                            <div class="page-header-icon"><i class="bi bi-credit-card-2-front-fill"></i></div>
                            <div><h2>EFTPOS — Linkly Core Payments</h2><p>Terminal pairing and refunds for ticket sales.</p></div>
                            <div class="page-header-actions">
                                <a href="{{ route('eft.pairing-guide') }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-question-circle me-1"></i>Help</a>
                            </div>
                        </div>
                        <div class="card-panel mb-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                <div class="text-muted small">Environment: <strong class="text-uppercase">{{ $linklyMode }}</strong> &middot; each terminal below is independently paired, so a second ticket counter can run its own concurrently.</div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-pane="pane-settings"><i class="bi bi-plus-lg me-1"></i>Add a Terminal</button>
                            </div>
                            @foreach($eftTerminals as $terminal)
                            @php $lastKnown = $terminal->lastKnownStatus(); @endphp
                            <div class="row g-3 align-items-center border-top pt-3 mt-2">
                                <div class="col-md-3">
                                    <strong>{{ $terminal->label }}</strong>
                                    @if($terminal->is_default)<span class="badge bg-primary ms-1">Default</span>@endif
                                    <div class="text-muted small">{{ $terminal->key }}</div>
                                </div>
                                <div class="col-md-2">
                                    <span class="status-pill status-{{ $terminal->isPaired($linklyMode) ? 'paid' : 'cancelled' }}">{{ $terminal->isPaired($linklyMode) ? 'Paired' : 'Not paired' }}</span>
                                </div>
                                <div class="col-md-2">
                                    @if($lastKnown['state'] === 'online')
                                    <span class="status-pill status-paid" title="Last confirmed via a {{ $lastKnown['via'] }} at {{ $lastKnown['at']->format('d M Y H:i') }}"><i class="bi bi-circle-fill" style="font-size:0.5rem;"></i> Online</span>
                                    @elseif($lastKnown['state'] === 'offline')
                                    <span class="status-pill status-cancelled" title="Last attempt via a {{ $lastKnown['via'] }} at {{ $lastKnown['at']->format('d M Y H:i') }}"><i class="bi bi-circle-fill" style="font-size:0.5rem;"></i> Offline</span>
                                    @else
                                    <span class="status-pill status-pending">Not checked</span>
                                    @endif
                                    @if($lastKnown['at'])
                                    <div class="text-muted" style="font-size:0.68rem;">{{ $lastKnown['at']->diffForHumans() }}</div>
                                    @endif
                                </div>
                                <div class="col-md-3">
                                    <form action="{{ route('admin.tickets.eft.pair') }}" method="POST" class="d-flex gap-2">
                                        @csrf
                                        <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                                        <input type="text" name="pair_code" class="form-control form-control-sm rounded-3" placeholder="Pair / repair code" required maxlength="10">
                                        <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-plug-fill me-1"></i>Pair</button>
                                    </form>
                                </div>
                                <div class="col-md-2">
                                    <form action="{{ route('admin.tickets.eft.logon') }}" method="POST" onsubmit="return confirm('Check {{ $terminal->label }} is online now?')">
                                        @csrf
                                        <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Check Status</button>
                                    </form>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="card-panel" style="padding:0;">
                            <div class="p-3 pb-0"><h5 class="mb-0"><i class="bi bi-clock-history me-1"></i>Recent Transactions</h5></div>
                            <div class="table-scroll-wrap" style="max-height: calc(100vh - 460px);">
                                <table class="console-table">
                                    <thead><tr><th>Type</th><th class="col-amount">Amount</th><th>Reference</th><th>Terminal</th><th>Date/Time</th><th>Result</th><th>Session ID</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                        @forelse($linklyTransactions as $txn)
                                        @php
                                            $pillClass = match($txn->status) { 'approved' => 'paid', 'initiated', 'in_progress' => 'pending', default => 'cancelled' };
                                            $canRefundRow = $txn->txn_type === 'purchase' && $txn->status === 'approved' && !$linklyTransactions->contains(fn ($t) => $t->original_transaction_id === $txn->id && in_array($t->status, ['initiated', 'in_progress', 'approved']));
                                        @endphp
                                        <tr>
                                            <td class="text-capitalize">{{ $txn->txn_type }}</td>
                                            <td class="col-amount">{{ $txn->amount !== null ? number_format($txn->amount, 2) : '—' }}</td>
                                            <td class="col-txn">{{ $txn->pos_txn_ref }}</td>
                                            <td class="small text-muted">{{ $txn->eftTerminal->label ?? '—' }}</td>
                                            <td>{{ $txn->created_at->format('d M Y H:i:s') }}</td>
                                            <td><span class="status-pill status-{{ $pillClass }}">{{ ucfirst($txn->status) }}</span></td>
                                            <td class="col-txn">{{ $txn->linkly_session_id ?: '—' }}</td>
                                            <td class="text-end">
                                                @if($txn->linkly_session_id)
                                                <form action="{{ route('admin.tickets.eft.reprint', $txn->linkly_session_id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Reprint receipt"><i class="bi bi-receipt"></i></button>
                                                </form>
                                                @endif
                                                @if($canRefundRow)
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Refund" onclick="openTicketRefundModal({{ $txn->id }}, {{ $txn->amount }})"><i class="bi bi-arrow-counterclockwise"></i> Refund</button>
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="8" class="text-center text-muted py-4">No ticket-related Linkly transactions yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TICKET CONTROLLERS -->
                    <div class="console-pane" id="pane-controllers">
                        <div class="page-header">
                            <div class="page-header-icon"><i class="bi bi-people-fill"></i></div>
                            <div><h2>Ticket Controllers</h2><p>People with access to the Ticket Console / Kiosk.</p></div>
                        </div>
                        <div class="card-panel mb-3">
                            <div class="fw-bold mb-2">Add a Ticket Controller</div>
                            <ul class="nav nav-pills mb-3 small" role="tablist">
                                <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tcExistingPane" type="button">Existing User</button></li>
                                <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tcNewPane" type="button">New Person</button></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="tcExistingPane">
                                    <form action="{{ route('admin.ticket-controllers.store') }}" method="POST" class="d-flex gap-2 flex-wrap">
                                        @csrf
                                        <select name="user_id" class="form-select" style="max-width:280px;" required>
                                            <option value="">-- Choose a user --</option>
                                            @foreach($allUsersForControllers as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                            @endforeach
                                        </select>
                                        <select name="level" class="form-select" style="max-width:160px;">
                                            <option value="view">View</option>
                                            <option value="entry" selected>Entry</option>
                                            @if($activeRole === 'Admin')
                                            <option value="admin">Admin</option>
                                            @endif
                                        </select>
                                        <button type="submit" class="btn-save">Add</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="tcNewPane">
                                    <form action="{{ route('admin.ticket-controllers.store') }}" method="POST">
                                        @csrf
                                        <div class="field-row two-col">
                                            <div class="field-group"><label class="field-label">Full Name</label><input type="text" name="name" required></div>
                                            <div class="field-group"><label class="field-label">Email</label><input type="email" name="email" required></div>
                                        </div>
                                        <div class="field-row two-col">
                                            <div class="field-group"><label class="field-label">Mobile</label><input type="text" name="mobile" required></div>
                                            <div class="field-group">
                                                <label class="field-label">Access Level</label>
                                                <select name="level">
                                                    <option value="view">View</option>
                                                    <option value="entry" selected>Entry</option>
                                                    @if($activeRole === 'Admin')
                                                    <option value="admin">Admin</option>
                                                    @endif
                                                </select>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn-save">Create &amp; Add</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="card-panel" style="padding:0;">
                            <div class="table-scroll-wrap">
                                <table class="console-table">
                                    <thead><tr><th>Name</th><th>Email</th><th>Level</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                        @forelse($ticketControllers as $tc)
                                        @php $canTouch = $activeRole === 'Admin' || $tc->level !== 'admin'; @endphp
                                        <tr>
                                            <td class="col-name">{{ $tc->name }}</td>
                                            <td>{{ $tc->email }}</td>
                                            <td>
                                                @if($canTouch)
                                                <form action="{{ route('admin.ticket-controllers.updateLevel', $tc->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <select name="level" class="form-select form-select-sm d-inline-block" style="width:auto;" onchange="this.form.submit()">
                                                        <option value="view" {{ $tc->level === 'view' ? 'selected' : '' }}>View</option>
                                                        <option value="entry" {{ $tc->level === 'entry' ? 'selected' : '' }}>Entry</option>
                                                        @if($activeRole === 'Admin')
                                                        <option value="admin" {{ $tc->level === 'admin' ? 'selected' : '' }}>Admin</option>
                                                        @endif
                                                    </select>
                                                </form>
                                                @else
                                                <span class="status-pill status-paid">Admin</span>
                                                @endif
                                            </td>
                                            <td><span class="status-pill status-{{ $tc->status === 'Active' ? 'paid' : 'cancelled' }}">{{ $tc->status === 'Active' ? 'Active' : 'Locked' }}</span></td>
                                            <td class="text-end">
                                                <form action="{{ route('admin.ticket-controllers.sendResetLink', $tc->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn-action-resend" title="Send password reset link"><i class="bi bi-key-fill"></i></button>
                                                </form>
                                                @if($canTouch)
                                                <form action="{{ route('admin.ticket-controllers.toggleLock', $tc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ $tc->status === 'Active' ? 'Lock' : 'Unlock' }} this account?')">
                                                    @csrf
                                                    <button type="submit" class="btn-action-checkstatus" title="{{ $tc->status === 'Active' ? 'Lock account' : 'Unlock account' }}"><i class="bi {{ $tc->status === 'Active' ? 'bi-lock-fill' : 'bi-unlock-fill' }}"></i></button>
                                                </form>
                                                <form action="{{ route('admin.ticket-controllers.destroy', $tc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this person\'s Ticket Controller access?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn-action-delete" title="Remove access"><i class="bi bi-x-lg"></i></button>
                                                </form>
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">No Ticket Controllers assigned yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- LOGS -->
                    <div class="console-pane" id="pane-logs">
                        <div class="page-header">
                            <div class="page-header-icon"><i class="bi bi-journal-text"></i></div>
                            <div><h2>Logs</h2><p>Recent ticket-related activity.</p></div>
                        </div>
                        <div class="card-panel" style="padding:0;">
                            <div class="table-scroll-wrap" style="max-height: calc(100vh - 300px);">
                                <table class="console-table">
                                    <thead><tr><th>Date/Time</th><th>Action</th><th>Performed By</th><th>IP Address</th></tr></thead>
                                    <tbody>
                                        @forelse($ticketLogs as $log)
                                        <tr>
                                            <td>{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d M Y H:i:s') }}</td>
                                            <td>{{ $log->action }}</td>
                                            <td>{{ $log->performed_by_name ?? '—' }}</td>
                                            <td class="text-muted small">{{ $log->ip_address ?: '—' }}</td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">No ticket-related activity logged yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </main>
        </div>
    </div>

    @if($canAdd)
    <div class="modal fade" id="addTicketModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('admin.tickets.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Add Ticket Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description (optional)</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">Price</label><input type="number" step="0.01" min="0.01" name="price" class="form-control" required></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Background Colour</label><input type="color" name="background_color" class="form-control form-control-color w-100" value="#C89B3C"></div>
                        <div class="col-6 mb-3"><label class="form-label">Image URL (optional)</label><input type="text" name="image" class="form-control" placeholder="/uploads/tickets/example.jpg"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Sort Order (optional)</label><input type="number" name="sort_order" class="form-control" value="0"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-warning">Add</button></div>
            </form>
        </div>
    </div>
    @endif

    @if($canEdit)
    @foreach($tickets as $ticket)
    <div class="modal fade" id="editTicketModal{{ $ticket->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('admin.tickets.update', $ticket->id) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Edit Ticket Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ $ticket->name }}" required></div>
                    <div class="mb-3"><label class="form-label">Description (optional)</label><textarea name="description" class="form-control" rows="2">{{ $ticket->description }}</textarea></div>
                    <div class="mb-3"><label class="form-label">Price</label><input type="number" step="0.01" min="0.01" name="price" class="form-control" value="{{ $ticket->price }}" required></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Background Colour</label><input type="color" name="background_color" class="form-control form-control-color w-100" value="{{ $ticket->background_color ?: '#C89B3C' }}"></div>
                        <div class="col-6 mb-3"><label class="form-label">Image URL (optional)</label><input type="text" name="image" class="form-control" value="{{ $ticket->image }}" placeholder="/uploads/tickets/example.jpg"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" {{ $ticket->status === 'Active' ? 'selected' : '' }}>Active</option>
                            <option value="Inactive" {{ $ticket->status === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="{{ $ticket->sort_order }}"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-warning">Save</button></div>
            </form>
        </div>
    </div>
    @endforeach
    @endif

    @if($canManageConsole)
    <div class="modal fade" id="ticketRefundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Refund Transaction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div id="refundFormArea">
                        <label class="form-label">Refund Amount</label>
                        <input type="number" step="0.01" min="0.01" id="refundAmountInput" class="form-control">
                        <p class="text-muted small mt-2 mb-0">The customer may be asked to present their card again on the terminal to complete the refund.</p>
                    </div>
                    <div id="refundStatusArea" style="display:none;" class="text-center py-3">
                        <div class="spinner-border text-danger mb-2"></div>
                        <div class="fw-bold" id="refundStatusLine1">Starting…</div>
                        <div class="text-muted small" id="refundStatusLine2"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-danger" id="refundSubmitBtn" onclick="submitTicketRefund()">Refund</button></div>
            </div>
        </div>
    </div>
    @endif

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        const useGlobalPaymentMethods = document.getElementById('useGlobalPaymentMethods');
        if (useGlobalPaymentMethods) {
            useGlobalPaymentMethods.addEventListener('change', function () {
                document.getElementById('ticketPaymentMethodChoices').style.display = this.checked ? 'none' : '';
            });
        }

        // "This Computer's EFT Terminal" — the same localStorage key the Ticket Kiosk page
        // itself reads (see ticket-pos.blade.php), so setting it here takes effect there too
        // on this same computer/browser. Never sent to the server — that's the whole point:
        // two kiosk computers can each be pointed at a different terminal independently.
        (function () {
            const STORAGE_KEY = 'ticketPosEftTerminalId';
            const select = document.getElementById('thisComputerTerminalSelect');
            const saveBtn = document.getElementById('saveThisComputerTerminalBtn');
            const status = document.getElementById('thisComputerTerminalStatus');
            if (!select || !saveBtn) { return; }

            try {
                const saved = localStorage.getItem(STORAGE_KEY);
                if (saved && select.querySelector('option[value="' + saved + '"]')) {
                    select.value = saved;
                    status.textContent = 'Currently set for this computer.';
                } else {
                    status.textContent = 'Not set for this computer yet — the kiosk will use the default terminal.';
                }
            } catch (e) {
                status.textContent = 'Could not read this browser\'s saved terminal.';
            }

            saveBtn.addEventListener('click', function () {
                if (!select.value) {
                    status.textContent = 'Choose a terminal first.';
                    return;
                }
                try {
                    localStorage.setItem(STORAGE_KEY, select.value);
                    status.textContent = 'Saved for this computer.';
                } catch (e) {
                    status.textContent = 'Could not save — this browser may be blocking local storage.';
                }
            });
        })();
        function activatePane(paneId) {
            const link = document.querySelector('[data-pane="' + paneId + '"]');
            if (!link) { return false; }
            document.querySelectorAll('[data-pane]').forEach(function (b) { b.classList.remove('active'); });
            link.classList.add('active');
            document.querySelectorAll('.console-pane').forEach(function (p) { p.classList.toggle('active', p.id === paneId); });
            document.getElementById('appSidebar').classList.remove('open');
            document.getElementById('sidebarBackdrop').classList.remove('show');
            return true;
        }
        document.querySelectorAll('[data-pane]').forEach(function (el) {
            el.addEventListener('click', function () { activatePane(this.dataset.pane); });
        });
        // The Settings/EFTPOS/Ticket Controllers forms are plain full-page POST/redirects
        // (not AJAX), so the client-side "which pane is active" state would otherwise reset
        // back to Dashboard after saving — same "consoleActivePane" localStorage convention
        // as event-console.blade.php, so a form submission anywhere in one of these panes
        // reopens that exact pane once the page reloads.
        ['pane-settings', 'pane-eftpos', 'pane-controllers'].forEach(function (paneId) {
            const pane = document.getElementById(paneId);
            if (!pane) { return; }
            pane.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    try { localStorage.setItem('consoleActivePane', paneId); } catch (e) {}
                });
            });
        });
        (function restoreActivePane() {
            let savedPane = null;
            try { savedPane = localStorage.getItem('consoleActivePane'); } catch (e) {}
            if (savedPane && activatePane(savedPane)) {
                try { localStorage.removeItem('consoleActivePane'); } catch (e) {}
            }
        })();
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                document.getElementById('appSidebar').classList.toggle('open');
                document.getElementById('sidebarBackdrop').classList.toggle('show');
            });
        }
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', function () {
                document.getElementById('appSidebar').classList.remove('open');
                this.classList.remove('show');
            });
        }

        @if($canManageConsole)
        let refundTxnId = null;
        let refundPollCancelled = true;
        const REFUND_URL_BASE = @json(url('/admin/tickets/eft/refund'));
        const REFUND_STATUS_URL_BASE = @json(url('/admin/eft/charge/status'));
        const refundModalEl = document.getElementById('ticketRefundModal');
        const refundModal = refundModalEl ? new bootstrap.Modal(refundModalEl) : null;

        function openTicketRefundModal(txnId, amount) {
            refundTxnId = txnId;
            refundPollCancelled = true;
            document.getElementById('refundAmountInput').value = Number(amount).toFixed(2);
            document.getElementById('refundFormArea').style.display = '';
            document.getElementById('refundStatusArea').style.display = 'none';
            document.getElementById('refundSubmitBtn').style.display = '';
            document.getElementById('refundSubmitBtn').disabled = false;
            if (refundModal) { refundModal.show(); }
        }
        function setRefundStatus(line1, line2) {
            document.getElementById('refundStatusLine1').textContent = line1 || '';
            document.getElementById('refundStatusLine2').textContent = line2 || '';
        }
        function submitTicketRefund() {
            const amount = parseFloat(document.getElementById('refundAmountInput').value);
            if (!amount || amount <= 0) { return; }
            if (!confirm('Refund ' + amount.toFixed(2) + ' on the terminal now?')) { return; }

            document.getElementById('refundFormArea').style.display = 'none';
            document.getElementById('refundStatusArea').style.display = '';
            document.getElementById('refundSubmitBtn').style.display = 'none';
            setRefundStatus('Starting refund…', '');
            refundPollCancelled = false;

            const clientRef = 'ticket-refund-' + refundTxnId + '-' + Date.now();
            fetch(REFUND_URL_BASE + '/' + refundTxnId, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'amount=' + encodeURIComponent(amount.toFixed(2)) + '&client_ref=' + encodeURIComponent(clientRef),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    if (!(result.status >= 200 && result.status < 300 && result.data.success)) {
                        setRefundStatus('Could not start refund', result.data.message || '');
                        return;
                    }
                    pollTicketRefund(result.data.session_id, Date.now());
                })
                .catch(function () {
                    setRefundStatus('Network error', 'Please try again.');
                });
        }
        function pollTicketRefund(sessionId, startedAt) {
            if (refundPollCancelled) { return; }
            if (Date.now() - startedAt > 180000) {
                setRefundStatus('Timed out', 'Check Transaction Status before retrying.');
                return;
            }
            fetch(REFUND_STATUS_URL_BASE + '/' + encodeURIComponent(sessionId), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (refundPollCancelled) { return; }
                    if (data.display && data.display.length) { setRefundStatus(data.display[0], data.display[1] || ''); }
                    if (!data.done) {
                        setTimeout(function () { pollTicketRefund(sessionId, startedAt); }, 1200);
                        return;
                    }
                    if (data.success) {
                        setRefundStatus('REFUND APPROVED', data.auth_code ? 'Auth ' + data.auth_code : '');
                        setTimeout(function () { window.location.reload(); }, 1200);
                    } else {
                        setRefundStatus('REFUND ' + (data.payment_status || 'NOT COMPLETED').toUpperCase(), data.message || '');
                        document.getElementById('refundSubmitBtn').style.display = '';
                    }
                })
                .catch(function () {
                    setTimeout(function () { pollTicketRefund(sessionId, startedAt); }, 1200);
                });
        }
        @endif
    </script>
</body>
</html>
