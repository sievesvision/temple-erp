<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🛕 Shree Mandir ERP · Email Verification</title>

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
      --overlay-gradient: linear-gradient(135deg, rgba(23, 17, 10, 0.9) 0%, rgba(230, 81, 0, 0.75) 100%);
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
      overflow-x: hidden;
    }

    .font-divine {
      font-family: 'Cinzel', serif;
      font-weight: 700;
    }

    /* ----- navbar ----- */
    .navbar-custom {
      background: rgba(253, 251, 247, 0.85);
      backdrop-filter: blur(20px) saturate(180%);
      -webkit-backdrop-filter: blur(20px) saturate(180%);
      border-bottom: 1px solid rgba(184, 134, 58, 0.15);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.02);
      transition: all 0.4s ease;
      z-index: 1050;
    }

    .navbar-custom .navbar-brand {
      font-weight: 900;
      font-size: 1.6rem;
      letter-spacing: 0.5px;
      color: #2b1f13;
      text-decoration: none;
    }

    .navbar-custom .navbar-brand i {
      color: var(--primary-saffron);
      text-shadow: 0 2px 10px rgba(255, 111, 0, 0.2);
    }

    .navbar-custom .nav-link {
      color: #4a3e35;
      font-weight: 500;
      padding: 0.5rem 1.1rem;
      border-radius: 30px;
      margin: 0 0.1rem;
      transition: all 0.3s ease;
      font-size: 0.95rem;
      text-decoration: none;
    }

    .navbar-custom .nav-link:hover, 
    .navbar-custom .nav-link.active {
      color: var(--primary-saffron);
      background: rgba(255, 111, 0, 0.06);
    }

    .btn-saffron {
      background: var(--saffron-gradient);
      border: 1px solid var(--saffron-dark);
      color: #fff !important;
      font-weight: 600;
      padding: 0.6rem 1.6rem;
      border-radius: 30px;
      transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
      box-shadow: 0 4px 15px rgba(255, 111, 0, 0.2);
      text-decoration: none;
    }

    .btn-saffron:hover {
      background: var(--saffron-dark);
      transform: translateY(-1.5px);
      box-shadow: 0 6px 20px rgba(255, 111, 0, 0.3);
    }

    .btn-outline-saffron {
      border: 2px solid var(--primary-saffron);
      color: var(--primary-saffron) !important;
      background: transparent;
      font-weight: 600;
      padding: 0.5rem 1.6rem;
      border-radius: 30px;
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .btn-outline-saffron:hover {
      background: var(--primary-saffron);
      color: #fff !important;
      box-shadow: 0 6px 20px rgba(255, 111, 0, 0.2);
    }

    /* ----- verification container ----- */
    .verify-container {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 6rem 1.5rem 4rem 1.5rem;
      margin-top: 80px;
    }

    .verify-card {
      width: 100%;
      max-width: 500px;
      background: #ffffff;
      border: 2px solid #ebdcc5;
      border-radius: 24px;
      padding: 2.5rem 2rem;
      box-shadow: 0 10px 30px rgba(184, 134, 58, 0.06);
      animation: fadeInUp 0.8s ease-out;
      text-align: center;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .card-title {
      font-size: 2rem;
      color: var(--dark-bg);
      margin-bottom: 0.5rem;
    }

    .card-subtitle {
      color: #7a6e63;
      margin-bottom: 2rem;
      font-size: 0.95rem;
    }

    .email-display {
      background: rgba(184, 134, 58, 0.05);
      border: 1px solid rgba(184, 134, 58, 0.15);
      border-radius: 12px;
      padding: 0.8rem 1.2rem;
      font-weight: 600;
      color: #72501a;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 2rem;
      font-size: 0.95rem;
    }

    .otp-input-box {
      font-size: 2rem;
      font-weight: 800;
      letter-spacing: 12px;
      text-align: center;
      padding: 0.8rem;
      border-radius: 14px;
      border: 2px solid #ebdcc5;
      width: 100%;
      max-width: 280px;
      margin: 0 auto 1.5rem auto;
      color: var(--saffron-dark);
      background: #fdfbf7;
    }

    .otp-input-box:focus {
      border-color: var(--primary-saffron);
      box-shadow: 0 0 0 0.25rem rgba(255, 111, 0, 0.15);
      outline: none;
    }

    .btn-verify {
      background: var(--saffron-gradient);
      border: 1px solid var(--saffron-dark);
      color: #fff;
      font-weight: 600;
      padding: 0.9rem;
      border-radius: 30px;
      font-size: 1.1rem;
      box-shadow: 0 4px 15px rgba(255, 111, 0, 0.25);
      transition: all 0.3s ease;
      width: 100%;
      margin-bottom: 1.5rem;
    }

    .btn-verify:hover {
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(255, 111, 0, 0.4);
    }

    .btn-resend {
      font-weight: 600;
      color: var(--primary-saffron);
      text-decoration: none;
      background: transparent;
      border: none;
      cursor: pointer;
      font-size: 0.95rem;
      transition: all 0.2s ease;
    }

    .btn-resend:hover:not(:disabled) {
      color: var(--saffron-dark);
      text-decoration: underline;
    }

    .btn-resend:disabled {
      color: #a39589;
      cursor: not-allowed;
    }

    .timer-text {
      color: #7a6e63;
      font-size: 0.85rem;
      margin-top: 0.5rem;
    }
  </style>
</head>
<body>
  @include('layouts.partials.notifications')

  <!--  NAVBAR (Sticky Glassmorphism)               -->
  <nav class="navbar navbar-expand-lg navbar-custom py-3 fixed-top" id="mainNavbar">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="{{ route('home') }}">
        <i class="bi bi-temple me-2"></i>
        <span>SHREE MANDIR</span>
      </a>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
              aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center gap-1">
          <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#features">Features</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#poojas">Book Pooja</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#donations">Donations</a></li>

          @auth
            @php
              $role = auth()->user()->role;
              $activeRole = session('active_role', $role);
              $dashboardRoute = 'login';
              if ($activeRole === 'Admin') $dashboardRoute = 'admin.dashboard';
              elseif ($activeRole === 'Devotee') $dashboardRoute = 'devotee.dashboard';
              elseif ($activeRole === 'Priest') $dashboardRoute = 'priest.dashboard';
              elseif ($activeRole === 'Trustee') $dashboardRoute = 'trustee.dashboard';
              elseif ($activeRole === 'Staff') $dashboardRoute = 'staff.dashboard';
              elseif ($activeRole === 'Accountant') $dashboardRoute = 'accountant.dashboard';
            @endphp
            <li class="nav-item ms-lg-3">
              <a class="btn btn-saffron" href="{{ route($dashboardRoute) }}">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
              </a>
            </li>
            <li class="nav-item ms-lg-2">
              <a class="btn btn-outline-saffron" href="{{ route('logout') }}">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
              </a>
            </li>
          @else
            <li class="nav-item ms-lg-3">
              <a class="btn btn-saffron" href="{{ route('login') }}">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
              </a>
            </li>
            <li class="nav-item ms-lg-2">
              <a class="btn btn-outline-saffron" href="{{ route('register') }}">
                <i class="bi bi-person-plus me-1"></i> Register
              </a>
            </li>
          @endauth
        </ul>
      </div>
    </div>
  </nav>

  <div class="verify-container">
    <div class="verify-card">
      <h2 class="card-title font-divine">Verify Your Email</h2>
      <p class="card-subtitle">Please enter the 6-digit OTP code sent to your email.</p>

      <div class="email-display">
        <i class="bi bi-envelope-check-fill"></i>
        <span>{{ $email }}</span>
      </div>

      <!-- Ajax Alerts -->
      <div id="ajaxAlert" class="alert d-none border-0 shadow-sm mb-4" style="border-radius: 16px;"></div>

      <!-- Form Error alerts -->
      @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 16px; background-color: #fff2f2;">
          <div class="d-flex align-items-center justify-content-center gap-2 text-danger fw-bold">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span>{{ $errors->first() }}</span>
          </div>
        </div>
      @endif

      <!-- Success alerts -->
      @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 16px; background-color: #f2fdf2;">
          <div class="d-flex align-items-center justify-content-center gap-2 text-success fw-bold">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ session('success') }}</span>
          </div>
        </div>
      @endif

      <form method="POST" action="{{ route('register.verify-otp.post') }}" id="verifyForm">
        @csrf
        
        <input type="text" 
               class="otp-input-box" 
               name="otp" 
               id="otpInput" 
               placeholder="000000" 
               required 
               maxlength="6" 
               pattern="\d{6}" 
               autocomplete="one-time-code"
               autofocus>
        
        <button class="btn btn-verify font-divine py-3" type="submit">
          <i class="bi bi-shield-check me-1"></i> Verify OTP Code
        </button>
      </form>

      <div class="mt-4">
        <button type="button" id="resendBtn" class="btn-resend">
          <i class="bi bi-arrow-clockwise me-1"></i> Resend Verification Code
        </button>
        <div id="cooldownTimer" class="timer-text"></div>
        <div id="expiryTimer" class="timer-text text-danger fw-medium mt-2"></div>
      </div>
    </div>
  </div>

  <!-- Custom Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Server-sent timing values
    @php
      $cooldownExpiresAt = session('resend_cooldown_expires_at');
      $cooldownSeconds = $cooldownExpiresAt ? max(0, $cooldownExpiresAt->diffInSeconds(now())) : 0;

      $otpExpiresAt = session('registration_otp_expires_at');
      $expirySeconds = $otpExpiresAt ? max(0, $otpExpiresAt->diffInSeconds(now())) : 600;
    @endphp

    let cooldownSeconds = {{ $cooldownSeconds }};
    let expirySeconds = {{ $expirySeconds }};

    const resendBtn = document.getElementById('resendBtn');
    const cooldownTimer = document.getElementById('cooldownTimer');
    const expiryTimer = document.getElementById('expiryTimer');
    const ajaxAlert = document.getElementById('ajaxAlert');

    function updateTimers() {
      // Cooldown timer logic
      if (cooldownSeconds > 0) {
        resendBtn.disabled = true;
        cooldownTimer.innerHTML = `Resend available in <strong>${cooldownSeconds}s</strong>`;
        cooldownSeconds--;
      } else {
        resendBtn.disabled = false;
        cooldownTimer.innerHTML = "";
      }

      // Expiry timer logic
      if (expirySeconds > 0) {
        const minutes = Math.floor(expirySeconds / 60);
        const seconds = expirySeconds % 60;
        const formattedTime = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        expiryTimer.innerHTML = `<i class="bi bi-clock-history me-1"></i> Code expires in <strong>${formattedTime}</strong>`;
        expirySeconds--;
      } else {
        expiryTimer.innerHTML = "⚠️ OTP code has expired. Please resend a new code.";
      }
    }

    // Run immediately on page load and start interval
    updateTimers();
    const timerInterval = setInterval(updateTimers, 1000);

    // Ajax Resend implementation
    resendBtn.addEventListener('click', async () => {
      // Disable immediately to prevent spam
      resendBtn.disabled = true;
      cooldownTimer.innerHTML = "Sending...";

      try {
        const response = await fetch('{{ route("register.resend-otp") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          }
        });

        const data = await response.json();

        if (data.success) {
          // Success state
          ajaxAlert.className = "alert alert-success border-0 shadow-sm mb-4";
          ajaxAlert.style.backgroundColor = "#f2fdf2";
          ajaxAlert.innerHTML = `<div class="d-flex align-items-center justify-content-center gap-2 text-success fw-bold"><i class="bi bi-check-circle-fill"></i><span>${data.message}</span></div>`;
          ajaxAlert.classList.remove('d-none');

          // Reset timers to server defaults for new OTP
          cooldownSeconds = 60;
          expirySeconds = 600;
        } else {
          // Error state
          ajaxAlert.className = "alert alert-danger border-0 shadow-sm mb-4";
          ajaxAlert.style.backgroundColor = "#fff2f2";
          ajaxAlert.innerHTML = `<div class="d-flex align-items-center justify-content-center gap-2 text-danger fw-bold"><i class="bi bi-exclamation-circle-fill"></i><span>${data.message}</span></div>`;
          ajaxAlert.classList.remove('d-none');
          
          resendBtn.disabled = false;
          cooldownTimer.innerHTML = "";
        }
      } catch (error) {
        ajaxAlert.className = "alert alert-danger border-0 shadow-sm mb-4";
        ajaxAlert.style.backgroundColor = "#fff2f2";
        ajaxAlert.innerHTML = `<div class="d-flex align-items-center justify-content-center gap-2 text-danger fw-bold"><i class="bi bi-exclamation-circle-fill"></i><span>An error occurred while resending. Please try again.</span></div>`;
        ajaxAlert.classList.remove('d-none');
        
        resendBtn.disabled = false;
        cooldownTimer.innerHTML = "";
      }
    });

    // Auto-focus OTP helper to strip non-numeric & submit when 6 chars
    const otpInput = document.getElementById('otpInput');
    otpInput.addEventListener('input', () => {
      // Strip non-numbers
      otpInput.value = otpInput.value.replace(/\D/g, '');
      if (otpInput.value.length === 6) {
        document.getElementById('verifyForm').submit();
      }
    });
  </script>
</body>
</html>
