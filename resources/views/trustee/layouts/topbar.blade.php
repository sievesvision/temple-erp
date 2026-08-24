<!-- TOPBAR -->
<header class="topbar">
  <div class="d-flex align-items-center gap-3">
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
      <i class="bi bi-list"></i>
    </button>
    <h4><i class="bi bi-grid-1x2-fill"></i> @yield('header-title', 'Trustee Dashboard')</h4>
  </div>
  
  <div class="topbar-actions">
    <span class="badge bg-warning text-dark me-2" style="font-size:0.85rem; padding: 6px 12px; border-radius: 20px;">
      Governance
    </span>

    <div class="dropdown">
      <button class="profile-toggle dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-person-circle"></i>
        <span>{{ Auth::check() ? Auth::user()->name : 'Trustee' }}</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="border-radius: 20px; padding: 8px;">
        <li><a class="dropdown-item" href="{{ route('trustee.dashboard') }}?tab=profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
        <li><a class="dropdown-item" href="{{ route('trustee.dashboard') }}?tab=approvals"><i class="bi bi-check2-square me-2"></i>Approvals</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" id="topbarLogoutBtn" style="cursor:pointer;"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</header>
