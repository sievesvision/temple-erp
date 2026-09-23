<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no">
  <title>{{ $temple['name'] }} · Kiosk Sign In</title>
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

    /* A dedicated counter/tablet screen, not a browsed page — full-bleed, centered, nothing
       to navigate away to. Deliberately no navbar, no footer, no other links. */
    body {
      background: linear-gradient(160deg, var(--dark-bg) 0%, #1a1512 100%);
      color: #2d2520;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .font-divine { font-family: 'Playfair Display', serif; font-weight: 700; }

    .kiosk-card {
      width: 100%;
      max-width: 440px;
      background: #fdfbf7;
      border-radius: 20px;
      box-shadow: 0 30px 70px rgba(0,0,0,0.35);
      padding: 2.75rem 2.25rem;
      animation: fadeInUp 0.5s ease-out;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(16px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .kiosk-brand { display: flex; flex-direction: column; align-items: center; gap: 10px; margin-bottom: 1.75rem; text-align: center; }
    .kiosk-brand img { width: 56px; height: 56px; object-fit: contain; }
    .kiosk-brand .om-mark { font-size: 2.4rem; line-height: 1; color: var(--primary-saffron); }
    .kiosk-brand-name { font-size: 1.15rem; color: var(--dark-bg); }
    .kiosk-brand-tag { font-size: 0.7rem; letter-spacing: 0.14em; text-transform: uppercase; color: #948c7e; }

    .kiosk-title { font-size: 1.5rem; color: var(--dark-bg); margin-bottom: 0.3rem; text-align: center; }
    .kiosk-subtitle { color: #7a6e63; margin-bottom: 1.75rem; font-size: 0.9rem; text-align: center; }

    .form-floating { margin-bottom: 1.1rem; }
    .form-control { min-height: 58px; font-size: 1.05rem; }
    .form-control:focus { border-color: var(--primary-saffron); box-shadow: 0 0 0 1px var(--primary-saffron); }

    .btn-kiosk-login {
      background: var(--primary-saffron);
      border: 1px solid var(--primary-saffron);
      color: #fff;
      font-weight: 700;
      padding: 1rem;
      border-radius: 12px;
      font-size: 1.1rem;
      width: 100%;
      min-height: 58px;
    }
    .btn-kiosk-login:hover { color: #fff; background: var(--saffron-dark); border-color: var(--saffron-dark); }

    .kiosk-footnote { text-align: center; color: #a89f92; font-size: 0.78rem; margin-top: 1.5rem; }
  </style>
</head>
<body>
  <div class="kiosk-card">
    <div class="kiosk-brand">
      @if($temple['logo'])
        <img src="{{ $temple['logo'] }}" alt="{{ $temple['name'] }} logo">
      @else
        <span class="om-mark">ॐ</span>
      @endif
      <span>
        <span class="kiosk-brand-name font-divine d-block">{{ $temple['brand_title'] ?: $temple['name'] }}</span>
        <span class="kiosk-brand-tag">Counter Terminal</span>
      </span>
    </div>

    <h1 class="kiosk-title font-divine">Kiosk Sign In</h1>
    <p class="kiosk-subtitle">For event donation and ticket counter terminals</p>

    @if(isset($errors) && $errors->any())
      <div class="alert alert-danger mb-3" style="border-radius: 10px; background-color: #fff2f2; border: 1px solid #f3c6c6;">
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
      <div class="alert alert-success mb-3" style="border-radius: 10px; background-color: #f2fdf2; border: 1px solid #c6e8c6;">
        <div class="d-flex align-items-center gap-2 text-success fw-bold">
          <i class="bi bi-check-circle-fill"></i>
          <span>{{ session('success') }}</span>
        </div>
      </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}" id="kioskLoginForm">
      @csrf

      <div class="form-floating">
        <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="kioskEmailInput" placeholder="name@example.com" required value="{{ old('email') }}" autocomplete="username">
        <label for="kioskEmailInput"><i class="bi bi-envelope me-1 text-muted"></i>Counter Email</label>
      </div>

      <div class="form-floating position-relative">
        <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="kioskPasswordInput" placeholder="Password" required autocomplete="current-password">
        <label for="kioskPasswordInput"><i class="bi bi-lock me-1 text-muted"></i>Password</label>
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

    <p class="kiosk-footnote">Contact the temple office if you don't have counter credentials.</p>
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
  </script>
</body>
</html>
