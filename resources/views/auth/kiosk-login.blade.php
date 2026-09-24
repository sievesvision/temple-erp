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
      --dark-bg: {{ $temple['dark_color'] }};
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    /* Body copy, labels and inputs use Inter — the same face the actual counter screens
       (event-pos-donation / ticket-pos) already standardised on for numbers and UI text —
       so the sign-in screen already feels like the device it's the front door to. Playfair
       Display stays reserved for the temple's own name, matching every other page on the
       site that carries it. */
    body, input, button { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    .font-divine { font-family: 'Playfair Display', Georgia, serif; font-weight: 700; }

    html, body { height: 100%; }

    /* A dedicated counter/tablet screen, not a browsed page — full-bleed, centered, nothing
       to navigate away to. Deliberately no navbar, no footer, no other links. Fixed dark
       backdrop by design (this is one physical device's own screen, not a themeable page). */
    body {
      position: relative;
      /* auto, not hidden — a short landscape kiosk viewport (~768px and under is common)
         must never silently clip the welcome header off the top; it scrolls instead. */
      overflow-y: auto;
      color: #2d2520;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background:
        radial-gradient(720px 480px at 18% 8%, color-mix(in srgb, var(--primary-gold) 22%, transparent), transparent 62%),
        radial-gradient(640px 520px at 88% 92%, color-mix(in srgb, var(--primary-saffron) 26%, transparent), transparent 60%),
        linear-gradient(160deg, var(--dark-bg) 0%, #16110d 100%);
    }

    /* A faint repeating dot lattice — the kind of quiet texture a physical kiosk fascia
       has — rather than a flat gradient doing all the work. */
    body::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(255,255,255,0.055) 1.4px, transparent 1.4px);
      background-size: 26px 26px;
      pointer-events: none;
    }

    .kiosk-wrap {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 960px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2.25rem;
    }

    /* ---------- Welcome header ---------- */
    .welcome-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.74rem;
      font-weight: 700;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--primary-gold);
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.18);
      padding: 0.45rem 1rem;
      border-radius: 999px;
      margin-bottom: 1rem;
    }
    .welcome-eyebrow i { font-size: 0.85rem; }

    .welcome-heading { text-align: center; color: #fff; }
    .welcome-heading h1 { font-size: clamp(1.7rem, 4vw, 2.4rem); line-height: 1.2; }
    .welcome-heading h1 .accent { color: var(--primary-gold); }
    .welcome-heading p { margin-top: 0.5rem; color: rgba(255,255,255,0.62); font-size: 1rem; }

    /* ---------- Sign-in card ---------- */
    .kiosk-card {
      width: 100%;
      max-width: 440px;
      background: rgba(253, 251, 247, 0.98);
      border-radius: 24px;
      box-shadow: 0 40px 90px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.06);
      padding: 2.5rem 2.25rem 2.25rem;
      animation: fadeInUp 0.5s ease-out;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(16px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .kiosk-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 1.6rem; }
    .kiosk-brand img { width: 44px; height: 44px; object-fit: contain; flex-shrink: 0; }
    .kiosk-brand .om-mark { font-size: 1.9rem; line-height: 1; color: var(--primary-saffron); flex-shrink: 0; }
    .kiosk-brand-name { font-size: 1.05rem; color: var(--dark-bg); line-height: 1.2; display: block; }
    .kiosk-brand-tag { font-size: 0.68rem; letter-spacing: 0.1em; text-transform: uppercase; color: #948c7e; }

    .form-floating { margin-bottom: 1rem; }
    .form-control {
      min-height: 60px;
      font-size: 1.05rem;
      border: 1.5px solid #e7ddcd;
      border-radius: 14px;
    }
    .form-control:focus { border-color: var(--primary-saffron); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-saffron) 18%, transparent); }
    .form-floating label { color: #948c7e; }

    .btn-kiosk-login {
      /* A self-darkened version of the primary colour, not --saffron-dark (the temple's
         separate, unrelated "dark" brand tone, used for the page backdrop) — pairing an
         arbitrary second brand colour into this gradient reads as muddy rather than warm. */
      background: linear-gradient(135deg, var(--primary-saffron), color-mix(in srgb, var(--primary-saffron) 55%, black));
      border: none;
      color: #fff;
      font-weight: 700;
      padding: 1.05rem;
      border-radius: 14px;
      font-size: 1.08rem;
      width: 100%;
      min-height: 60px;
      box-shadow: 0 14px 30px color-mix(in srgb, var(--primary-saffron) 35%, transparent);
      transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    .btn-kiosk-login:hover { color: #fff; }
    .btn-kiosk-login:active { transform: scale(0.98); }

    .kiosk-footnote { text-align: center; color: #a89f92; font-size: 0.78rem; margin-top: 1.4rem; }
    .kiosk-toggle-link { text-align: center; margin-top: 1.25rem; font-size: 0.9rem; }
    .kiosk-toggle-link a { color: var(--primary-saffron); font-weight: 600; text-decoration: none; }

    /* ---------- PIN keypad ---------- */
    .pin-panel-title { text-align: center; font-weight: 700; color: var(--dark-bg); font-size: 1.05rem; margin-bottom: 0.3rem; }
    .pin-panel-subtitle { text-align: center; color: #948c7e; font-size: 0.85rem; margin-bottom: 1.5rem; }

    .pin-dots { display: flex; justify-content: center; gap: 10px; margin-bottom: 1.75rem; }
    .pin-dot {
      width: 44px; height: 52px;
      border: 1.5px solid #e7ddcd;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem; color: var(--dark-bg);
      background: #fff;
      transition: border-color 0.15s ease;
    }
    .pin-dot.filled { border-color: var(--primary-saffron); }
    .pin-dot.filled::after { content: ''; width: 12px; height: 12px; border-radius: 50%; background: var(--primary-saffron); }
    .pin-panel.shake .pin-dot { border-color: #d9534f; animation: pinShake 0.4s; }
    @keyframes pinShake {
      0%, 100% { transform: translateX(0); }
      20% { transform: translateX(-6px); }
      40% { transform: translateX(6px); }
      60% { transform: translateX(-4px); }
      80% { transform: translateX(4px); }
    }

    .pin-keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .pin-key {
      min-height: 58px;
      border: 1.5px solid #e7ddcd;
      border-radius: 14px;
      background: #fff;
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--dark-bg);
    }
    .pin-key:active { background: #f3ede3; }
    .pin-key.pin-key-back { background: #f3ede3; color: #7a6e63; font-size: 1.1rem; }
    .pin-key.pin-key-submit {
      background: linear-gradient(135deg, var(--primary-saffron), color-mix(in srgb, var(--primary-saffron) 55%, black));
      color: #fff;
      font-size: 1.3rem;
    }
    .pin-key.pin-key-submit:disabled { opacity: 0.4; }
    .pin-locked-note {
      text-align: center;
      background: #fff7ea;
      border: 1px solid #f0dfb8;
      color: #8a6d1f;
      border-radius: 12px;
      padding: 0.9rem 1rem;
      font-size: 0.88rem;
      margin-bottom: 1.25rem;
    }

    /* ---------- Live clock — a quiet, familiar cue that this is a live terminal ---------- */
    .kiosk-clock {
      position: fixed;
      top: env(safe-area-inset-top, 0px);
      right: 0;
      margin: 20px 24px 0 0;
      text-align: right;
      color: rgba(255,255,255,0.55);
      font-variant-numeric: tabular-nums;
      z-index: 1;
    }
    .kiosk-clock .time { font-size: 1.1rem; font-weight: 700; color: rgba(255,255,255,0.85); }
    .kiosk-clock .date { font-size: 0.72rem; letter-spacing: 0.04em; }

    @media (max-width: 480px) {
      .kiosk-clock { display: none; }
    }

    /* A landscape kiosk tablet is commonly ~700-800px tall — tighten the welcome header and
       card padding rather than let the fixed clock/eyebrow/heading stack push the keypad
       (the actually functional part) below the fold. */
    @media (max-height: 820px) {
      .kiosk-wrap { gap: 1.1rem; }
      .welcome-eyebrow { margin-bottom: 0.5rem; }
      .welcome-heading h1 { font-size: clamp(1.3rem, 3.2vw, 1.8rem); }
      .welcome-heading p { display: none; }
      .kiosk-card { padding: 1.5rem 1.75rem 1.5rem; }
      .kiosk-brand { margin-bottom: 1rem; }
      .pin-dots { margin-bottom: 1.1rem; }
      .pin-key { min-height: 48px; }
    }
  </style>
</head>
<body>
  <div class="kiosk-clock">
    <div class="time" id="kioskClockTime">--:--</div>
    <div class="date" id="kioskClockDate"></div>
  </div>

  <div class="kiosk-wrap">
    <div class="welcome-heading">
      <span class="welcome-eyebrow"><i class="bi bi-sun-fill" id="kioskGreetingIcon"></i> <span id="kioskGreeting">Welcome</span></span>
      <h1 class="font-divine">Welcome to <span class="accent">{{ $temple['brand_title'] ?: $temple['name'] }}</span></h1>
      <p>Sign in to open your event donation or ticket counter</p>
    </div>

    <div class="kiosk-card">
      <div class="kiosk-brand">
        @if($temple['logo'])
          <img src="{{ $temple['logo'] }}" alt="{{ $temple['name'] }} logo">
        @else
          <span class="om-mark">ॐ</span>
        @endif
        <span>
          <span class="kiosk-brand-name font-divine">{{ $temple['brand_title'] ?: $temple['name'] }}</span>
          <span class="kiosk-brand-tag d-block">Counter Sign In</span>
        </span>
      </div>

      @if(isset($errors) && $errors->any())
        <div class="alert alert-danger mb-3" style="border-radius: 12px; background-color: #fff2f2; border: 1px solid #f3c6c6;">
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
        <div class="alert alert-success mb-3" style="border-radius: 12px; background-color: #f2fdf2; border: 1px solid #c6e8c6;">
          <div class="d-flex align-items-center gap-2 text-success fw-bold">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ session('success') }}</span>
          </div>
        </div>
      @endif

      @php $showEmailFirst = $pinLocked || $errors->has('email') || $errors->has('password') || $errors->has('g-recaptcha-response'); @endphp

      @if($pinLocked)
        <div class="pin-locked-note"><i class="bi bi-shield-lock-fill me-1"></i> PIN sign-in is temporarily disabled after too many incorrect attempts. Please sign in with your email and password below.</div>
      @else
        {{-- PIN panel — the default, fast path for kiosk-only accounts. --}}
        <div id="kioskPinPanel" class="pin-panel {{ $showEmailFirst ? 'd-none' : '' }}">
          <p class="pin-panel-title">Enter your PIN</p>
          <p class="pin-panel-subtitle">6-digit counter PIN</p>

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
        <form method="POST" action="{{ route('login.post') }}" id="kioskLoginForm">
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

      <p class="kiosk-footnote">Contact the temple office if you don't have counter credentials.</p>
    </div>
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
      if (dateEl) { dateEl.textContent = now.toLocaleDateString([], { weekday: 'short', day: '2-digit', month: 'short' }); }

      const greeting = document.getElementById('kioskGreeting');
      const greetingIcon = document.getElementById('kioskGreetingIcon');
      if (greeting) {
        const hour = now.getHours();
        greeting.textContent = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening';
        if (greetingIcon) { greetingIcon.className = hour >= 6 && hour < 18 ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'; }
      }
    }
    tickKioskClock();
    setInterval(tickKioskClock, 30000);

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
        render();
        pinPanel.classList.remove('shake');
      }
      window.resetPin = resetPin;

      function addDigit(d) {
        if (digits.length >= 6) { return; }
        digits += d;
        render();
        if (digits.length === 6) {
          // Auto-submit — the fast path this whole feature exists for; the arrow key stays
          // visible/tappable too, but nobody should need it in the normal case.
          setTimeout(function () { form.submit(); }, 120);
        }
      }

      document.querySelectorAll('.pin-key[data-digit]').forEach(function (btn) {
        btn.addEventListener('click', function () { addDigit(btn.dataset.digit); });
      });
      document.getElementById('pinBackspace').addEventListener('click', function () {
        digits = digits.slice(0, -1);
        render();
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
