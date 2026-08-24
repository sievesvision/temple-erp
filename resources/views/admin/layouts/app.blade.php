<!DOCTYPE html>
<html>
<head>
    <title>@yield('title','Temple ERP')</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <style>
        /* ALL YOUR EXISTING CSS HERE */
         * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #f6f1eb;
      color: #1e1e2a;
    }

    /* ---------- SIDEBAR (refined) ---------- */
    .sidebar {
      width: 270px;
      height: 100vh;
      position: fixed;
      left: 0;
      top: 0;
      background: #ffffff;
      backdrop-filter: blur(2px);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
      overflow-y: auto;
      z-index: 1050;
      padding: 0 0 24px 0;
      border-right: 1px solid rgba(184, 134, 58, 0.08);
      transition: transform 0.25s ease;
    }

    .logo-area {
      padding: 24px 20px 20px 24px;
      display: flex;
      align-items: center;
      gap: 10px;
      border-bottom: 1px solid rgba(0,0,0,0.03);
    }
    .logo-icon {
      background: #b8863a;
      width: 40px;
      height: 40px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 22px;
      box-shadow: 0 6px 12px rgba(184, 134, 58, 0.2);
    }
    .logo-text {
      font-weight: 700;
      font-size: 22px;
      letter-spacing: -0.3px;
      color: #2d1f0e;
    }
    .logo-text span {
      color: #b8863a;
    }

    .sidebar .nav {
      padding: 16px 12px 0 12px;
    }
    .sidebar .nav-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 11px 16px;
      border-radius: 14px;
      color: #3e3e4a;
      font-weight: 500;
      font-size: 15px;
      transition: all 0.2s;
      margin-bottom: 2px;
      position: relative;
    }
    .sidebar .nav-link i {
      font-size: 1.25rem;
      width: 24px;
      text-align: center;
      color: #7b6b5a;
      transition: color 0.2s;
    }
    .sidebar .nav-link:hover {
      background: #f3ebe0;
      color: #b8863a;
    }
    .sidebar .nav-link:hover i {
      color: #b8863a;
    }
    .sidebar .nav-link.active {
      background: #b8863a;
      color: white;
      box-shadow: 0 6px 16px rgba(184, 134, 58, 0.25);
    }
    .sidebar .nav-link.active i {
      color: white;
    }
    .sidebar .nav-link.logout-link {
      margin-top: 20px;
      border-top: 1px solid #eeece7;
      border-radius: 0;
      padding-top: 20px;
      color: #b34a4a;
    }
    .sidebar .nav-link.logout-link i {
      color: #b34a4a;
    }
    .sidebar .nav-link.logout-link:hover {
      background: transparent;
      color: #b34a4a;
    }

    /* ---------- MAIN CONTENT ---------- */
    .main-content {
      margin-left: 270px;
      min-height: 100vh;
      transition: margin 0.25s;
    }

    /* top bar */
    .topbar {
      background: rgba(255, 255, 255, 0.7);
      backdrop-filter: blur(8px);
      padding: 16px 32px;
      border-bottom: 1px solid rgba(0,0,0,0.02);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      position: sticky;
      top: 0;
      z-index: 1020;
    }
    .topbar h4 {
      font-weight: 600;
      font-size: 1.5rem;
      letter-spacing: -0.3px;
      color: #2d1f0e;
      margin: 0;
    }
    .topbar h4 i {
      color: #b8863a;
      margin-right: 8px;
    }
    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .btn-notif {
      background: white;
      border: none;
      width: 44px;
      height: 44px;
      border-radius: 40px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.02);
      position: relative;
      transition: 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .btn-notif:hover {
      background: #f3ebe0;
    }
    .badge-dot {
      position: absolute;
      top: 4px;
      right: 4px;
      background: #d13a3a;
      color: white;
      font-size: 11px;
      font-weight: 600;
      width: 22px;
      height: 22px;
      border-radius: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid white;
    }
    .profile-toggle {
      background: white;
      border: none;
      padding: 6px 16px 6px 12px;
      border-radius: 40px;
      font-weight: 500;
      box-shadow: 0 2px 8px rgba(0,0,0,0.02);
      display: flex;
      align-items: center;
      gap: 10px;
      transition: 0.2s;
    }
    .profile-toggle:hover {
      background: #f3ebe0;
    }
    .profile-toggle i {
      font-size: 1.4rem;
      color: #b8863a;
    }

    /* cards */
    .stat-card {
      background: white;
      border-radius: 24px;
      padding: 22px 24px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.02);
      border: 1px solid rgba(184, 134, 58, 0.06);
      transition: transform 0.15s, box-shadow 0.2s;
      height: 100%;
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 32px rgba(184, 134, 58, 0.08);
    }
    .stat-card .stat-label {
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      color: #7b6b5a;
      font-weight: 600;
    }
    .stat-card .stat-number {
      font-size: 2.2rem;
      font-weight: 700;
      color: #1e1e2a;
      letter-spacing: -0.5px;
      margin: 4px 0 0 0;
    }
    .stat-icon {
      width: 52px;
      height: 52px;
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: white;
    }
    .stat-icon.gold {
      background: #b8863a;
    }
    .stat-icon.blue {
      background: #2a6fdb;
    }
    .stat-icon.green {
      background: #1f9d6a;
    }
    .stat-icon.rose {
      background: #c94b6e;
    }
     .stat-icon.red {
      background: #ff0d0d;
    }
  .stat-icon.yellow {
      background: #ffbb00;
    }


    /* quick actions */
    .quick-card {
      background: white;
      border-radius: 24px;
      padding: 24px 28px;
      border: 1px solid rgba(184, 134, 58, 0.06);
      box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .quick-card h5 {
      font-weight: 600;
      color: #2d1f0e;
      margin-bottom: 18px;
    }
    .quick-btn {
      border-radius: 60px;
      padding: 12px 0;
      font-weight: 600;
      font-size: 0.95rem;
      border: none;
      transition: all 0.2s;
      background: #f4efe9;
      color: #2d1f0e;
    }
    .quick-btn:hover {
      background: #b8863a;
      color: white;
      transform: scale(0.98);
    }
    .quick-btn i {
      margin-right: 8px;
    }
    .quick-btn.primary {
      background: #b8863a;
      color: white;
    }
    .quick-btn.primary:hover {
      background: #a07431;
    }

    /* table cards */
    .table-wrap {
      background: white;
      border-radius: 24px;
      border: 1px solid rgba(184, 134, 58, 0.06);
      box-shadow: 0 8px 24px rgba(0,0,0,0.02);
      overflow: hidden;
      height: 100%;
    }
    .table-wrap .card-header {
      background: transparent;
      border-bottom: 1px solid #f0ece6;
      padding: 18px 24px;
      font-weight: 600;
      font-size: 1.05rem;
      color: #2d1f0e;
    }
    .table-wrap .table {
      margin: 0;
    }
    .table-wrap .table th {
      font-weight: 600;
      color: #5a4e3e;
      border-bottom: 1px solid #f0ece6;
      padding: 14px 24px;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }
    .table-wrap .table td {
      padding: 14px 24px;
      border-bottom: 1px solid #f5f0ea;
      color: #1e1e2a;
      font-weight: 500;
    }
    .table-wrap .table tr:last-child td {
      border-bottom: none;
    }
    .badge-amount {
      background: #f3ebe0;
      color: #b8863a;
      padding: 4px 12px;
      border-radius: 40px;
      font-weight: 600;
      font-size: 0.8rem;
    }
    .text-muted-light {
      color: #a0907e;
    }

    /* responsive */
    @media (max-width: 992px) {
      .sidebar {
        transform: translateX(-100%);
        width: 280px;
      }
      .sidebar.show {
        transform: translateX(0);
      }
      .main-content {
        margin-left: 0;
      }
      .topbar {
        padding: 14px 20px;
      }
      .stat-card .stat-number {
        font-size: 1.8rem;
      }
    }
    /* toggle button (mobile) */
    .menu-toggle {
      background: white;
      border: none;
      width: 44px;
      height: 44px;
      border-radius: 40px;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }
    @media (max-width: 992px) {
      .menu-toggle {
        display: flex;
      }
    }

    /* scrollbar */
    .sidebar::-webkit-scrollbar {
      width: 4px;
    }
    .sidebar::-webkit-scrollbar-track {
      background: transparent;
    }
    .sidebar::-webkit-scrollbar-thumb {
      background: #d6cbbc;
      border-radius: 12px;
    }
    </style>

    {{-- Page Specific CSS --}}
    @yield('page-css')

</head>

<body>
    @include('layouts.partials.notifications')

    {{-- Sidebar --}}
    @include('admin.layouts.sidebar')

    <div class="main-content">

        {{-- Topbar --}}
        @include('admin.layouts.topbar')

        <div class="container-fluid p-4">
            @yield('content')
        </div>
</div>
<!-- LOGOUT CONFIRMATION MODAL -->
<div class="modal fade logout-modal" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4" style="background: #fdfbf7; border: 1px solid #b8863a !important;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-danger"><i class="bi bi-box-arrow-right me-2"></i>Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <p class="mb-0" style="font-weight: 500; color: #2d1f0e;">Are you sure you want to logout? You will be redirected to the login page.</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
        <button type="button" class="btn btn-danger rounded-pill px-4 text-white fw-bold" id="confirmLogoutBtn">Logout</button>
      </div>
    </div>
  </div>
</div>




    <!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script>
// common scripts
       $(document).ready(function() {
     // Mobile toggle
    const toggleBtn = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
      toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('show');
      });
      document.addEventListener('click', function(e) {
        if (window.innerWidth <= 992) {
          if (!sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('show');
          }
        }
      });
    }

    // Logout flow
    const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
    const confirmLogoutBtn = document.getElementById('confirmLogoutBtn');

    function showLogoutConfirmation(e) {
      e.preventDefault();
      logoutModal.show();
    }

    const sidebarLogout = document.getElementById('sidebarLogoutBtn');
    if (sidebarLogout) {
      sidebarLogout.addEventListener('click', showLogoutConfirmation);
    }

    const topbarLogout = document.getElementById('topbarLogoutBtn');
    if (topbarLogout) {
      topbarLogout.addEventListener('click', showLogoutConfirmation);
    }

    if (confirmLogoutBtn) {
      confirmLogoutBtn.addEventListener('click', function() {
        window.location.href = '{{ route('logout') }}';
      });
    }
  });
</script>

{{-- Page Specific JS --}}
@yield('page-js')

</body>
</html>