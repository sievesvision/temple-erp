<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $temple['name'] }} · Set New Password</title>
  <link rel="icon" type="image/gif" href="{{ $temple['logo'] }}">

  <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
  <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">

  <style>
    :root {
      --primary-saffron: {{ $temple['primary_color'] }};
      --saffron-dark: {{ $temple['dark_color'] }};
      --primary-gold: {{ $temple['accent_color'] }};
      --saffron-gradient: linear-gradient(135deg, {{ $temple['accent_color'] }} 0%, {{ $temple['primary_color'] }} 50%, {{ $temple['dark_color'] }} 100%);
      --gold-gradient: linear-gradient(135deg, {{ $temple['accent_color'] }} 0%, {{ $temple['primary_color'] }} 50%, {{ $temple['dark_color'] }} 100%);
      --light-bg: #fbf8f1;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'DM Sans', sans-serif; }
    body {
      background: var(--light-bg);
      color: #2d2520;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    .font-divine { font-family: 'Playfair Display', serif; font-weight: 700; }
    .forgot-card {
      background: white;
      border: 1px solid rgba(184, 134, 58, 0.15);
      box-shadow: 0 10px 40px rgba(23, 17, 10, 0.05);
      border-radius: 24px;
      width: 100%;
      max-width: 500px;
      overflow: hidden;
    }
    .card-header-banner { background: var(--saffron-gradient); padding: 30px 24px; text-align: center; color: white; }
    .card-header-banner h2 { font-size: 1.6rem; margin-bottom: 6px; letter-spacing: 0.5px; }
    .card-body-content { padding: 40px 32px; }
    .form-control:focus { border-color: var(--primary-saffron); box-shadow: 0 0 0 0.25rem rgba(255, 111, 0, 0.15); }
    .btn-gold {
      background: var(--gold-gradient);
      border: none;
      color: white;
      padding: 12px 24px;
      border-radius: 40px;
      font-weight: 600;
      transition: all 0.3s;
      width: 100%;
    }
    .btn-gold:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(184, 134, 58, 0.25); color: white; }
  </style>
</head>
<body>
  <div class="forgot-card animate__animated animate__fadeIn">
    <div class="card-header-banner">
      <h2 class="font-divine">{{ $temple['name'] }}</h2>
      <p class="mb-0 text-white-50 small text-uppercase letter-spacing-1">Set New Password</p>
    </div>

    <div class="card-body-content">
      @if ($errors->any())
        <div class="alert alert-danger border-0 rounded-4 p-3 mb-4 small" style="background: #fee2e2; color: #991b1b;">
          @foreach ($errors->all() as $error)
            <div><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $error }}</div>
          @endforeach
        </div>
      @endif

      <h5 class="fw-bold mb-3" style="color: #2d1f0e;">Choose a new password</h5>
      <p class="text-muted small mb-4">Enter and confirm a new password for {{ $email }}.</p>

      <form method="POST" action="{{ route('admin-reset.post') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="mb-3">
          <label class="form-label fw-semibold small">New Password</label>
          <input type="password" name="password" class="form-control rounded-3" required autofocus>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold small">Confirm New Password</label>
          <input type="password" name="password_confirmation" class="form-control rounded-3" required>
        </div>

        <button type="submit" class="btn btn-gold">Set New Password</button>
      </form>

      <div class="text-center mt-4">
        <a href="{{ route('login') }}" class="small text-decoration-none" style="color: #7b6b5a;">
          <i class="bi bi-arrow-left me-1"></i> Back to Login
        </a>
      </div>
    </div>
  </div>
</body>
</html>
