<!-- TOPBAR -->
<header class="topbar">
  <div class="d-flex align-items-center gap-3">
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
      <i class="bi bi-list"></i>
    </button>
    <h4><i class="bi bi-grid-1x2-fill"></i> @yield('header-title', 'Priest Dashboard')</h4>
  </div>
  
  <div class="topbar-actions">
    @php
        $currentStatus = 'Offline';
        $switchEnabled = false;
        if (Auth::check()) {
            $user = Auth::user();
            $today = date('Y-m-d');
            $priestRecord = \DB::table('priests')->where('user_id', $user->id)->first();
            if ($priestRecord) {
                $currentStatus = $priestRecord->current_status;
            }
            
            $att = \DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('date', $today)
                ->whereNotNull('check_in_time')
                ->first();
            
            if ($att && !$att->check_out_time) {
                $switchEnabled = true;
            }
        }
    @endphp

    <div class="form-check form-switch me-3 d-flex align-items-center">
      <input class="form-check-input status-toggle-switch" type="checkbox" role="switch" id="topbarStatusSwitch" {{ $currentStatus === 'Online' ? 'checked' : '' }} {{ $switchEnabled ? '' : 'disabled' }} style="width: 2.5em; height: 1.25em; cursor: pointer;">
      <label class="form-check-label ms-2 fw-semibold" for="topbarStatusSwitch" id="topbarStatusLabel" style="font-size: 0.9rem; color: #2d1f0e; cursor: pointer;">
        {{ $currentStatus === 'Online' ? 'Online' : 'Offline' }}
      </label>
    </div>

    <div class="dropdown">
      <button class="profile-toggle dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-person-circle"></i>
        <span>{{ Auth::check() ? Auth::user()->name : 'Priest' }}</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="border-radius: 20px; padding: 8px;">
        <li><a class="dropdown-item" href="{{ route('priest.dashboard') }}?tab=profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
        <li><a class="dropdown-item" href="{{ route('priest.dashboard') }}?tab=poojas"><i class="bi bi-calendar-check me-2"></i>My Poojas</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" id="topbarLogoutBtn" style="cursor:pointer;"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</header>
