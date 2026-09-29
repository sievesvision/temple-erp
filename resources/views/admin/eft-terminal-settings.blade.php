<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EFT Terminal Settings</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A; --maroon-dark: #4A0A12; --gold: #C89B3C; --gold-hover: #A67C2B;
            --cream: #F9F3E7; --white: #FFFFFF; --border: #E5E7EB; --text-primary: #1F2A37;
            --text-secondary: #6B7280; --success: #10B981; --error: #EF4444;
            --info-bg: #EFF6FF; --info-border: #BFDBFE; --info-text: #1D4ED8;
            --serif: 'Playfair Display', Georgia, serif;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #FAFAF8; color: var(--text-primary); }
        h1, h2 { font-family: var(--serif); }

        .topbar { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 16px 24px; display: flex; align-items: center; gap: 14px; box-shadow: 0 4px 18px rgba(74,10,18,0.25); }
        .topbar h1 { font-size: 1.3rem; font-weight: 800; color: var(--gold); margin: 0; flex: 1; }
        .topbar-btn { background: rgba(255,255,255,0.12); border: none; color: white; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .topbar-btn:hover { background: rgba(255,255,255,0.22); color: white; }

        .body-wrap { padding: 24px clamp(16px, 3vw, 40px) 60px; max-width: 1280px; margin: 0 auto; }

        /* ---------- Info banner: informational, not dominant ---------- */
        .info-banner { display: flex; gap: 12px; align-items: flex-start; background: var(--info-bg); border: 1px solid var(--info-border); border-radius: 12px; padding: 14px 18px; margin-bottom: 28px; }
        .info-banner-icon { width: 26px; height: 26px; border-radius: 50%; background: var(--info-text); color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.85rem; }
        .info-banner-title { font-weight: 700; font-size: 0.92rem; color: var(--text-primary); margin-bottom: 3px; }
        .info-banner-text { font-size: 0.82rem; color: var(--text-secondary); line-height: 1.55; }
        .mode-chip { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 800; letter-spacing: 0.04em; background: #fff; border: 1px solid var(--info-border); color: var(--info-text); margin-left: 4px; }

        /* ---------- Section headings ---------- */
        .section-heading-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin: 28px 0 14px; }
        .section-heading-row:first-of-type { margin-top: 0; }
        .section-heading { display: flex; align-items: center; gap: 10px; font-size: 1.15rem; font-weight: 800; color: var(--text-primary); }
        .section-count { background: var(--border); color: var(--text-secondary); font-size: 0.78rem; font-weight: 700; border-radius: 20px; padding: 2px 10px; }
        .section-count.count-ok { background: #D1FAE5; color: #047857; }
        .operational-flag { display: inline-flex; align-items: center; gap: 6px; font-size: 0.82rem; color: #047857; font-weight: 600; }
        .operational-flag .dot { width: 8px; height: 8px; border-radius: 50%; background: #10B981; }

        /* ---------- Terminal card ---------- */
        .terminal-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(16,24,40,0.04); }
        .terminal-card-inactive .terminal-summary-row { background: #FAFAFA; }
        .terminal-summary-row { display: flex; align-items: center; gap: 20px; padding: 18px 20px; cursor: pointer; flex-wrap: wrap; }
        .terminal-summary-row:hover { background: #FBF9F6; }
        .terminal-card-inactive .terminal-summary-row:hover { background: #F3F4F6; }
        .terminal-icon { width: 44px; height: 44px; border-radius: 10px; background: var(--cream); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: var(--maroon); flex-shrink: 0; }
        .terminal-summary-main { flex: 1 1 240px; min-width: 200px; }
        .terminal-summary-name { font-size: 1rem; margin-bottom: 3px; }
        .terminal-summary-meta { font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 8px; }
        .terminal-summary-badges { display: flex; gap: 6px; flex-wrap: wrap; }
        .terminal-summary-status { display: flex; flex-direction: column; gap: 7px; flex: 0 0 210px; }
        .status-row { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; }
        .status-row i { color: var(--text-secondary); width: 16px; text-align: center; flex-shrink: 0; }
        .status-label { color: var(--text-secondary); min-width: 82px; }
        .terminal-summary-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; margin-left: auto; }

        .badge-pill { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 0.66rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap; }
        .badge-provider { background: #F3F4F6; color: #4B5563; }
        .badge-info { background: #DBEAFE; color: #1D4ED8; }
        .badge-ok { background: #D1FAE5; color: #047857; }
        .badge-bad { background: #FEE2E2; color: #B91C1C; }
        .badge-warn { background: #FEF3C7; color: #92400E; }

        .btn-terminal-settings { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 8px; border: 1px solid var(--border); background: #fff; color: var(--text-primary); font-weight: 700; font-size: 0.82rem; min-height: 40px; }
        .btn-terminal-settings:hover { border-color: var(--maroon); color: var(--maroon); }
        .btn-terminal-more { width: 40px; height: 40px; border-radius: 8px; border: 1px solid var(--border); background: #fff; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); }
        .btn-terminal-more:hover { background: #F9FAFB; }

        .terminal-detail-body { padding: 4px 20px 20px; border-top: 1px solid var(--border); }
        .terminal-detail-section { padding: 16px 0; border-bottom: 1px solid var(--border); }
        .terminal-detail-section:last-child { border-bottom: none; padding-bottom: 4px; }
        .terminal-detail-heading { font-size: 0.76rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 10px; }
        .terminal-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; font-size: 0.88rem; }
        .terminal-danger-zone { background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 10px; padding: 14px 16px !important; margin-top: 4px; }

        /* ---------- Add terminal ---------- */
        .add-terminal-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 22px 24px; margin-top: 32px; }
        .add-terminal-header { display: flex; align-items: center; gap: 10px; font-size: 1.05rem; font-weight: 800; color: var(--maroon); margin-bottom: 4px; }
        .add-terminal-sub { font-size: 0.84rem; color: var(--text-secondary); margin-bottom: 18px; }
        .field-hint { font-size: 0.74rem; color: var(--text-secondary); margin-top: 4px; }
        .btn-add-terminal { padding: 10px 22px; border-radius: 8px; border: none; background: var(--maroon); color: white; font-weight: 800; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; }
        .btn-add-terminal:hover { background: var(--maroon-dark); color: #fff; }

        @media (max-width: 640px) {
            .terminal-summary-row { padding: 16px; }
            .terminal-summary-status { flex-basis: 100%; order: 3; }
            .terminal-summary-actions { flex-basis: 100%; order: 4; margin-left: 0; justify-content: flex-end; }
        }
    </style>
</head>
<body>
    @php $temple = \App\Models\Setting::templeBranding(); @endphp
    <header class="topbar">
        <h1><i class="bi bi-credit-card-2-front-fill me-2"></i>EFT Terminal Settings</h1>
        <a href="{{ route('eft.pairing-guide') }}" target="_blank" class="topbar-btn"><i class="bi bi-question-circle"></i>Help</a>
        {{-- Not url()->previous() — this page is only ever linked to from Admin Settings, but
             is also directly reachable by an event-admin coordinator or ticket-admin controller
             (see the controller's own docblock), who can't necessarily open Admin Settings
             itself (role.admin-gated) — so the parent page depends on which of those this
             visitor actually is, rather than trusting the browser's referrer. --}}
        <a href="{{ $isSystemAdmin ? route('admin.settings') : route('admin.dashboard') }}" class="topbar-btn"><i class="bi bi-arrow-left"></i>Back</a>
        <a href="{{ route('logout') }}" class="topbar-btn"><i class="bi bi-box-arrow-right"></i>Logout</a>
    </header>

    <div class="body-wrap">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <div class="info-banner">
            <span class="info-banner-icon"><i class="bi bi-info-lg"></i></span>
            <div>
                <div class="info-banner-title">Multiple EFT terminals can be used at the same time</div>
                <div class="info-banner-text">
                    Each terminal is independently paired via Linkly Cloud or mx51 Cloud, allowing multiple physical or virtual PIN pads to operate simultaneously — for example, one for the Ticket Kiosk and another for the Donation POS.
                    <span class="mode-chip">{{ strtoupper($linklyMode) }}</span>
                </div>
            </div>
        </div>

        @if($activeTerminals->isEmpty() && $inactiveTerminals->isEmpty())
        <p class="text-muted">No terminals registered yet — add one below.</p>
        @endif

        @if($activeTerminals->isNotEmpty())
        <div class="section-heading-row">
            <div class="section-heading">Active Terminals <span class="section-count count-ok">{{ $activeTerminals->count() }}</span></div>
            @if($allOperational)
            <span class="operational-flag"><span class="dot"></span> All systems operational</span>
            @endif
        </div>
        @foreach($activeTerminals as $terminal)
            @include('admin.partials.eft-terminal-card', ['terminal' => $terminal, 'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode, 'isSystemAdmin' => $isSystemAdmin])
        @endforeach
        @endif

        @if($inactiveTerminals->isNotEmpty())
        <div class="section-heading-row">
            <div class="section-heading">Inactive Terminals <span class="section-count">{{ $inactiveTerminals->count() }}</span></div>
        </div>
        @foreach($inactiveTerminals as $terminal)
            @include('admin.partials.eft-terminal-card', ['terminal' => $terminal, 'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode, 'isSystemAdmin' => $isSystemAdmin])
        @endforeach
        @endif

        <div class="add-terminal-card">
            <div class="add-terminal-header"><i class="bi bi-plus-circle-fill"></i> Add New Terminal</div>
            <div class="add-terminal-sub">Add and configure a new EFT terminal. Each terminal requires a unique key and a label for easy identification.</div>
            <form action="{{ route('admin.eft-terminals.store') }}" method="POST" class="row g-3 align-items-start">
                @csrf
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Terminal Key</label>
                    <input type="text" name="key" class="form-control rounded-3" placeholder="e.g. ticket-counter-2" maxlength="40" required>
                    <div class="field-hint">Unique identifier. No spaces.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Label</label>
                    <input type="text" name="label" class="form-control rounded-3" placeholder="e.g. Ticket Counter 2" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Provider</label>
                    <select name="provider" class="form-select rounded-3">
                        <option value="linkly">Linkly Cloud (PIN pad)</option>
                        <option value="cba_sci">mx51 Cloud</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold d-none d-md-block">&nbsp;</label>
                    <button type="submit" class="btn-add-terminal w-100 justify-content-center"><i class="bi bi-plus-lg"></i> Add Terminal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
