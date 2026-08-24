<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🛕 Shree Mandir ERP · Reset Password</title>

  <!-- Bootstrap 5.3 + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Google Fonts: Cinzel & Outfit -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700;900&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary-saffron: #ff6f00;
      --saffron-dark: #e65100;
      --primary-gold: #b8863a;
      --gold-light: #e8d0a7;
      --gold-dark: #9c6c28;
      --dark-bg: #17110a;
      --light-bg: #fdfbf7;
      --saffron-gradient: linear-gradient(135deg, #ff9e00 0%, #ff6f00 50%, #e65100 100%);
      --gold-gradient: linear-gradient(135deg, #c9933b 0%, #b8863a 50%, #9c6c28 100%);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Outfit', sans-serif;
    }

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

    .font-divine {
      font-family: 'Cinzel', serif;
      font-weight: 700;
    }

    .forgot-card {
      background: white;
      border: 1px solid rgba(184, 134, 58, 0.15);
      box-shadow: 0 10px 40px rgba(23, 17, 10, 0.05);
      border-radius: 24px;
      width: 100%;
      max-width: 500px;
      overflow: hidden;
    }

    .card-header-banner {
      background: var(--saffron-gradient);
      padding: 30px 24px;
      text-align: center;
      color: white;
    }

    .card-header-banner h2 {
      font-size: 1.6rem;
      margin-bottom: 6px;
      letter-spacing: 0.5px;
    }

    .card-body-content {
      padding: 40px 32px;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-saffron);
      box-shadow: 0 0 0 0.25rem rgba(255, 111, 0, 0.15);
    }

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

    .btn-gold:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(184, 134, 58, 0.25);
      color: white;
    }

    .btn-outline-gold {
      background: transparent;
      border: 1.5px solid var(--primary-gold);
      color: var(--primary-gold);
      padding: 10px 24px;
      border-radius: 40px;
      font-weight: 600;
      transition: all 0.3s;
      width: 100%;
    }

    .btn-outline-gold:hover:not(:disabled) {
      background: var(--gold-gradient);
      color: white;
      border-color: transparent;
    }

    .otp-box {
      font-size: 2rem;
      font-weight: 800;
      text-align: center;
      letter-spacing: 12px;
      font-family: monospace;
      color: var(--primary-saffron);
      background: rgba(255, 111, 0, 0.03);
      border: 2px dashed var(--primary-saffron) !important;
      border-radius: 14px;
      padding: 12px;
    }

    .timer-badge {
      background: #fdf2f2;
      color: #9b1c1c;
      padding: 6px 16px;
      border-radius: 40px;
      font-weight: 600;
      font-size: 0.85rem;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
  </style>
</head>
<body>
  @include('layouts.partials.notifications')

  <div class="forgot-card animate__animated animate__fadeIn">
    <!-- Header Banner -->
    <div class="card-header-banner">
      <h2 class="font-divine">🛕 Shree Mandir</h2>
      <p class="mb-0 text-white-50 small text-uppercase letter-spacing-1">Reset Account Password</p>
    </div>

    <!-- Card Body Content -->
    <div class="card-body-content">
      @if(session('success'))
          <div class="alert alert-success border-0 rounded-4 p-3 mb-4 small" style="background: #d1fae5; color: #065f46;">
              <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
          </div>
      @endif

      @php
        $step = $step ?? 1;
      @endphp

      @if($step == 1)
        <!-- STEP 1: Enter Email -->
        <h5 class="fw-bold mb-3" style="color: #2d1f0e;">Forgot Password?</h5>
        <p class="text-muted small mb-4">Please enter your registered email address. We will verify your account and email you a 6-digit OTP code to reset your password.</p>

        <form action="{{ route('forgot-password.post') }}" method="POST">
          @csrf
          <div class="mb-4">
            <label class="form-label fw-semibold">Email Address</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
              <input type="email" name="email" class="form-control rounded-end-3 border-start-0 @error('email') is-invalid @enderror" placeholder="e.g. yourname@domain.com" value="{{ old('email') }}" required>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <button type="submit" class="btn btn-gold mb-3">Send Verification OTP</button>
          <div class="text-center">
            <a href="{{ route('login') }}" class="text-decoration-none small text-warning fw-semibold"><i class="bi bi-arrow-left"></i> Back to Login</a>
          </div>
        </form>

      @elseif($step == 2)
        <!-- STEP 2: Enter OTP -->
        <h5 class="fw-bold mb-3" style="color: #2d1f0e;">Enter OTP Code</h5>
        <p class="text-muted small mb-3">An OTP code has been sent to <strong>{{ session('forgot_email') }}</strong>. Please check your spam folder if you do not receive it in 2 minutes.</p>

        <form action="{{ route('forgot-password.verify.post') }}" method="POST">
          @csrf
          <div class="mb-3 text-center">
            <span class="timer-badge">
              <i class="bi bi-clock-history"></i> OTP expires in: <span id="otp-timer">10:00</span>
            </span>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold d-block text-center">Verification Code</label>
            <input type="text" name="otp" id="otp-input" class="form-control otp-box @error('otp') is-invalid @enderror" placeholder="000000" maxlength="6" autocomplete="off" required>
            @error('otp')
              <div class="invalid-feedback text-center mt-2">{{ $message }}</div>
            @enderror
          </div>

          <button type="submit" class="btn btn-gold mb-3">Verify OTP</button>

          <!-- Resend button container -->
          <div class="row g-2 mb-3">
            <div class="col-6">
              <button type="button" id="resendBtn" class="btn btn-outline-gold" disabled>
                Resend OTP <span id="cooldown-timer">(60s)</span>
              </button>
            </div>
            <div class="col-6">
              <a href="{{ route('forgot-password') }}?restart=1" class="btn btn-outline-secondary w-100 rounded-pill py-2" style="font-weight: 600; font-size:0.95rem;">Cancel / Restart</a>
            </div>
          </div>
        </form>

      @elseif($step == 3)
        <!-- STEP 3: Enter New Password -->
        <h5 class="fw-bold mb-3" style="color: #2d1f0e;">Set New Password</h5>
        <p class="text-muted small mb-4">Your email has been verified. Please create a strong, secure new password for your account.</p>

        <form action="{{ route('forgot-password.reset.post') }}" method="POST">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-semibold">New Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
              <input type="password" name="password" class="form-control rounded-end-3 border-start-0 @error('password') is-invalid @enderror" placeholder="Min 6 characters" required>
              @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Confirm Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-shield-lock text-muted"></i></span>
              <input type="password" name="password_confirmation" class="form-control rounded-end-3 border-start-0" placeholder="Re-enter password" required>
            </div>
          </div>

          <button type="submit" class="btn btn-gold mb-3">Update Password</button>
          <div class="text-center">
            <a href="{{ route('forgot-password') }}?restart=1" class="text-decoration-none small text-warning fw-semibold"><i class="bi bi-arrow-left"></i> Restart Reset</a>
          </div>
        </form>
      @endif
    </div>
  </div>

  @if($step == 2)
  <script>
    document.addEventListener("DOMContentLoaded", function() {
        const otpInput = document.getElementById('otp-input');
        
        // Auto focus and auto submit on 6th digit
        if (otpInput) {
            otpInput.focus();
            otpInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 6) {
                    this.form.submit();
                }
            });
        }

        // OTP expiration countdown (10 minutes)
        let expirySecs = 600;
        const expiryTimer = document.getElementById('otp-timer');
        
        const expiryInterval = setInterval(function() {
            expirySecs--;
            if (expirySecs <= 0) {
                clearInterval(expiryInterval);
                expiryTimer.textContent = "Expired";
                alert('Your OTP has expired. Please restart the password reset process.');
                window.location.href = "{{ route('forgot-password') }}?restart=1";
            } else {
                let mins = Math.floor(expirySecs / 60);
                let secs = expirySecs % 60;
                expiryTimer.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
            }
        }, 1000);

        // Resend cooldown timer (60 seconds)
        let cooldownSecs = 60;
        const cooldownTimer = document.getElementById('cooldown-timer');
        const resendBtn = document.getElementById('resendBtn');

        const cooldownInterval = setInterval(function() {
            cooldownSecs--;
            if (cooldownSecs <= 0) {
                clearInterval(cooldownInterval);
                resendBtn.disabled = false;
                cooldownTimer.textContent = "";
            } else {
                cooldownTimer.textContent = "(" + cooldownSecs + "s)";
            }
        }, 1000);

        // AJAX Resend OTP trigger
        resendBtn.addEventListener('click', function() {
            resendBtn.disabled = true;
            cooldownSecs = 60;
            cooldownTimer.textContent = "(60s)";
            
            // Re-run cooldown interval
            const newInterval = setInterval(function() {
                cooldownSecs--;
                if (cooldownSecs <= 0) {
                    clearInterval(newInterval);
                    resendBtn.disabled = false;
                    cooldownTimer.textContent = "";
                } else {
                    cooldownTimer.textContent = "(" + cooldownSecs + "s)";
                }
            }, 1000);

            // Fetch AJAX post
            fetch("{{ route('forgot-password.resend') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('A new OTP code has been successfully emailed to you.');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error sending verification code. Please try again.');
            });
        });
    });
  </script>
  @endif

</body>
</html>
