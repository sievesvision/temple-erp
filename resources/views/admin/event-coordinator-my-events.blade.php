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
        html, body { overflow-x: hidden; max-width: 100%; }
        body { margin: 0; min-height: 100vh; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--cream); color: var(--text-primary); }
        /* Serif is reserved for the one big page title, same as the console — event names
           and everything else stay in the plain sans body font, matching how names render
           everywhere else in the admin panel (donation rows, console tables, etc.) instead
           of an inconsistent small-bold-serif treatment. */
        .page-header h2 { font-family: var(--serif); }

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
        .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
        .page-header-icon { width: 46px; height: 46px; border-radius: 50%; background: var(--maroon); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .page-header h2 { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; }
        .page-header p { color: var(--text-secondary); font-size: 0.87rem; margin: 2px 0 0; }

        /* A fixed min-height keeps every card the same height regardless of how long an
           event's name/location text is, so the button lands at the same vertical position
           on every card instead of drifting card to card. min-width:0 on the text column is
           what actually matters for phones/tablets — without it, a flex child holding
           nowrap-ish text refuses to shrink below its own content width and forces the whole
           row (button included) to overflow sideways instead of wrapping. */
        .my-event-card {
            background: var(--white); border: 1px solid var(--border); border-radius: 14px;
            box-shadow: 0 1px 3px rgba(31,42,55,0.04); padding: 18px 22px; margin-bottom: 14px;
            min-height: 78px; display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 12px 16px; transition: border-color 0.15s; max-width: 100%;
        }
        .my-event-card:hover { border-color: var(--gold); }
        .my-event-card .card-info { flex: 1 1 220px; min-width: 0; }
        .my-event-card .event-name { font-weight: 700; font-size: 1.02rem; color: var(--text-primary); margin: 0 0 4px; overflow-wrap: break-word; word-break: break-word; }
        .my-event-card .meta { color: var(--text-secondary); font-size: 0.83rem; display: flex; flex-wrap: wrap; gap: 4px 14px; }
        .my-event-card .meta span { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; overflow-wrap: break-word; word-break: break-word; }

        .btn-open-console {
            background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; border: none;
            padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 6px 16px rgba(200,155,60,0.3);
            white-space: nowrap; flex-shrink: 0;
        }
        .btn-open-console:hover { color: white; opacity: 0.92; }

        .empty-state { background: var(--white); border: 1px dashed var(--border); border-radius: 14px; padding: 48px 24px; text-align: center; color: var(--text-secondary); }
        .empty-state i { font-size: 2.2rem; color: var(--gold); display: block; margin-bottom: 12px; }

        /* Stack the button under the text on phones AND tablet-portrait (iPad is 768px) —
           there's no scenario at these widths where a side-by-side row and a full-width,
           easy-to-tap button both work, so play it safe rather than risk the row squeezing. */
        @media (max-width: 820px) {
            .my-event-card { flex-direction: column; align-items: stretch; }
            .my-event-card .card-info { flex-basis: auto; }
            .btn-open-console { justify-content: center; width: 100%; }
        }
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
                <h2>My Events</h2>
                <p>Events you've been assigned to coordinate — open the console for the full donations table, quick entry, and dashboard.</p>
            </div>
        </div>

        @forelse($events as $event)
        <div class="my-event-card">
            <div class="card-info">
                <div class="event-name">{{ $event->event_name }}</div>
                <div class="meta">
                    <span><i class="bi bi-calendar-event"></i>{{ $event->date_tbc ? 'Date to be confirmed' : date('d M Y', strtotime($event->event_date)) }}</span>
                    @if($event->location)<span><i class="bi bi-geo-alt"></i>{{ $event->location }}</span>@endif
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
