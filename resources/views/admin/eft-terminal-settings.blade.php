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
            --cream: #F9F3E7; --white: #FFFFFF; --border: #F0E5D6; --text-primary: #1F2A37;
            --text-secondary: #6B7280; --success: #10B981; --error: #EF4444;
            --serif: 'Playfair Display', Georgia, serif;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--cream); color: var(--text-primary); }
        h1, h2 { font-family: var(--serif); }

        .topbar { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 16px 24px; display: flex; align-items: center; gap: 14px; box-shadow: 0 4px 18px rgba(74,10,18,0.25); }
        .topbar h1 { font-size: 1.3rem; font-weight: 800; color: var(--gold); margin: 0; flex: 1; }
        .topbar-btn { background: rgba(255,255,255,0.12); border: none; color: white; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .topbar-btn:hover { background: rgba(255,255,255,0.22); color: white; }

        .body-wrap { padding: 24px clamp(16px, 3vw, 40px); max-width: 900px; margin: 0 auto; }
        .card-panel { background: var(--white); border: 1px solid var(--border); border-radius: 14px; padding: 24px; box-shadow: 0 1px 3px rgba(31,42,55,0.04); margin-bottom: 20px; }
        .status-pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
        .status-pill.paid { background: #ECFDF5; color: var(--success); }
        .status-pill.cancelled { background: #FEF2F2; color: var(--error); }
        .status-pill.pending { background: #FFF7ED; color: #F59E0B; }
        .btn-save { padding: 10px 22px; border-radius: 10px; border: none; background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; font-size: 0.92rem; }
        .group-heading { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); margin: 28px 0 10px; display: flex; align-items: center; gap: 10px; }
        .group-heading::after { content: ''; flex: 1; height: 1px; background: var(--border); }
        .group-heading:first-child { margin-top: 0; }
        /* An inactive (unpaired) terminal can't take a payment — dimmed and grouped below the
           active ones so it doesn't compete for attention, without hiding it entirely (it may
           still hold transaction history, or just be awaiting its first pairing). */
        .card-panel-inactive { opacity: 0.72; }
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

        <p class="text-muted small mb-3">Each terminal below is independently paired via Linkly Cloud, so more than one physical (or virtual test) PIN pad can be in use at once — e.g. one for the Ticket Kiosk and another for an event's donation POS, running simultaneously. Mode: <strong class="text-uppercase">{{ $linklyMode }}</strong> (set by the <code>LINKLY_*</code> credentials configured on the server).</p>

        @if($activeTerminals->isEmpty() && $inactiveTerminals->isEmpty())
        <p class="text-muted">No terminals registered yet — add one below.</p>
        @endif

        @if($activeTerminals->isNotEmpty())
        <div class="group-heading">Active — paired &amp; usable</div>
        @foreach($activeTerminals as $terminal)
            @include('admin.partials.eft-terminal-card', ['terminal' => $terminal, 'linklyMode' => $linklyMode, 'isSystemAdmin' => $isSystemAdmin])
        @endforeach
        @endif

        @if($inactiveTerminals->isNotEmpty())
        <div class="group-heading">Inactive — not currently paired</div>
        @foreach($inactiveTerminals as $terminal)
            @include('admin.partials.eft-terminal-card', ['terminal' => $terminal, 'linklyMode' => $linklyMode, 'isSystemAdmin' => $isSystemAdmin])
        @endforeach
        @endif

        <div class="card-panel" style="background:var(--cream);">
            <div class="fw-semibold mb-2">Add Another Terminal</div>
            <form action="{{ route('admin.eft-terminals.store') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label small">Key (unique, no spaces)</label>
                    <input type="text" name="key" class="form-control rounded-3" placeholder="e.g. ticket-counter-2" maxlength="40" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Label</label>
                    <input type="text" name="label" class="form-control rounded-3" placeholder="e.g. Ticket Counter 2" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Provider</label>
                    <select name="provider" class="form-select rounded-3">
                        <option value="linkly">Linkly Cloud (PIN pad)</option>
                        <option value="cba_sci">CBA Smart Terminal</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn-save w-100">Add Terminal</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
