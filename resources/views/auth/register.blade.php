<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $temple['name'] }} · Devotee Registration</title>

  <!-- Bootstrap 5.3 + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Google Fonts: matches the temple's public site (Playfair Display + DM Sans) -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary-saffron: {{ $temple['primary_color'] }};
      --saffron-dark: {{ $temple['dark_color'] }};
      --primary-gold: {{ $temple['accent_color'] }};
      --dark-bg: {{ $temple['dark_color'] }};
      --light-bg: #fbf8f1;
      --border-subtle: #e7ddcd;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'DM Sans', sans-serif;
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
      font-family: 'Playfair Display', serif;
      font-weight: 700;
    }

    /* ----- split container ----- */
    .split-container {
      display: flex;
      flex: 1;
      min-height: 0;
      width: 100%;
    }

    /* ----- left panel ----- */
    .left-panel {
      width: 50%;
      background: url('{{ $temple['hero_image'] }}') no-repeat center center;
      background-size: cover;
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 4rem;
      color: #fff;
    }

    .left-panel::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: {{ $temple['dark_color'] }};
      opacity: 0.82;
      z-index: 1;
    }

    .left-panel > * {
      position: relative;
      z-index: 2;
    }

    .brand-logo {
      font-size: 1.6rem;
      font-weight: 700;
      letter-spacing: 0.2px;
      color: #fff;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .brand-logo i {
      color: var(--primary-gold);
    }

    .left-panel-heading {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      align-self: flex-start;
      font-size: 0.85rem;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: #fff;
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.3);
      padding: 0.55rem 1.1rem;
      border-radius: 999px;
    }

    .left-panel-heading i {
      color: var(--primary-gold);
      font-size: 1rem;
    }

    .panel-quote {
      max-width: 480px;
      margin: auto 0;
    }

    .panel-quote h1 {
      font-size: 2.75rem;
      line-height: 1.25;
      margin-bottom: 1.5rem;
      color: var(--primary-gold);
    }

    .panel-quote p {
      font-size: 1.05rem;
      color: #ffd8bd;
      line-height: 1.6;
    }

    .panel-features {
      margin-top: 2rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .panel-features li {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      font-size: 0.92rem;
      color: #f3ede3;
    }

    .panel-features i {
      width: 36px;
      height: 36px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-radius: 8px;
      color: var(--primary-gold);
      font-size: 1.05rem;
    }

    .left-footer {
      font-size: 0.85rem;
      color: rgba(255, 255, 255, 0.6);
    }

    /* ----- right panel ----- */
    .right-panel {
      width: 50%;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 4rem 3rem;
      background: #fdfbf7;
      overflow-y: auto;
    }

    .right-panel::-webkit-scrollbar {
      width: 6px;
    }
    .right-panel::-webkit-scrollbar-track {
      background: #fdfbf7;
    }
    .right-panel::-webkit-scrollbar-thumb {
      background: var(--border-subtle);
      border-radius: 10px;
    }
    .right-panel::-webkit-scrollbar-thumb:hover {
      background: var(--primary-gold);
    }

    .register-card {
      width: 100%;
      max-width: 600px;
      animation: fadeInUp 0.8s ease-out;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .card-title {
      font-size: 2.2rem;
      color: var(--dark-bg);
      margin-bottom: 0.5rem;
    }

    .card-subtitle {
      color: #7a6e63;
      margin-bottom: 2rem;
      font-size: 1rem;
    }

    /* ----- navbar (matches the public site navbar) ----- */
    .navbar-custom {
      background: rgba(251, 248, 241, 0.97);
      border-bottom: 1px solid var(--border-subtle);
      z-index: 1050;
    }

    .navbar-custom .navbar-brand {
      text-decoration: none;
    }

    .brand-mark {
      width: 48px;
      height: 48px;
      object-fit: contain;
    }

    .brand-title {
      font: 700 1.1rem 'Playfair Display', serif;
      color: var(--dark-bg);
      line-height: 1.15;
      display: block;
    }

    .brand-subtitle {
      color: #716c64;
      font-size: 0.68rem;
      letter-spacing: 0.13em;
      text-transform: uppercase;
      display: block;
    }

    .navbar-custom .nav-link {
      color: #25231f;
      font-weight: 600;
      padding: 0.75rem 0.7rem !important;
      font-size: 0.82rem;
      text-decoration: none;
    }

    .navbar-custom .nav-link:hover,
    .navbar-custom .nav-link.active {
      color: var(--primary-saffron);
    }

    .btn-saffron {
      background: var(--primary-saffron);
      border: 1px solid var(--primary-saffron);
      color: #fff !important;
      font-weight: 700;
      padding: 0.75rem 1.15rem;
      border-radius: 5px;
      transition: background-color 0.2s ease, border-color 0.2s ease;
      text-decoration: none;
    }

    .btn-saffron:hover {
      background: var(--saffron-dark);
      border-color: var(--saffron-dark);
    }

    .btn-outline-saffron {
      border: 1px solid var(--primary-saffron);
      color: var(--primary-saffron) !important;
      background: transparent;
      font-weight: 700;
      padding: 0.75rem 1.15rem;
      border-radius: 5px;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .btn-outline-saffron:hover {
      background: var(--primary-saffron);
      color: #fff !important;
    }

    /* ----- form controls ----- */
    .form-floating {
      margin-bottom: 1.2rem;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-saffron);
      box-shadow: 0 0 0 1px var(--primary-saffron);
    }

    .btn-register {
      background: var(--primary-saffron);
      border: 1px solid var(--primary-saffron);
      color: #fff;
      font-weight: 600;
      padding: 0.9rem;
      border-radius: 10px;
      font-size: 1.05rem;
      transition: background-color 0.2s ease, border-color 0.2s ease;
      width: 100%;
    }

    .btn-register:hover {
      color: #fff;
      background: var(--saffron-dark);
      border-color: var(--saffron-dark);
    }

    /* ----- responsiveness ----- */
    @media (max-width: 991px) {
      .left-panel {
        display: none;
      }
      .right-panel {
        width: 100%;
        padding: 3rem 1.5rem;
        height: auto;
      }
      .split-container {
        min-height: auto;
      }
    }
  </style>
</head>
<body>
  @include('layouts.partials.notifications')

  <!--  NAVBAR (matches the public site navbar)               -->
  <nav class="navbar navbar-expand-lg navbar-custom py-2 sticky-top" id="mainNavbar">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
        @if($temple['logo'])
          <img class="brand-mark" src="{{ $temple['logo'] }}" alt="{{ $temple['name'] }} logo">
        @else
          <span class="brand-mark d-flex align-items-center justify-content-center" style="font-size:2.1rem;line-height:1;color:var(--primary-saffron);">ॐ</span>
        @endif
        <span>
          <span class="brand-title">{{ $temple['brand_title'] ?: $temple['name'] }}</span>
          <span class="brand-subtitle">{{ $temple['subtitle'] }}</span>
        </span>
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
              elseif ($activeRole === 'Committee') $dashboardRoute = 'committee.dashboard';
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

  <div class="split-container">
    <!-- LEFT PANEL -->
    <div class="left-panel">
      <div class="left-panel-heading">
        <i class="bi bi-person-vcard-fill"></i>
        <span>Register</span>
      </div>

      <div class="panel-quote">
        <h1 class="font-divine">Begin Your <br>Devotional Journey</h1>
        <p>Create your devotee profile in a few steps.</p>

        <ul class="panel-features list-unstyled">
          <li><i class="bi bi-calendar2-check"></i><span>Book poojas online anytime</span></li>
          <li><i class="bi bi-wallet2"></i><span>Make and track your donations</span></li>
          <li><i class="bi bi-bell"></i><span>Get updates on events &amp; festivals</span></li>
        </ul>
      </div>

      <div class="left-footer">
        © {{ date('Y') }} {{ $temple['name'] }}. Secure devotee portal.
      </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
      <div class="register-card">

        <!-- back link for mobile -->
        <div class="d-lg-none mb-4">
          <a class="brand-logo font-divine text-dark fs-3" href="{{ route('home') }}">
            @if($temple['logo'])
              <img src="{{ $temple['logo'] }}" alt="{{ $temple['name'] }} logo" style="width:32px;height:32px;object-fit:contain;">
            @else
              <i class="bi bi-temple text-saffron"></i>
            @endif
            <span>{{ $temple['brand_title'] ?: $temple['name'] }}</span>
          </a>
        </div>

        <h2 class="card-title font-divine">Create Account</h2>
        <p class="card-subtitle">Register as a Devotee to access booking and payment services</p>

        <!-- Display general validation errors -->
        @if(isset($errors) && $errors->any())
          <div class="alert alert-danger mb-4" style="border-radius: 10px; background-color: #fff2f2; border: 1px solid #f3c6c6;">
            <div class="d-flex align-items-center gap-2 text-danger fw-bold mb-1">
              <i class="bi bi-exclamation-circle-fill"></i>
              <span>Registration Failed:</span>
            </div>
            <ul class="mb-0 text-danger small ps-4">
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <!-- Success Message -->
        @if(session('success'))
          <div class="alert alert-success mb-4" style="border-radius: 10px; background-color: #f2fdf2; border: 1px solid #c6e8c6;">
            <div class="d-flex align-items-center gap-2 text-success fw-bold">
              <i class="bi bi-check-circle-fill"></i>
              <span>{{ session('success') }}</span>
            </div>
          </div>
        @endif

        <!-- Form -->
        <form method="POST" action="{{ route('register.post') }}" id="registerForm">
          @csrf

          <!-- Full Name -->
          <div class="form-floating">
            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="nameInput" placeholder="Devdutt Pattanaik" required value="{{ old('name') }}">
            <label for="nameInput"><i class="bi bi-person me-1 text-muted"></i>Full Name</label>
          </div>

          <!-- Email & Mobile in Row -->
          <div class="row g-2">
            <div class="col-md-6">
              <div class="form-floating">
                <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="emailInput" placeholder="name@example.com" required value="{{ old('email') }}">
                <label for="emailInput"><i class="bi bi-envelope me-1 text-muted"></i>Email Address</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control @error('mobile') is-invalid @enderror" name="mobile" id="mobileInput" placeholder="9876543210" required maxlength="10" value="{{ old('mobile') }}">
                <label for="mobileInput"><i class="bi bi-telephone me-1 text-muted"></i>Mobile Number</label>
              </div>
            </div>
          </div>

          <!-- Gender & DOB in Row -->
          <div class="row g-2">
            <div class="col-md-6">
              <div class="form-floating">
                <select name="gender" id="genderInput" class="form-select @error('gender') is-invalid @enderror" required>
                  <option value="" disabled {{ old('gender') == '' ? 'selected' : '' }}>Select Gender</option>
                  <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                  <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                  <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>Other</option>
                </select>
                <label for="genderInput"><i class="bi bi-gender-ambiguous me-1 text-muted"></i>Gender</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="date" class="form-control @error('dob') is-invalid @enderror" name="dob" id="dobInput" required value="{{ old('dob') }}">
                <label for="dobInput"><i class="bi bi-calendar-event me-1 text-muted"></i>Date of Birth</label>
              </div>
            </div>
          </div>

          <!-- Gothra & Nakshatra in Row -->
          <div class="row g-2">
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" name="gothra" id="gothraInput" placeholder="Kashyapa" value="{{ old('gothra') }}">
                <label for="gothraInput"><i class="bi bi-star me-1 text-muted"></i>Gothra (Optional)</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" name="nakshatra" id="nakshatraInput" placeholder="Rohini" value="{{ old('nakshatra') }}">
                <label for="nakshatraInput"><i class="bi bi-brightness-high me-1 text-muted"></i>Nakshatra (Optional)</label>
              </div>
            </div>
          </div>

          <!-- Address -->
          <div class="form-floating">
            <textarea class="form-control @error('address') is-invalid @enderror" name="address" id="addressInput" placeholder="Enter full address" style="height: 100px;">{{ old('address') }}</textarea>
            <label for="addressInput"><i class="bi bi-geo-alt me-1 text-muted"></i>Resident Address</label>
          </div>

          <!-- Passwords in Row -->
          <div class="row g-2">
            <div class="col-md-6">
              <div class="form-floating position-relative">
                <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="passwordInput" placeholder="Password" required>
                <label for="passwordInput"><i class="bi bi-lock me-1 text-muted"></i>Password</label>
                <button type="button" class="btn position-absolute end-0 top-50 translate-middle-y border-0 me-2" onclick="togglePassword('passwordInput', 'eyeIcon1')" style="z-index: 10;" aria-label="Toggle password visibility">
                  <i id="eyeIcon1" class="bi bi-eye text-muted fs-5"></i>
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating position-relative">
                <input type="password" class="form-control" name="password_confirmation" id="confirmPasswordInput" placeholder="Confirm Password" required>
                <label for="confirmPasswordInput"><i class="bi bi-lock-fill me-1 text-muted"></i>Confirm Password</label>
                <button type="button" class="btn position-absolute end-0 top-50 translate-middle-y border-0 me-2" onclick="togglePassword('confirmPasswordInput', 'eyeIcon2')" style="z-index: 10;" aria-label="Toggle password visibility">
                  <i id="eyeIcon2" class="bi bi-eye text-muted fs-5"></i>
                </button>
              </div>
            </div>
          </div>

          <button class="btn btn-register font-divine py-3 mt-3" type="submit">
            <i class="bi bi-person-plus me-1"></i> Register Account
          </button>
        </form>

        <div class="text-center mt-4 text-muted small">
          Already registered?
          <a href="{{ route('login') }}" style="color: var(--primary-saffron); font-weight:600; text-decoration:none;">Sign In here</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Custom Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Toggle password visibility
    function togglePassword(inputId, iconId) {
      const password = document.getElementById(inputId);
      const eyeIcon = document.getElementById(iconId);

      if (password.type === "password") {
        password.type = "text";
        eyeIcon.classList.remove("bi-eye");
        eyeIcon.classList.add("bi-eye-slash");
      } else {
        password.type = "password";
        eyeIcon.classList.remove("bi-eye-slash");
        eyeIcon.classList.add("bi-eye");
      }
    }
  </script>
</body>
</html>
