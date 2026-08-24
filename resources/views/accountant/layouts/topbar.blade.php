<!-- TOPBAR -->
<header class="topbar">
  <div class="d-flex align-items-center gap-3">
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
      <i class="bi bi-list"></i>
    </button>
    <h4><i class="bi bi-grid-1x2-fill"></i> @yield('header-title', 'Accountant Dashboard')</h4>
  </div>
  
  <div class="topbar-actions">
    <span class="badge bg-success text-white me-2" style="font-size:0.85rem; padding: 6px 12px; border-radius: 20px;">
      Finance & Accounts
    </span>

    <div class="dropdown">
      <button class="profile-toggle dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-person-circle"></i>
        <span>{{ Auth::check() ? Auth::user()->name : 'Accountant' }}</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="border-radius: 20px; padding: 8px;">
        <li><a class="dropdown-item" href="{{ route('accountant.dashboard') }}?tab=profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
        <li><a class="dropdown-item" href="{{ route('accountant.dashboard') }}?tab=reports"><i class="bi bi-file-earmark-bar-graph me-2"></i>Financial Reports</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" id="topbarLogoutBtn" style="cursor:pointer;"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</header>
