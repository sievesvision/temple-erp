<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no">
  <title>{{ $temple['name'] }} · Set Your POS PIN</title>
  <link rel="icon" type="image/gif" href="{{ $temple['logo'] }}">

  <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
  <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
  <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">

  <style>
    :root {
      --primary-saffron: {{ $temple['primary_color'] }};
      --dark-bg: {{ $temple['dark_color'] }};
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body, input, button { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    .font-divine { font-family: 'Playfair Display', Georgia, serif; font-weight: 700; }
    html, body { height: 100%; }
    body {
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      padding: 24px; background: #fbf8f1; color: #2d2520;
    }

    .settings-card {
      width: 100%; max-width: 440px;
      background: #fff;
      border-radius: 22px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.12);
      padding: 2.25rem 2rem;
      text-align: center;
    }

    .settings-title { font-size: 1.3rem; color: var(--dark-bg); margin-bottom: 0.25rem; }
    .settings-subtitle { color: #7a6e63; font-size: 0.88rem; margin-bottom: 1.5rem; }

    .form-floating { margin-bottom: 0.75rem; text-align: left; }
    .form-control { min-height: 50px; font-size: 0.95rem; border: 1.5px solid #e7ddcd; border-radius: 12px; }
    .form-control:focus { border-color: var(--primary-saffron); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-saffron) 18%, transparent); }

    .btn-save {
      background: var(--primary-saffron); border: none; color: #fff; font-weight: 700;
      padding: 0.7rem; border-radius: 10px; font-size: 0.92rem; width: 100%; min-height: 46px;
    }

    .invalid-icon { font-size: 2.6rem; color: #c0392b; margin-bottom: 1rem; }
    .valid-icon { font-size: 2.6rem; color: var(--primary-saffron); margin-bottom: 1rem; }
  </style>
</head>
<body>
  @include('partials.test-banner')
  <div class="settings-card">
    @if(!$valid)
      <i class="bi bi-exclamation-triangle-fill invalid-icon"></i>
      <h1 class="settings-title font-divine">Link expired</h1>
      <p class="settings-subtitle">This PIN setup link is invalid or has already been used. Ask an admin to send you a new one.</p>
      <a href="{{ route('pos.login') }}" class="btn btn-save font-divine">Back to sign in</a>
    @else
      <i class="bi bi-grid-3x3-gap-fill valid-icon"></i>
      <h1 class="settings-title font-divine">Set your POS PIN</h1>
      <p class="settings-subtitle">Vanakkam, {{ $name }}. Choose the 6-digit PIN you'll use to sign in at the counter — it works at every event or ticket counter you're authorised for.</p>

      @if(isset($errors) && $errors->any())
        <div class="alert alert-danger mb-3 text-start" style="border-radius: 10px;">
          <ul class="mb-0 small ps-3">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('pos.pin.activate.submit', ['user' => $userId, 'token' => $token]) }}">
        @csrf
        <div class="form-floating">
          <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="form-control" name="new_pin" id="new_pin" placeholder="New PIN" required autofocus>
          <label for="new_pin"><i class="bi bi-grid-3x3-gap-fill me-1"></i>New 6-Digit PIN</label>
        </div>
        <div class="form-floating">
          <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="form-control" name="new_pin_confirmation" id="new_pin_confirmation" placeholder="Confirm PIN" required>
          <label for="new_pin_confirmation"><i class="bi bi-grid-3x3-gap-fill me-1"></i>Confirm New PIN</label>
        </div>

        <button type="submit" class="btn btn-save font-divine mt-2">Set My PIN</button>
      </form>
    @endif
  </div>
</body>
</html>
