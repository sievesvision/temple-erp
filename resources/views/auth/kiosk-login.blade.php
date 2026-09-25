<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no">
  <title>{{ $temple['name'] }} · Counter Sign In</title>
  <link rel="icon" type="image/gif" href="{{ $temple['logo'] }}">

  <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
  <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
  <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">

  <style>
    :root {
      --primary-saffron: {{ $temple['primary_color'] }};
      --saffron-dark: {{ $temple['dark_color'] }};
      --primary-gold: {{ $temple['accent_color'] }};
      --ink: #1f2430;
      --muted: #7d8494;
      --bg: #f6f4f0;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    /* Inter throughout — the same face the actual counter screens (event-pos-donation /
       ticket-pos) already standardised on, kept self-hosted rather than pulling a heavier
       display face from Google Fonts: this is a login screen, the one page where a kiosk
       device depending on an external CDN just to render its own text is the wrong trade,
       even though a heavier weight would sit closer to the reference's bolder wordmark. */
    body, input, button { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    .font-divine { font-family: 'Playfair Display', Georgia, serif; font-weight: 700; }

    html, body { height: 100%; }

    body {
      position: relative;
      overflow-x: hidden;
      overflow-y: auto;
      color: var(--ink);
      min-height: 100vh;
      background: var(--bg);
      padding: 24px;
      display: flex;
      flex-direction: column;
    }

    /* Soft blurred colour fields instead of a flat background — the reference's own quiet
       blue blobs, re-tinted to the temple's own saffron/gold rather than a generic brand
       blue, so this still reads as part of the same site as every other page. */
    .kiosk-blob { position: fixed; border-radius: 50%; filter: blur(70px); pointer-events: none; z-index: 0; }
    .kiosk-blob.b1 { width: 420px; height: 420px; top: -120px; left: -100px; background: color-mix(in srgb, var(--primary-gold) 35%, transparent); }
    .kiosk-blob.b2 { width: 360px; height: 360px; bottom: -140px; right: -80px; background: color-mix(in srgb, var(--primary-saffron) 28%, transparent); }
    .kiosk-blob.b3 { width: 260px; height: 260px; top: 40%; left: 8%; background: color-mix(in srgb, var(--primary-gold) 20%, transparent); }

    .kiosk-topbar { position: relative; z-index: 1; display: flex; justify-content: flex-end; margin-bottom: 1.5rem; }
    .kiosk-clock { text-align: right; color: var(--muted); font-variant-numeric: tabular-nums; }
    .kiosk-clock .time { font-size: 1.15rem; font-weight: 700; color: var(--ink); }
    .kiosk-clock .date { font-size: 0.75rem; letter-spacing: 0.03em; }

    .kiosk-shell {
      position: relative; z-index: 1;
      flex: 1;
      width: 100%; max-width: 1120px; margin: 0 auto;
      display: flex; flex-direction: column; align-items: center;
      justify-content: center;
      gap: 2.75rem;
      text-align: center;
    }

    /* Hidden by default (narrow/stacked layouts) since the card's own brand block below
       still shows the same logo there — only the wide layout, which hides that block to
       avoid repeating the temple's name twice, needs it here instead. */
    .kiosk-intro-logo { display: none; width: 56px; height: 56px; object-fit: contain; margin-bottom: 1rem; }
    .kiosk-intro-logo.om-mark { display: none; font-size: 2.4rem; line-height: 1; color: var(--primary-saffron); }

    .kiosk-intro h1 { font-size: clamp(1.9rem, 4vw, 2.6rem); font-weight: 800; line-height: 1.2; letter-spacing: -0.01em; }
    .kiosk-intro h1 .accent { color: var(--primary-saffron); }
    .kiosk-intro-tagline { margin-top: 0.6rem; color: var(--muted); font-size: 1.05rem; }

    .kiosk-feature-list { list-style: none; margin-top: 1.75rem; display: flex; flex-direction: column; gap: 0.9rem; align-items: center; }
    .kiosk-feature-list li { display: flex; align-items: center; gap: 12px; font-weight: 600; color: var(--ink); font-size: 1rem; }
    .feature-icon-badge {
      width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .feature-icon-badge.tone-a { background: color-mix(in srgb, var(--primary-saffron) 14%, white); color: var(--primary-saffron); }
    .feature-icon-badge.tone-b { background: color-mix(in srgb, var(--primary-gold) 20%, white); color: color-mix(in srgb, var(--primary-gold) 70%, black); }

    /* ---------- Sign-in card ---------- */
    .kiosk-card {
      width: 100%; max-width: 420px;
      background: #fff;
      border-radius: 26px;
      box-shadow: 0 30px 70px rgba(31,36,48,0.12), 0 0 0 1px rgba(31,36,48,0.03);
      padding: 2.25rem 2rem 1.75rem;
      text-align: center;
    }

    .kiosk-brand { display: flex; flex-direction: column; align-items: center; gap: 8px; margin-bottom: 1.25rem; }
    .kiosk-brand img { width: 52px; height: 52px; object-fit: contain; }
    .kiosk-brand .om-mark { font-size: 2.1rem; line-height: 1; color: var(--primary-saffron); }
    .kiosk-brand-name { font-size: 1.15rem; color: var(--ink); }
    .kiosk-brand-tag { font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); margin-top: 2px; }

    .kiosk-card-title { font-size: 1.15rem; font-weight: 800; color: var(--ink); margin-top: 0.5rem; }
    .kiosk-card-subtitle { color: var(--muted); font-size: 0.88rem; margin-bottom: 1.5rem; }

    .form-floating { margin-bottom: 1rem; }
    .form-control {
      min-height: 58px; font-size: 1.02rem;
      border: 1.5px solid #e8e4dc; border-radius: 14px; background: #faf9f6;
    }
    .form-control:focus { border-color: var(--primary-saffron); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-saffron) 16%, transparent); background: #fff; }
    .form-floating label { color: var(--muted); }

    .btn-kiosk-login {
      background: linear-gradient(135deg, var(--primary-saffron), color-mix(in srgb, var(--primary-saffron) 55%, black));
      border: none; color: #fff; font-weight: 700; padding: 1.05rem; border-radius: 14px;
      font-size: 1.05rem; width: 100%; min-height: 58px;
      box-shadow: 0 14px 30px color-mix(in srgb, var(--primary-saffron) 30%, transparent);
    }
    .btn-kiosk-login:hover { color: #fff; }
    .btn-kiosk-login:active { transform: scale(0.98); }

    .kiosk-toggle-link { text-align: center; margin-top: 1.1rem; font-size: 0.88rem; }
    .kiosk-toggle-link a { color: var(--primary-saffron); font-weight: 600; text-decoration: none; }

    .kiosk-secure-note {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      border-top: 1px solid #eee9e0; margin-top: 1.5rem; padding-top: 1.1rem;
      color: var(--muted); font-size: 0.8rem;
    }

    /* ---------- PIN keypad ---------- */
    .pin-dots { display: flex; justify-content: center; gap: 9px; margin-bottom: 1.5rem; }
    .pin-dot {
      width: 42px; height: 50px; border: 1.5px solid #e8e4dc; border-radius: 12px;
      background: #faf9f6;
      display: flex; align-items: center; justify-content: center;
      transition: border-color 0.15s ease;
    }
    .pin-dot.filled { border-color: var(--primary-saffron); background: #fff; }
    .pin-dot.filled::after { content: ''; width: 11px; height: 11px; border-radius: 50%; background: var(--primary-saffron); }
    .pin-panel.shake .pin-dot { border-color: #d9534f; animation: pinShake 0.4s; }
    @keyframes pinShake {
      0%, 100% { transform: translateX(0); }
      20% { transform: translateX(-6px); } 40% { transform: translateX(6px); }
      60% { transform: translateX(-4px); } 80% { transform: translateX(4px); }
    }

    .pin-keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .pin-key {
      min-height: 56px; border: none; border-radius: 14px; background: #f4f2ee;
      font-size: 1.3rem; font-weight: 700; color: var(--ink);
    }
    .pin-key:active { background: #ebe7df; }
    .pin-key.pin-key-back { color: var(--muted); font-size: 1.05rem; }
    .pin-key.pin-key-submit {
      background: linear-gradient(135deg, var(--primary-saffron), color-mix(in srgb, var(--primary-saffron) 55%, black));
      color: #fff; font-size: 1.25rem;
    }
    .pin-key.pin-key-submit:disabled { opacity: 0.35; }

    .pin-locked-note {
      text-align: center; background: #fff7ea; border: 1px solid #f0dfb8; color: #8a6d1f;
      border-radius: 12px; padding: 0.9rem 1rem; font-size: 0.88rem; margin-bottom: 1.25rem;
    }

    .kiosk-poweredby {
      position: relative; z-index: 1;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      margin-top: 1.75rem; color: var(--muted); font-size: 0.75rem;
    }
    .kiosk-poweredby img { height: 16px; width: auto; opacity: 0.85; }

    @media (min-width: 980px) {
      .kiosk-shell { flex-direction: row; align-items: center; justify-content: space-between; text-align: left; gap: 4rem; }
      .kiosk-intro { flex: 1; }
      .kiosk-intro-logo { display: block; }
      .kiosk-intro-logo.om-mark { display: block; }
      .kiosk-feature-list { align-items: flex-start; }
      .kiosk-card { flex-shrink: 0; }
      /* The left column already names the temple ("Welcome to {temple}") — repeating the
         same name in the card's own header again is pure redundancy, and the ~130px it
         costs is exactly what keypad rows need on a shorter widescreen display (a 1280x800-
         class kiosk monitor with normal browser chrome has less usable height than its
         nominal resolution suggests). */
      .kiosk-brand { display: none; }
      .kiosk-card { padding-top: 1.75rem; }
    }

    @media (max-width: 480px) {
      .kiosk-topbar { justify-content: center; }
    }

    /* These thresholds are deliberately generous, not tuned to exactly clear one nominal
       resolution — a kiosk's REPORTED resolution (1280x853, "HD", an iPad's numbers, etc.)
       is rarely its actual usable browser viewport once OS scaling, a toolbar, or a
       bookmarks bar are accounted for, and guessing that overhead wrong is exactly how a
       page ends up "technically responsive" but still clipped on the device someone is
       actually holding. Two tiers, both purely height-driven (independent of width, since a
       wide monitor can still have a short viewport): moderate first, aggressive once space is
       genuinely tight — always keeping the keypad (the one thing that must never scroll out
       of reach) as the last thing to give up size.
       Tier 1 — moderate: drop the decorative tagline/feature list, trim padding. */
    @media (max-height: 900px) {
      .kiosk-shell { gap: 1.25rem; }
      .kiosk-intro-tagline { display: none; }
      .kiosk-feature-list { display: none; }
      .kiosk-card { padding: 1.35rem 1.5rem 1.1rem; }
      .kiosk-brand { display: none; }
      .kiosk-card-title { margin-top: 0; }
      .kiosk-card-subtitle { margin-bottom: 0.85rem; }
      .pin-dots { margin-bottom: 0.85rem; }
      .pin-key { min-height: 44px; }
      .kiosk-toggle-link { margin-top: 0.75rem; }
      .kiosk-secure-note { margin-top: 0.9rem; padding-top: 0.7rem; }
    }

    /* Tier 2 — aggressive: for a genuinely short viewport (a scaled-down or landscape-
       squeezed browser window), the secure-access footnote is the one thing left that isn't
       load-bearing — everything else here is either the keypad itself or already at a
       touch-usable floor. */
    @media (max-height: 640px) {
      body { padding: 12px; }
      .kiosk-topbar { margin-bottom: 0.5rem; }
      .kiosk-clock .time { font-size: 0.95rem; }
      .kiosk-shell { gap: 0.75rem; }
      .kiosk-intro h1 { font-size: clamp(1.3rem, 3.2vw, 1.7rem); }
      .kiosk-card { padding: 1rem 1.25rem 0.85rem; }
      .kiosk-card-title { font-size: 1rem; }
      .kiosk-card-subtitle { font-size: 0.8rem; margin-bottom: 0.6rem; }
      .pin-dots { gap: 6px; margin-bottom: 0.6rem; }
      .pin-dot { width: 34px; height: 38px; }
      .pin-keypad { gap: 6px; }
      .pin-key { min-height: 38px; font-size: 1.05rem; }
      .kiosk-toggle-link { margin-top: 0.5rem; font-size: 0.8rem; }
      .kiosk-secure-note { display: none; }
      .kiosk-poweredby { display: none; }
    }
  </style>
</head>
<body>
  <div class="kiosk-blob b1"></div>
  <div class="kiosk-blob b2"></div>
  <div class="kiosk-blob b3"></div>

  <div class="kiosk-topbar">
    <div class="kiosk-clock">
      <div class="time" id="kioskClockTime">--:--</div>
      <div class="date" id="kioskClockDate"></div>
    </div>
  </div>

  <div class="kiosk-shell">
    <div class="kiosk-intro">
      {{-- Only shown in the wide/landscape layout — the card's own brand block (with the
           same logo) is hidden there to avoid repeating the temple's name twice, but that
           left the logo itself with nowhere to appear at all. On narrow/stacked layouts the
           card's brand block is still visible, so this stays hidden there instead of showing
           the same mark twice. --}}
      @if($temple['logo'])
        <img src="{{ $temple['logo'] }}" alt="{{ $temple['name'] }} logo" class="kiosk-intro-logo">
      @else
        <span class="kiosk-intro-logo om-mark">ॐ</span>
      @endif
      <h1>Welcome to <span class="accent">{{ $temple['brand_title'] ?: $temple['name'] }}</span></h1>
      <p class="kiosk-intro-tagline">Fast, secure sign-in for your counter team.</p>
      <ul class="kiosk-feature-list">
        <li><span class="feature-icon-badge tone-a"><i class="bi bi-calendar-heart-fill"></i></span> Event Donations</li>
        <li><span class="feature-icon-badge tone-b"><i class="bi bi-ticket-perforated-fill"></i></span> Ticket Sales</li>
      </ul>
    </div>

    <div class="kiosk-card">
      <div class="kiosk-brand">
        @if($temple['logo'])
          <img src="{{ $temple['logo'] }}" alt="{{ $temple['name'] }} logo">
        @else
          <span class="om-mark">ॐ</span>
        @endif
        <span>
          <span class="kiosk-brand-name font-divine d-block">{{ $temple['brand_title'] ?: $temple['name'] }}</span>
          <span class="kiosk-brand-tag">Counter Sign In</span>
        </span>
      </div>

      @if(isset($errors) && $errors->any())
        <div class="alert alert-danger mb-3 text-start" style="border-radius: 12px; background-color: #fff2f2; border: 1px solid #f3c6c6;">
          <div class="d-flex align-items-center gap-2 text-danger fw-bold mb-1">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span>Sign in failed:</span>
          </div>
          <ul class="mb-0 text-danger small ps-4">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @if(session('success'))
        <div class="alert alert-success mb-3 text-start" style="border-radius: 12px; background-color: #f2fdf2; border: 1px solid #c6e8c6;">
          <div class="d-flex align-items-center gap-2 text-success fw-bold">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ session('success') }}</span>
          </div>
        </div>
      @endif

      {{-- Session-timeout / expired-page recovery lands back here with a plain 'error'
           flash (see bootstrap/app.php's TokenMismatchException handler) — this auth page
           has no shared layout/notifications partial to render it otherwise, so without
           this block the message would be silently dropped and the recovery would look like
           an unexplained bounce back to a blank login screen. --}}
      @if(session('error'))
        <div class="alert alert-warning mb-3 text-start" style="border-radius: 12px; background-color: #fff7ea; border: 1px solid #f0dfb8;">
          <div class="d-flex align-items-center gap-2 fw-bold" style="color:#8a6d1f;">
            <i class="bi bi-arrow-clockwise"></i>
            <span>{{ session('error') }}</span>
          </div>
        </div>
      @endif

      @php $showEmailFirst = $pinLocked || $errors->has('email') || $errors->has('password') || $errors->has('g-recaptcha-response'); @endphp

      @if($pinLocked)
        <p class="kiosk-card-title">Kiosk Login</p>
        <div class="pin-locked-note"><i class="bi bi-shield-lock-fill me-1"></i> PIN sign-in is temporarily disabled after too many incorrect attempts. Please sign in with your email and password below.</div>
      @else
        <div id="kioskPinPanel" class="pin-panel {{ $showEmailFirst ? 'd-none' : '' }}">
          <p class="kiosk-card-title">Kiosk Login</p>
          <p class="kiosk-card-subtitle">Enter your PIN to continue</p>

          <form method="POST" action="{{ route('kiosk.pin-login') }}" id="kioskPinForm">
            @csrf
            <input type="hidden" name="pin" id="kioskPinValue">

            <div class="pin-dots" id="pinDots">
              @for($i = 0; $i < 6; $i++)
                <span class="pin-dot" data-dot="{{ $i }}"></span>
              @endfor
            </div>

            <div class="pin-keypad">
              @for($n = 1; $n <= 9; $n++)
                <button type="button" class="pin-key" data-digit="{{ $n }}">{{ $n }}</button>
              @endfor
              <button type="button" class="pin-key pin-key-back" id="pinBackspace"><i class="bi bi-backspace-fill"></i></button>
              <button type="button" class="pin-key" data-digit="0">0</button>
              <button type="submit" class="pin-key pin-key-submit" id="pinSubmit" disabled><i class="bi bi-arrow-right"></i></button>
            </div>
          </form>

          <p class="kiosk-toggle-link"><a href="#" id="showEmailPanelLink">Sign in with email &amp; password instead</a></p>
        </div>
      @endif

      <div id="kioskEmailPanel" class="{{ $showEmailFirst ? '' : 'd-none' }}">
        @if(!$pinLocked)
          <p class="kiosk-card-title">Kiosk Login</p>
          <p class="kiosk-card-subtitle">Sign in with your email &amp; password</p>
        @endif
        {{-- ?from=kiosk survives a CSRF failure (a query string, unlike the POST body, isn't
             invalidated by the token mismatch) — it's how the 419 handler in bootstrap/app.php
             tells this shared login.post submission apart from the general login page's. --}}
        <form method="POST" action="{{ route('login.post', ['from' => 'kiosk']) }}" id="kioskLoginForm">
          @csrf

          <div class="form-floating">
            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="kioskEmailInput" placeholder="name@example.com" required value="{{ old('email') }}" autocomplete="username">
            <label for="kioskEmailInput"><i class="bi bi-envelope me-1"></i>Counter Email</label>
          </div>

          <div class="form-floating position-relative">
            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="kioskPasswordInput" placeholder="Password" required autocomplete="current-password">
            <label for="kioskPasswordInput"><i class="bi bi-lock me-1"></i>Password</label>
            <button type="button" class="btn position-absolute end-0 top-50 translate-middle-y border-0 me-2" onclick="toggleKioskPassword()" style="z-index: 10;" aria-label="Toggle password visibility">
              <i id="kioskEyeIcon" class="bi bi-eye text-muted fs-5"></i>
            </button>
          </div>

          {{-- No "Remember me" here on purpose — a persistent login on a shared counter device
               would defeat the point of a session timeout returning to this screen. --}}

          @include('partials.recaptcha-widget')

          <button class="btn btn-kiosk-login font-divine" type="submit">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
          </button>
        </form>

        @unless($pinLocked)
          <p class="kiosk-toggle-link"><a href="#" id="showPinPanelLink">Use your PIN instead</a></p>
        @endunless
      </div>

      <div class="kiosk-secure-note"><i class="bi bi-lock-fill"></i> Secure access. Please do not share your PIN.</div>
    </div>
  </div>

  <div class="kiosk-poweredby">
    <span>Provided by</span>
    <img src="{{ asset('images/SievesPos_simple_logo.png') }}" alt="SievesPOS">
    <span>(Sievesvision)</span>
  </div>

  <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script>
    function toggleKioskPassword() {
      const input = document.getElementById('kioskPasswordInput');
      const icon = document.getElementById('kioskEyeIcon');
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      icon.classList.toggle('bi-eye', !isHidden);
      icon.classList.toggle('bi-eye-slash', isHidden);
    }

    function tickKioskClock() {
      const now = new Date();
      const timeEl = document.getElementById('kioskClockTime');
      const dateEl = document.getElementById('kioskClockDate');
      if (timeEl) { timeEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
      if (dateEl) { dateEl.textContent = now.toLocaleDateString([], { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }); }
    }
    tickKioskClock();
    setInterval(tickKioskClock, 30000);

    // Keeps this page's embedded CSRF token (and the session it belongs to) alive for as
    // long as the kiosk screen is left open — without this, a token baked in at page-load
    // time goes stale the instant the session idles past SESSION_LIFETIME, OR the moment
    // anyone logs in from a SECOND tab of this same browser (Laravel rotates the session/
    // token on every login; tabs share one cookie jar), long before anyone actually taps a
    // PIN. Refetches on an interval AND immediately whenever the tab becomes visible again
    // (switched back to after using another tab/app), which is exactly when a cross-tab
    // rotation would otherwise go unnoticed until the next failed submit.
    (function () {
      function refreshCsrfToken() {
        fetch('{{ route('kiosk.csrf-token') }}', { headers: { 'Accept': 'application/json' } })
          .then(function (res) { return res.ok ? res.json() : null; })
          .then(function (data) {
            if (!data || !data.token) { return; }
            document.querySelectorAll('input[name="_token"]').forEach(function (input) {
              input.value = data.token;
            });
          })
          .catch(function () { /* a transient network hiccup here just means the next scheduled
                                    or focus-triggered attempt tries again — the existing 419
                                    recovery redirect is still the safety net either way. */ });
      }

      setInterval(refreshCsrfToken, 5 * 60 * 1000);
      document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') { refreshCsrfToken(); }
      });
      window.addEventListener('focus', refreshCsrfToken);
    })();

    (function () {
      const pinPanel = document.getElementById('kioskPinPanel');
      const emailPanel = document.getElementById('kioskEmailPanel');
      const showEmailLink = document.getElementById('showEmailPanelLink');
      const showPinLink = document.getElementById('showPinPanelLink');
      if (showEmailLink) {
        showEmailLink.addEventListener('click', function (e) { e.preventDefault(); pinPanel.classList.add('d-none'); emailPanel.classList.remove('d-none'); });
      }
      if (showPinLink) {
        showPinLink.addEventListener('click', function (e) { e.preventDefault(); emailPanel.classList.add('d-none'); pinPanel.classList.remove('d-none'); resetPin(); });
      }
      if (!pinPanel) { return; }

      const dots = Array.prototype.slice.call(document.querySelectorAll('.pin-dot'));
      const hiddenInput = document.getElementById('kioskPinValue');
      const submitBtn = document.getElementById('pinSubmit');
      const form = document.getElementById('kioskPinForm');
      let digits = '';

      function render() {
        dots.forEach(function (dot, i) { dot.classList.toggle('filled', i < digits.length); });
        hiddenInput.value = digits;
        submitBtn.disabled = digits.length !== 6;
      }

      function resetPin() {
        digits = '';
        submitted = false;
        render();
        pinPanel.classList.remove('shake');
      }
      window.resetPin = resetPin;

      // Guards against a real double-submit: the arrow button becomes tappable the instant
      // the 6th digit lands, at the same moment the auto-submit timer starts counting down —
      // tapping it in that window used to fire the form a SECOND time on top of the pending
      // auto-submit. The first submission succeeds and rotates the session (a normal part of
      // logging in), so the second one — still carrying the token from before that rotation —
      // fails as a stale/mismatched token, which is what actually showed up as "session timed
      // out" despite the PIN being entered correctly. Routing both triggers through this one
      // guarded function makes a submission happen at most once, however it was triggered.
      let submitted = false;
      function submitPin() {
        if (submitted || digits.length !== 6) { return; }
        submitted = true;
        form.submit();
      }

      function addDigit(d) {
        if (digits.length >= 6) { return; }
        digits += d;
        render();
        if (digits.length === 6) {
          // Auto-submit — the fast path this whole feature exists for; the arrow key stays
          // visible/tappable too, but nobody should need it in the normal case.
          setTimeout(submitPin, 120);
        }
      }

      document.querySelectorAll('.pin-key[data-digit]').forEach(function (btn) {
        btn.addEventListener('click', function () { addDigit(btn.dataset.digit); });
      });
      document.getElementById('pinBackspace').addEventListener('click', function () {
        digits = digits.slice(0, -1);
        render();
      });
      // Intercepts the button's own native submit — without this, a manual tap fires the
      // browser's default form submission independently of submitPin()'s guard, which is
      // exactly the double-submit this whole guard exists to prevent.
      submitBtn.addEventListener('click', function (e) {
        e.preventDefault();
        submitPin();
      });

      // A counter PC may have a real keyboard attached, not just a touchscreen.
      document.addEventListener('keydown', function (e) {
        if (pinPanel.classList.contains('d-none')) { return; }
        if (e.key >= '0' && e.key <= '9') { addDigit(e.key); }
        else if (e.key === 'Backspace') { digits = digits.slice(0, -1); render(); }
      });

      // A wrong-PIN response re-renders this same page with a 'pin' validation error —
      // show that as a shake-and-clear instead of a wall of text, matching the keypad's own
      // fast, glanceable style.
      @if($errors->has('pin'))
        pinPanel.classList.add('shake');
        setTimeout(resetPin, 450);
      @endif

      render();
    })();
  </script>
</body>
</html>
