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

        /* ---------- Same page-header / card-panel idiom as the Event Console ---------- */
        .page-wrap { max-width: 920px; margin: 0 auto; padding: 28px clamp(16px, 3vw, 32px) 48px; }
        .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
        .page-header-icon { width: 46px; height: 46px; border-radius: 50%; background: var(--maroon); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .page-header h2 { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; }
        .page-header p { color: var(--text-secondary); font-size: 0.87rem; margin: 2px 0 0; }

        .card-panel { background: var(--white); border: 1px solid var(--border); border-radius: 14px; box-shadow: 0 1px 3px rgba(31,42,55,0.04); overflow: hidden; }

        table.my-events-table { width: 100%; border-collapse: collapse; }
        table.my-events-table th, table.my-events-table td { padding: 16px 20px; text-align: left; font-size: 0.88rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
        table.my-events-table tbody tr:last-child th, table.my-events-table tbody tr:last-child td { border-bottom: none; }
        table.my-events-table th { background: var(--cream); font-weight: 700; color: var(--text-secondary); text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.04em; }
        table.my-events-table td.col-event { font-weight: 700; color: var(--text-primary); font-size: 0.95rem; }
        table.my-events-table td.col-meta { color: var(--text-secondary); white-space: nowrap; }
        table.my-events-table td.col-meta i { margin-right: 4px; }
        table.my-events-table td.col-action { text-align: right; }

        .btn-open-console { background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 6px 16px rgba(200,155,60,0.3); white-space: nowrap; }
        .btn-open-console:hover { color: white; opacity: 0.92; }

        .empty-state { background: var(--white); border: 1px dashed var(--border); border-radius: 14px; padding: 48px 24px; text-align: center; color: var(--text-secondary); }
        .empty-state i { font-size: 2.2rem; color: var(--gold); display: block; margin-bottom: 12px; }

        @media (max-width: 640px) {
            table.my-events-table, table.my-events-table tbody, table.my-events-table tr { display: block; width: 100%; }
            table.my-events-table thead { display: none; }
            table.my-events-table tr { border-bottom: 1px solid var(--border); padding: 16px 20px; }
            table.my-events-table tr:last-child { border-bottom: none; }
            table.my-events-table td { display: block; padding: 2px 0; border-bottom: none; }
            table.my-events-table td.col-action { text-align: left; margin-top: 12px; }
            table.my-events-table td.col-action .btn-open-console { width: 100%; justify-content: center; }
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
        @if($loop->first)
        <div class="card-panel">
            <table class="my-events-table">
                <thead>
                    <tr><th>Event</th><th>Date</th><th>Location</th><th class="text-end">Action</th></tr>
                </thead>
                <tbody>
        @endif
                    <tr>
                        <td class="col-event">{{ $event->event_name }}</td>
                        <td class="col-meta"><i class="bi bi-calendar-event"></i>{{ $event->date_tbc ? 'Date to be confirmed' : date('d M Y', strtotime($event->event_date)) }}</td>
                        <td class="col-meta">@if($event->location)<i class="bi bi-geo-alt"></i>{{ $event->location }}@else — @endif</td>
                        <td class="col-action">
                            <a href="{{ route('admin.events.console', $event->event_id) }}" class="btn-open-console">
                                <i class="bi bi-arrow-right-circle-fill"></i>Open Console
                            </a>
                        </td>
                    </tr>
        @if($loop->last)
                </tbody>
            </table>
        </div>
        @endif
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
