<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🛕 Shree Mandir ERP · Devotee Registration</title>

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

    /* ----- split container ----- */
    .split-container {
      display: flex;
      min-height: calc(100vh - 80px);
      margin-top: 80px;
      width: 100%;
    }

    /* ----- left panel ----- */
    .left-panel {
      width: 50%;
      background: url('https://images.unsplash.com/photo-1609137144814-7d5a570077ec?w=800&auto=format&fit=crop&q=80') no-repeat center center;
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
      background: var(--overlay-gradient);
      z-index: 1;
    }

    .left-panel > * {
      position: relative;
      z-index: 2;
    }

    .brand-logo {
      font-size: 1.8rem;
      font-weight: 900;
      letter-spacing: 0.5px;
      color: #fff;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .brand-logo i {
      color: var(--gold-light);
      filter: drop-shadow(0 2px 8px rgba(255,111,0,0.4));
    }

    .panel-quote {
      max-width: 480px;
      margin: auto 0;
    }

    .panel-quote h1 {
      font-size: 3rem;
      line-height: 1.2;
      margin-bottom: 1.5rem;
      color: var(--gold-light);
      text-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }

    .panel-quote p {
      font-size: 1.1rem;
      color: #ffd8bd;
      line-height: 1.6;
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
      height: calc(100vh - 80px);
    }

    .right-panel::-webkit-scrollbar {
      width: 6px;
    }
    .right-panel::-webkit-scrollbar-track {
      background: #fdfbf7;
    }
    .right-panel::-webkit-scrollbar-thumb {
      background: #ebdcc5;
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

    /* ----- form controls ----- */
    .form-floating {
      margin-bottom: 1.2rem;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-saffron);
      box-shadow: 0 0 0 0.25rem rgba(255, 111, 0, 0.15);
    }

    .btn-register {
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
    }

    .btn-register:hover {
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(255, 111, 0, 0.4);
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

  <div class="split-container">
    <!-- LEFT PANEL -->
    <div class="left-panel">
      <a class="brand-logo font-divine" href="{{ route('home') }}">
        <i class="bi bi-temple"></i>
        <span>SHREE MANDIR ERP</span>
      </a>

      <div class="panel-quote">
        <h1 class="font-divine">Begin Your <br>Vedic Journey</h1>
        <p>Create your devotee profile to seamlessly book authentic Vedic poojas, track and manage your donations, secure temple certificates, and connect directly with trusted priests.</p>
      </div>

      <div class="left-footer">
        © 2026 Shree Mandir Trust. Secure ERP Devotee Portal.
      </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
      <div class="register-card">
        
        <!-- back link for mobile -->
        <div class="d-lg-none mb-4">
          <a class="brand-logo font-divine text-dark fs-3" href="{{ route('home') }}">
            <i class="bi bi-temple text-saffron"></i>
            <span>SHREE MANDIR</span>
          </a>
        </div>

        <h2 class="card-title font-divine">Create Account</h2>
        <p class="card-subtitle">Register as a Devotee to access booking and payment services</p>

        <!-- Display general validation errors -->
        @if(isset($errors) && $errors->any())
          <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 16px; background-color: #fff2f2;">
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
          <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 16px; background-color: #f2fdf2;">
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