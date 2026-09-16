<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Events · {{ $temple['name'] ?? 'Temple' }}</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A;
            --maroon-dark: #4A0A12;
            --gold: #C89B3C;
            --gold-hover: #A67C2B;
            --cream: #F9F3E7;
            --white: #FFFFFF;
            --border: #F0E5D6;
            --text-primary: #1F2A37;
            --text-secondary: #6B7280;
            --serif: 'Playfair Display', Georgia, serif;
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--cream); color: var(--text-primary); }
        h1, h2 { font-family: var(--serif); }

        .topbar {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            background-image:
                radial-gradient(circle at 8% 30%, rgba(255,255,255,0.05) 0%, transparent 45%),
                radial-gradient(circle at 92% 70%, rgba(255,255,255,0.05) 0%, transparent 45%),
                linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            color: white; padding: 14px 24px; display: flex; align-items: center; gap: 16px;
            box-shadow: 0 4px 18px rgba(74,10,18,0.25);
        }
        .topbar-brand { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
        .topbar-logo { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; background: #fff; padding: 2px; flex-shrink: 0; }
        .topbar-temple-name { font-weight: 800; font-size: 1rem; line-height: 1.2; font-family: var(--serif); }
        .topbar-temple-sub { font-size: 0.72rem; color: rgba(255,255,255,0.6); line-height: 1.2; }
        .admin-pill { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.25); color: white; padding: 7px 14px; border-radius: 999px; font-weight: 700; font-size: 0.85rem; }
        .admin-pill:hover, .admin-pill:focus { background: rgba(255,255,255,0.18); color: white; }

        .page-wrap { max-width: 860px; margin: 0 auto; padding: 28px clamp(16px, 3vw, 32px) 48px; }
        .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
        .page-header-icon { width: 46px; height: 46px; border-radius: 50%; background: var(--maroon); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .page-header h1 { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; }
        .page-header p { color: var(--text-secondary); font-size: 0.87rem; margin: 2px 0 0; }

        .my-event-card { background: var(--white); border: 1px solid var(--border); border-radius: 14px; box-shadow: 0 1px 3px rgba(31,42,55,0.04); padding: 20px 24px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; transition: 0.15s; }
        .my-event-card:hover { border-color: var(--gold); }
        .my-event-card h2 { font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin: 0 0 6px; }
        .my-event-card .meta { color: var(--text-secondary); font-size: 0.85rem; }
        .my-event-card .meta i { margin-right: 4px; }

        .btn-open-console { background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 6px 16px rgba(200,155,60,0.3); }
        .btn-open-console:hover { color: white; opacity: 0.92; }

        .empty-state { background: var(--white); border: 1px dashed var(--border); border-radius: 14px; padding: 48px 24px; text-align: center; color: var(--text-secondary); }
        .empty-state i { font-size: 2.2rem; color: var(--gold); display: block; margin-bottom: 12px; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-brand">
            @if($temple['logo'] ?? null)<img src="{{ $temple['logo'] }}" class="topbar-logo" alt="">@endif
            <div>
                <div class="topbar-temple-name">{{ $temple['name'] ?? 'Temple' }}</div>
                @if($temple['subtitle'] ?? null)<div class="topbar-temple-sub">{{ $temple['subtitle'] }}</div>@endif
            </div>
        </div>
        <div class="dropdown">
            <button class="admin-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i><span>{{ \Illuminate\Support\Str::limit(auth()->user()->name ?? 'Admin', 14) }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('logout') }}"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="page-wrap">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <div class="page-header">
            <div class="page-header-icon"><i class="bi bi-calendar-check"></i></div>
            <div>
                <h1>My Events</h1>
                <p>Events you've been assigned to coordinate — open the console for the full donations table, quick entry, and dashboard.</p>
            </div>
        </div>

        @forelse($events as $event)
        <div class="my-event-card">
            <div>
                <h2>{{ $event->event_name }}</h2>
                <div class="meta">
                    <i class="bi bi-calendar-event"></i>{{ $event->date_tbc ? 'Date to be confirmed' : date('d M Y', strtotime($event->event_date)) }}
                    @if($event->location) &middot; <i class="bi bi-geo-alt"></i>{{ $event->location }} @endif
                </div>
            </div>
            <a href="{{ route('admin.events.console', $event->event_id) }}" class="btn-open-console">
                <i class="bi bi-arrow-right-circle-fill"></i>Open Console
            </a>
        </div>
        @empty
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            You haven't been assigned to coordinate any events yet.
        </div>
        @endforelse
    </div>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
