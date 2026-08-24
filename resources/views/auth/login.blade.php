<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🛕 Shree Mandir ERP · Devotee & Management Login</title>

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

    /* ----- left panel: majestic look ----- */
    .left-panel {
      width: 50%;
      background: url('https://images.unsplash.com/photo-1544735716-392fe2489ffa?w=800&auto=format&fit=crop&q=80') no-repeat center center;
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

    /* ----- right panel: form look ----- */
    .right-panel {
      width: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 4rem 3rem;
      background: #fdfbf7;
    }

    .login-card {
      width: 100%;
      max-width: 520px;
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

    /* ----- visual role selector list ----- */
    .active-role-container {
      transition: all 0.3s ease;
    }

    .role-list-item {
      border: 1px solid transparent;
      transition: all 0.25s ease;
    }

    .role-list-item:hover {
      background: #fdfbf7;
      border-color: #ebdcc5;
      transform: translateX(4px);
    }

    .role-list-item.active {
      background: rgba(255, 111, 0, 0.05);
      border-color: rgba(255, 111, 0, 0.2);
    }

    .role-list-item.active i.bi {
      color: var(--primary-saffron) !important;
    }

    .role-list-item.active span {
      color: var(--primary-saffron) !important;
      font-weight: 600 !important;
    }

    .animate-fade-in {
      animation: fadeIn 0.3s ease-out forwards;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ----- form controls ----- */
    .form-floating {
      margin-bottom: 1.2rem;
    }

    .form-control:focus {
      border-color: var(--primary-saffron);
      box-shadow: 0 0 0 0.25rem rgba(255, 111, 0, 0.15);
    }

    .btn-login {
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

    .btn-login:hover {
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
      }
    }
    
    /* media query for role-grid removed */
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
        <h1 class="font-divine">Where Devotion <br>Meets Governance</h1>
        <p>Seamlessly access devotee services, book pujas, coordinate trust approvals, manage schedules, and process payments under one synchronized Vedic management system.</p>
      </div>

      <div class="left-footer">
        © 2026 Shree Mandir Trust. Secure ERP Authorization.
      </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
      <div class="login-card">
        
        <!-- back link for mobile -->
        <div class="d-lg-none mb-4">
          <a class="brand-logo font-divine text-dark fs-3" href="{{ route('home') }}">
            <i class="bi bi-temple text-saffron"></i>
            <span>SHREE MANDIR</span>
          </a>
        </div>

        <h2 class="card-title font-divine">Welcome Back</h2>
        <p class="card-subtitle">Choose your access mode and enter credentials below</p>

        <!-- Display general validation errors -->
        @if(isset($errors) && $errors->any())
          <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 16px; background-color: #fff2f2;">
            <div class="d-flex align-items-center gap-2 text-danger fw-bold mb-1">
              <i class="bi bi-exclamation-circle-fill"></i>
              <span>Login Failed:</span>
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
        <form method="POST" action="{{ route('login.post') }}" id="loginForm">
          @csrf
          <input type="hidden" id="role" name="role" value="{{ old('role', 'Devotee') }}">

          <!-- Active Role Banner -->
          <div class="mb-4">
            <label class="form-label fw-bold text-muted small mb-2">ACCESS MODE *</label>
            <div class="active-role-container p-3 rounded-4 d-flex align-items-center justify-content-between" style="background: rgba(255, 111, 0, 0.05); border: 1px solid rgba(255, 111, 0, 0.15);">
              <div class="d-flex align-items-center gap-3">
                <div class="active-role-icon-box d-flex align-items-center justify-content-center rounded-3" style="width: 44px; height: 44px; background: var(--saffron-gradient); color: white; font-size: 1.3rem;">
                  <i id="activeRoleIcon" class="bi bi-person-fill"></i>
                </div>
                <div>
                  <div class="text-muted small fw-medium text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Accessing Portal As</div>
                  <h4 id="activeRoleText" class="font-divine mb-0 text-dark fs-5">Devotee</h4>
                </div>
              </div>
              <button type="button" id="toggleRolesBtn" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1.5" style="font-size: 0.8rem; border-color: #ebdcc5; color: #7a6e63; font-weight: 500;">
                <i class="bi bi-chevron-down me-1"></i> Management Login
              </button>
            </div>

            <!-- Hidden Role List (Collapsible) -->
            <div id="roleSelectorCollapse" class="d-none mb-3 animate-fade-in">
              <div class="p-3 bg-white rounded-4 border" style="border-color: #ebdcc5 !important; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                <div class="text-muted small fw-bold mb-2 text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Select Login Role</div>
                <div class="role-list d-flex flex-column gap-1">
                  <!-- Devotee Option -->
                  <div class="role-list-item d-flex align-items-center justify-content-between p-2 rounded-3" data-role="Devotee" style="cursor: pointer;">
                    <div class="d-flex align-items-center gap-3">
                      <i class="bi bi-person-fill fs-5 text-muted"></i>
                      <span class="fw-medium text-dark" style="font-size: 0.9rem;">Devotee</span>
                    </div>
                    <i class="bi bi-check-circle-fill text-saffron active-check d-none"></i>
                  </div>
                  
                  <!-- Priest Option -->
                  <div class="role-list-item d-flex align-items-center justify-content-between p-2 rounded-3" data-role="Priest" style="cursor: pointer;">
                    <div class="d-flex align-items-center gap-3">
                      <i class="bi bi-mortarboard-fill fs-5 text-muted"></i>
                      <span class="fw-medium text-dark" style="font-size: 0.9rem;">Priest</span>
                    </div>
                    <i class="bi bi-check-circle-fill text-saffron active-check d-none"></i>
                  </div>

                  <!-- Trustee Option -->
                  <div class="role-list-item d-flex align-items-center justify-content-between p-2 rounded-3" data-role="Trustee" style="cursor: pointer;">
                    <div class="d-flex align-items-center gap-3">
                      <i class="bi bi-briefcase-fill fs-5 text-muted"></i>
                      <span class="fw-medium text-dark" style="font-size: 0.9rem;">Trustee</span>
                    </div>
                    <i class="bi bi-check-circle-fill text-saffron active-check d-none"></i>
                  </div>

                  <!-- Staff Option -->
                  <div class="role-list-item d-flex align-items-center justify-content-between p-2 rounded-3" data-role="Staff" style="cursor: pointer;">
                    <div class="d-flex align-items-center gap-3">
                      <i class="bi bi-person-workspace fs-5 text-muted"></i>
                      <span class="fw-medium text-dark" style="font-size: 0.9rem;">Staff</span>
                    </div>
                    <i class="bi bi-check-circle-fill text-saffron active-check d-none"></i>
                  </div>

                  <!-- Accountant Option -->
                  <div class="role-list-item d-flex align-items-center justify-content-between p-2 rounded-3" data-role="Accountant" style="cursor: pointer;">
                    <div class="d-flex align-items-center gap-3">
                      <i class="bi bi-cash-coin fs-5 text-muted"></i>
                      <span class="fw-medium text-dark" style="font-size: 0.9rem;">Accountant</span>
                    </div>
                    <i class="bi bi-check-circle-fill text-saffron active-check d-none"></i>
                  </div>

                  <!-- Admin Option -->
                  <div class="role-list-item d-flex align-items-center justify-content-between p-2 rounded-3" data-role="Admin" style="cursor: pointer;">
                    <div class="d-flex align-items-center gap-3">
                      <i class="bi bi-gear-fill fs-5 text-muted"></i>
                      <span class="fw-medium text-dark" style="font-size: 0.9rem;">Admin</span>
                    </div>
                    <i class="bi bi-check-circle-fill text-saffron active-check d-none"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Email Input -->
          <div class="form-floating">
            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="emailInput" placeholder="name@example.com" required value="{{ old('email') }}">
            <label for="emailInput"><i class="bi bi-envelope me-1 text-muted"></i>Email Address</label>
          </div>

          <!-- Password Input -->
          <div class="form-floating position-relative">
            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="passwordInput" placeholder="Password" required>
            <label for="passwordInput"><i class="bi bi-lock me-1 text-muted"></i>Password</label>
            <button type="button" class="btn position-absolute end-0 top-50 translate-middle-y border-0 me-2" onclick="togglePassword()" style="z-index: 10;" aria-label="Toggle password visibility">
              <i id="eyeIcon" class="bi bi-eye text-muted fs-5"></i>
            </button>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" checked>
              <label class="form-check-label text-muted small" for="rememberMe">
                Remember me
              </label>
            </div>
            <a href="{{ route('forgot-password') }}?restart=1" class="small text-decoration-none" style="color: var(--primary-saffron); font-weight:500;">Forgot Password?</a>
          </div>

          <button class="btn btn-login font-divine py-3" type="submit">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Portal
          </button>
        </form>

        <div class="text-center mt-4 text-muted small">
          Devotee signing in for the first time?
          <a href="{{ route('register') }}" style="color: var(--primary-saffron); font-weight:600; text-decoration:none;">Create Account</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Custom Scripts -->
  <script>
    // --- LocalStorage persistence for selected role ---
    const roleInput = document.getElementById('role');
    const roleListItems = document.querySelectorAll('.role-list-item');
    const toggleRolesBtn = document.getElementById('toggleRolesBtn');
    const roleSelectorCollapse = document.getElementById('roleSelectorCollapse');

    const roleIcons = {
      'Devotee': 'bi-person-fill',
      'Priest': 'bi-mortarboard-fill',
      'Trustee': 'bi-briefcase-fill',
      'Staff': 'bi-person-workspace',
      'Accountant': 'bi-cash-coin',
      'Admin': 'bi-gear-fill'
    };

    function setActiveRole(role) {
      roleInput.value = role;

      // Update text and icon on display card
      const activeRoleText = document.getElementById('activeRoleText');
      const activeRoleIcon = document.getElementById('activeRoleIcon');
      
      if (activeRoleText) activeRoleText.textContent = role;
      if (activeRoleIcon) {
        activeRoleIcon.className = 'bi ' + (roleIcons[role] || 'bi-person-fill');
      }

      // Update items active state
      roleListItems.forEach(item => {
        const checkIcon = item.querySelector('.active-check');
        if (item.dataset.role === role) {
          item.classList.add('active');
          if (checkIcon) checkIcon.classList.remove('d-none');
        } else {
          item.classList.remove('active');
          if (checkIcon) checkIcon.classList.add('d-none');
        }
      });
    }

    // Toggle options list visibility
    if (toggleRolesBtn && roleSelectorCollapse) {
      toggleRolesBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (roleSelectorCollapse.classList.contains('d-none')) {
          roleSelectorCollapse.classList.remove('d-none');
          toggleRolesBtn.innerHTML = '<i class="bi bi-chevron-up me-1"></i> Hide Options';
        } else {
          roleSelectorCollapse.classList.add('d-none');
          toggleRolesBtn.innerHTML = '<i class="bi bi-chevron-down me-1"></i> Management Login';
        }
      });
    }

    // Set initial role on load
    document.addEventListener("DOMContentLoaded", () => {
      // 1. Check old input value (from Laravel verification fail)
      let initialRole = roleInput.value;
      
      // Default fallback (no localStorage fallback)
      if (!initialRole) {
        initialRole = 'Devotee';
      }

      setActiveRole(initialRole);
    });

    // Handle clicks on role list items
    roleListItems.forEach(item => {
      item.addEventListener('click', function() {
        setActiveRole(this.dataset.role);
        // Automatically hide selector when a role is selected
        if (roleSelectorCollapse && toggleRolesBtn) {
          roleSelectorCollapse.classList.add('d-none');
          toggleRolesBtn.innerHTML = '<i class="bi bi-chevron-down me-1"></i> Management Login';
        }
      });
    });

    // Toggle password visibility
    function togglePassword() {
      const password = document.getElementById("passwordInput");
      const eyeIcon = document.getElementById("eyeIcon");

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