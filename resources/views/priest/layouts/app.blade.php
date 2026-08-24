<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Priest Portal')</title>
  
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Google Font (Inter) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #f8f4f0;
      color: #1e1e2a;
    }

    /* ---------- SIDEBAR ---------- */
    .sidebar {
      width: 260px;
      height: 100vh;
      position: fixed;
      left: 0;
      top: 0;
      background: #ffffff;
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
      background: linear-gradient(135deg, #b8863a, #d4a05a);
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
      background: linear-gradient(135deg, #b8863a, #d4a05a);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
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
      background: linear-gradient(135deg, #b8863a, #d4a05a);
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
      margin-left: 260px;
      min-height: 100vh;
      transition: margin 0.25s;
    }

    /* top bar */
    .topbar {
      background: rgba(255, 255, 255, 0.8);
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
    }

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
  </style>
  
  @yield('page-css')
</head>
<body>
  @include('layouts.partials.notifications')

  {{-- Priest Sidebar --}}
  @include('priest.layouts.sidebar')

  <div class="main-content">
    {{-- Priest Topbar --}}
    @include('priest.layouts.topbar')

    <div class="container-fluid px-4 py-4">
      @yield('content')
    </div>
  </div>

  <!-- LOGOUT CONFIRMATION MODAL -->
  <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius:24px; border:none; box-shadow:0 24px 48px rgba(0,0,0,0.08);">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-right me-2 text-danger"></i>Confirm Logout</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body pt-3">
          <p class="mb-0" style="font-weight: 450; color: #2d1f0e;">Are you sure you want to logout?</p>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
          <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmLogoutBtn" style="background:#b34a4a; border:none;">Logout</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <script>
    $(document).ready(function() {
      // Mobile sidebar toggle
      $('#menuToggle').on('click', function(e) {
        e.stopPropagation();
        $('#sidebar').toggleClass('show');
      });

      $(document).on('click', function(e) {
        if ($(window).width() <= 992) {
          if (!$('#sidebar').is(e.target) && $('#sidebar').has(e.target).length === 0 && !$('#menuToggle').is(e.target) && $('#menuToggle').has(e.target).length === 0) {
            $('#sidebar').removeClass('show');
          }
        }
      });

      // Logout modal behavior
      const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
      
      $('#sidebarLogoutBtn, #topbarLogoutBtn').on('click', function(e) {
        e.preventDefault();
        logoutModal.show();
      });

      $('#confirmLogoutBtn').on('click', function() {
        window.location.href = '{{ route('logout') }}';
      });

      // Status toggle switch via AJAX
      $('.status-toggle-switch').on('change', function() {
        let self = $(this);
        let isChecked = self.is(':checked');
        let statusLabel = $('#topbarStatusLabel');
        
        $.ajax({
          url: '{{ route("priest.attendance.toggle") }}',
          type: 'POST',
          data: {
            _token: '{{ csrf_token() }}'
          },
          success: function(response) {
            if (response.success) {
              $('.status-toggle-switch').prop('checked', response.status === 'Online');
              statusLabel.text(response.status);
              window.location.reload();
            } else {
              alert('Failed to update status: ' + (response.message || ''));
              self.prop('checked', !isChecked);
            }
          },
          error: function(xhr) {
            let msg = 'An error occurred while updating status.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
              msg = xhr.responseJSON.message;
            }
            alert(msg);
            self.prop('checked', !isChecked);
          }
        });
      });
    });
  </script>

  @yield('page-js')
</body>
</html>
