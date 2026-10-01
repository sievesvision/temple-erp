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
    <link href="{{ asset('css/eft-terminal-registry.css') }}?v={{ @filemtime(public_path('css/eft-terminal-registry.css')) }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A; --maroon-dark: #4A0A12; --gold: #C89B3C; --gold-hover: #A67C2B;
            --cream: #F9F3E7; --white: #FFFFFF; --border: #E5E7EB; --text-primary: #1F2A37;
            --text-secondary: #6B7280; --success: #10B981; --error: #EF4444;
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
    </style>
</head>
<body>
    @include('partials.test-banner')
    {{-- Only a genuine standalone visit (not the POS-page popup, see EftTerminalController::
         index()'s own docblock on $embedded) gets this page's own topbar — showing it inside
         that small popup duplicated the popup's own title bar above it for no reason. --}}
    @if(!$embedded)
    <header class="topbar">
        <h1><i class="bi bi-credit-card-2-front-fill me-2"></i>EFT Terminal Settings</h1>
        <a href="{{ $isSystemAdmin ? route('admin.settings') : route('admin.dashboard') }}" class="topbar-btn"><i class="bi bi-arrow-left"></i>Back</a>
        <a href="{{ route('logout') }}" class="topbar-btn"><i class="bi bi-box-arrow-right"></i>Logout</a>
    </header>
    @endif

    <div class="body-wrap">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        @include('admin.partials.eft-terminal-registry', [
            'activeTerminals' => $activeTerminals, 'inactiveTerminals' => $inactiveTerminals,
            'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode,
            'canManageRegistryLevel' => $canManageRegistryLevel, 'isSystemAdmin' => $isSystemAdmin,
            'allOperational' => $allOperational,
        ])
    </div>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
