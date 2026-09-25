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
      width: 100%; max-width: 480px;
      background: #fff;
      border-radius: 22px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.12);
      padding: 2.25rem 2rem;
    }

    .settings-title { font-size: 1.3rem; color: var(--dark-bg); margin-bottom: 0.25rem; }
    .settings-subtitle { color: #7a6e63; font-size: 0.88rem; margin-bottom: 1.25rem; }

    .username-status {
      display: flex; align-items: center; gap: 10px;
      background: #f9f3e7; border: 1px solid #e7ddcd; border-radius: 12px;
      padding: 0.85rem 1rem; margin-bottom: 1.5rem; font-size: 0.9rem; color: #5c5248;
    }
    .username-status i { font-size: 1.2rem; color: var(--primary-saffron); }
    .username-status strong { font-family: ui-monospace, monospace; letter-spacing: 0.04em; }

    .destination-card {
      border: 1.5px solid #eee2d0; border-radius: 16px; padding: 1.1rem 1.15rem; margin-bottom: 1rem;
    }
    .destination-name { font-weight: 700; color: var(--dark-bg); font-size: 1rem; margin-bottom: 0.15rem; }
    .destination-pin-status { font-size: 0.82rem; color: #948c7e; margin-bottom: 0.9rem; }
    .destination-pin-status.set { color: #2f7d4f; }

    .form-floating { margin-bottom: 0.75rem; }
    .form-control { min-height: 50px; font-size: 0.95rem; border: 1.5px solid #e7ddcd; border-radius: 12px; }
    .form-control:focus { border-color: var(--primary-saffron); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-saffron) 18%, transparent); }

    .btn-save {
      background: var(--primary-saffron); border: none; color: #fff; font-weight: 700;
      padding: 0.7rem; border-radius: 10px; font-size: 0.92rem; width: 100%; min-height: 46px;
    }

    .field-hint { font-size: 0.76rem; color: #948c7e; margin: -0.4rem 0 0.75rem; }
    .back-link { display: inline-flex; align-items: center; gap: 6px; color: #7a6e63; text-decoration: none; font-size: 0.85rem; margin-bottom: 1.25rem; }
  </style>
</head>
<body>
  <div class="settings-card">
    <a href="{{ $backUrl }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to counter</a>

    <h1 class="settings-title font-divine">Your Kiosk PIN</h1>
    <p class="settings-subtitle">Set or change the 6-digit PIN used for fast sign-in at each of your counters.</p>

    @if($user->username)
      <div class="username-status">
        <i class="bi bi-person-badge-fill"></i>
        <span>Your kiosk username is <strong>{{ $user->username }}</strong> — use it together with your PIN at sign-in.</span>
      </div>
    @else
      <div class="username-status">
        <i class="bi bi-exclamation-circle"></i>
        <span>You don't have a kiosk username yet — ask an admin to assign one before you can set a PIN.</span>
      </div>
    @endif

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

    @if($user->username)
      @foreach($destinations as $destination)
        <div class="destination-card">
          <div class="destination-name">{{ $destination['label'] }}</div>
          <div class="destination-pin-status {{ $destination['pin_set_at'] ? 'set' : '' }}">
            @if($destination['pin_set_at'])
              <i class="bi bi-check-circle-fill"></i> PIN set on {{ $destination['pin_set_at']->format('d M Y') }}
            @else
              No PIN set yet for this counter
            @endif
          </div>

          <form method="POST" action="{{ route('kiosk.pin.update') }}">
            @csrf
            <input type="hidden" name="destination_type" value="{{ $destination['type'] }}">
            @if($destination['type'] === 'event')
              <input type="hidden" name="destination_id" value="{{ $destination['event_id'] }}">
            @endif

            <div class="form-floating">
              <input type="password" class="form-control" name="current_password" id="password_{{ $loop->index }}" placeholder="Password" required autocomplete="current-password">
              <label for="password_{{ $loop->index }}"><i class="bi bi-lock me-1"></i>Your Account Password</label>
            </div>

            <div class="form-floating">
              <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="form-control" name="new_pin" id="new_pin_{{ $loop->index }}" placeholder="New PIN">
              <label for="new_pin_{{ $loop->index }}"><i class="bi bi-grid-3x3-gap-fill me-1"></i>New 6-Digit PIN (optional)</label>
            </div>
            <div class="form-floating">
              <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="form-control" name="new_pin_confirmation" id="new_pin_confirm_{{ $loop->index }}" placeholder="Confirm PIN">
              <label for="new_pin_confirm_{{ $loop->index }}"><i class="bi bi-grid-3x3-gap-fill me-1"></i>Confirm New PIN</label>
            </div>
            <p class="field-hint">Leave the PIN fields blank to just verify your password (e.g. to clear a lockout) without changing this counter's PIN.</p>

            <button type="submit" class="btn btn-save font-divine">Save for {{ $destination['label'] }}</button>
          </form>
        </div>
      @endforeach
    @endif
  </div>
</body>
</html>
