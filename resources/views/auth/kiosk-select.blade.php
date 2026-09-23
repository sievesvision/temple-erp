<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no">
  <title>{{ $temple['name'] }} · Choose a Counter</title>
  <link rel="icon" type="image/gif" href="{{ $temple['logo'] }}">

  <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
  <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">

  <style>
    :root {
      --primary-saffron: {{ $temple['primary_color'] }};
      --saffron-dark: {{ $temple['dark_color'] }};
      --primary-gold: {{ $temple['accent_color'] }};
      --dark-bg: {{ $temple['dark_color'] }};
    }

    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'DM Sans', sans-serif; }
    html, body { height: 100%; }

    body {
      background: linear-gradient(160deg, var(--dark-bg) 0%, #1a1512 100%);
      color: #2d2520;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 32px 24px;
    }

    .font-divine { font-family: 'Playfair Display', serif; font-weight: 700; }

    .select-header { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; margin-bottom: 2.25rem; }
    .select-header img { width: 52px; height: 52px; object-fit: contain; }
    .select-header .om-mark { font-size: 2.2rem; line-height: 1; color: var(--primary-gold); }
    .select-title { color: #fff; font-size: 1.6rem; margin-top: 0.5rem; }
    .select-subtitle { color: rgba(255,255,255,0.6); font-size: 0.9rem; }

    .tile-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 18px;
      width: 100%;
      max-width: 760px;
    }

    .pos-tile {
      background: #fdfbf7;
      border-radius: 20px;
      padding: 2rem 1.25rem;
      text-align: center;
      text-decoration: none;
      color: var(--dark-bg);
      box-shadow: 0 18px 40px rgba(0,0,0,0.28);
      transition: transform 0.15s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
    }
    .pos-tile:active { transform: scale(0.96); }
    .pos-tile:hover { color: var(--dark-bg); }

    .pos-tile-icon {
      width: 64px; height: 64px; border-radius: 18px;
      background: color-mix(in srgb, var(--primary-saffron) 12%, white);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.9rem; color: var(--primary-saffron);
    }

    .pos-tile-label { font-weight: 700; font-size: 1.02rem; }
    .pos-tile-sub { font-size: 0.78rem; color: #8a8074; }

    .select-footer { margin-top: 2.5rem; }
    .select-logout {
      color: rgba(255,255,255,0.7);
      text-decoration: none;
      font-size: 0.85rem;
      border: 1px solid rgba(255,255,255,0.3);
      padding: 8px 20px;
      border-radius: 999px;
    }
    .select-logout:hover { color: #fff; border-color: rgba(255,255,255,0.6); }
  </style>
</head>
<body>
  <div class="select-header">
    @if($temple['logo'])
      <img src="{{ $temple['logo'] }}" alt="{{ $temple['name'] }} logo">
    @else
      <span class="om-mark">ॐ</span>
    @endif
    <span class="select-title font-divine">Choose a Counter</span>
    <span class="select-subtitle">This login can open more than one counter — pick one to continue</span>
  </div>

  <div class="tile-grid">
    @foreach($destinations as $destination)
      @if($destination['type'] === 'event')
        <a class="pos-tile" href="{{ route('admin.events.pos', $destination['event_id']) }}">
          <span class="pos-tile-icon"><i class="bi bi-calendar-heart-fill"></i></span>
          <span class="pos-tile-label">{{ $destination['label'] }}</span>
          <span class="pos-tile-sub">{{ \Carbon\Carbon::parse($destination['date'])->format('d M Y') }}</span>
        </a>
      @else
        <a class="pos-tile" href="{{ route('admin.tickets.pos') }}">
          <span class="pos-tile-icon"><i class="bi bi-ticket-perforated-fill"></i></span>
          <span class="pos-tile-label">{{ $destination['label'] }}</span>
          <span class="pos-tile-sub">Point of Sale</span>
        </a>
      @endif
    @endforeach
  </div>

  <div class="select-footer">
    <a class="select-logout" href="{{ route('logout', ['from' => 'kiosk']) }}"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
  </div>
</body>
</html>
