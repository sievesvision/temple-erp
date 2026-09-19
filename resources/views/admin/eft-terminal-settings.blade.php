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
        .btn-save { padding: 10px 22px; border-radius: 10px; border: none; background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; font-size: 0.92rem; }
    </style>
</head>
<body>
    @php $temple = \App\Models\Setting::templeBranding(); @endphp
    <header class="topbar">
        <h1><i class="bi bi-credit-card-2-front-fill me-2"></i>EFT Terminal Settings</h1>
        <a href="{{ url()->previous() }}" class="topbar-btn"><i class="bi bi-arrow-left"></i>Back</a>
        <a href="{{ route('logout') }}" class="topbar-btn"><i class="bi bi-box-arrow-right"></i>Logout</a>
    </header>

    <div class="body-wrap">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <p class="text-muted small mb-3">Each terminal below is independently paired via Linkly Cloud, so more than one physical (or virtual test) PIN pad can be in use at once — e.g. one for the Ticket Kiosk and another for an event's donation POS, running simultaneously. Mode: <strong class="text-uppercase">{{ $linklyMode }}</strong> (set by the <code>LINKLY_*</code> credentials configured on the server).</p>

        @foreach($eftTerminals as $terminal)
        <div class="card-panel">
            <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                <strong>{{ $terminal->label }}</strong>
                <span class="text-muted small">({{ $terminal->key }})</span>
                @if($terminal->is_default)<span class="badge bg-primary">Default</span>@endif
                <span class="status-pill {{ $terminal->isPaired($linklyMode) ? 'paid' : 'cancelled' }}">{{ $terminal->isPaired($linklyMode) ? 'Paired' : 'Not Paired' }}</span>
            </div>
            <form action="{{ route('admin.eft.pair') }}" method="POST" class="row g-3 align-items-end mb-2">
                @csrf
                <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                <div class="col-md-5">
                    <input type="text" name="pair_code" class="form-control rounded-3" placeholder="6-digit code from the terminal" maxlength="10" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">{{ $terminal->isPaired($linklyMode) ? 'Re-pair' : 'Pair' }}</button>
                </div>
            </form>
            @if($isSystemAdmin)
            <div class="d-flex gap-2">
                @if(!$terminal->is_default)
                <form action="{{ route('admin.eft-terminals.setDefault', $terminal) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Set as Default</button>
                </form>
                <form action="{{ route('admin.eft-terminals.destroy', $terminal) }}" method="POST" onsubmit="return confirm('Remove this terminal?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                </form>
                @endif
            </div>
            @endif
        </div>
        @endforeach

        <div class="card-panel" style="background:var(--cream);">
            <div class="fw-semibold mb-2">Add Another Terminal</div>
            <form action="{{ route('admin.eft-terminals.store') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label small">Key (unique, no spaces)</label>
                    <input type="text" name="key" class="form-control rounded-3" placeholder="e.g. ticket-counter-2" maxlength="40" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Label</label>
                    <input type="text" name="label" class="form-control rounded-3" placeholder="e.g. Ticket Counter 2" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn-save w-100">Add Terminal</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
