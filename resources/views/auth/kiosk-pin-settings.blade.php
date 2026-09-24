<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no">
  <title>{{ $temple['name'] }} · Kiosk PIN</title>
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
    }

    .settings-title { font-size: 1.3rem; color: var(--dark-bg); margin-bottom: 0.25rem; }
    .settings-subtitle { color: #7a6e63; font-size: 0.88rem; margin-bottom: 1.5rem; }

    .pin-status {
      display: flex; align-items: center; gap: 10px;
      background: #f9f3e7; border: 1px solid #e7ddcd; border-radius: 12px;
      padding: 0.85rem 1rem; margin-bottom: 1.5rem; font-size: 0.9rem; color: #5c5248;
    }
    .pin-status i { font-size: 1.2rem; color: var(--primary-saffron); }

    .form-floating { margin-bottom: 1rem; }
    .form-control { min-height: 54px; font-size: 1rem; border: 1.5px solid #e7ddcd; border-radius: 12px; }
    .form-control:focus { border-color: var(--primary-saffron); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-saffron) 18%, transparent); }

    .btn-save {
      background: var(--primary-saffron); border: none; color: #fff; font-weight: 700;
      padding: 0.9rem; border-radius: 12px; font-size: 1rem; width: 100%; min-height: 54px;
    }

    .section-divider { border: none; border-top: 1px solid #eee2d0; margin: 1.5rem 0; }
    .field-hint { font-size: 0.78rem; color: #948c7e; margin-top: -0.6rem; margin-bottom: 1rem; }

    .back-link { display: inline-flex; align-items: center; gap: 6px; color: #7a6e63; text-decoration: none; font-size: 0.85rem; margin-bottom: 1.25rem; }
  </style>
</head>
<body>
  <div class="settings-card">
    <a href="{{ url()->previous() }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to counter</a>

    <h1 class="settings-title font-divine">Your Kiosk PIN</h1>
    <p class="settings-subtitle">Set or change the 6-digit PIN used for fast counter sign-in.</p>

    <div class="pin-status">
      @if($user->pos_pin_set_at)
        <i class="bi bi-check-circle-fill"></i>
        <span>PIN set on {{ $user->pos_pin_set_at->format('d M Y') }}</span>
      @else
        <i class="bi bi-exclamation-circle"></i>
        <span>No PIN set yet — you're using email &amp; password only.</span>
      @endif
    </div>

    @if(isset($errors) && $errors->any())
      <div class="alert alert-danger mb-3" style="border-radius: 10px;">
        <ul class="mb-0 small ps-3">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if(session('success'))
      <div class="alert alert-success mb-3" style="border-radius: 10px;">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('kiosk.pin.update') }}">
      @csrf

      <div class="form-floating">
        <input type="password" class="form-control @error('current_password') is-invalid @enderror" name="current_password" id="currentPassword" placeholder="Password" required autocomplete="current-password">
        <label for="currentPassword"><i class="bi bi-lock me-1"></i>Your Account Password</label>
      </div>
      <p class="field-hint">Required to confirm it's really you — a PIN alone can never be used to change itself.</p>

      <hr class="section-divider">

      <div class="form-floating">
        <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="form-control @error('new_pin') is-invalid @enderror" name="new_pin" id="newPin" placeholder="New PIN">
        <label for="newPin"><i class="bi bi-grid-3x3-gap-fill me-1"></i>New 6-Digit PIN (optional)</label>
      </div>
      <div class="form-floating">
        <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="form-control" name="new_pin_confirmation" id="newPinConfirm" placeholder="Confirm PIN">
        <label for="newPinConfirm"><i class="bi bi-grid-3x3-gap-fill me-1"></i>Confirm New PIN</label>
      </div>
      <p class="field-hint">Leave blank to just verify your password (e.g. to clear a PIN lockout) without changing your PIN.</p>

      <button type="submit" class="btn btn-save font-divine">Save</button>
    </form>
  </div>
</body>
</html>
