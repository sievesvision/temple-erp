<!-- TOPBAR -->
<header class="topbar">
  <div class="d-flex align-items-center gap-3">
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
      <i class="bi bi-list"></i>
    </button>
    <h4><i class="bi bi-grid-1x2-fill"></i> @yield('header-title', 'Devotee Dashboard')</h4>
  </div>
  
  <div class="topbar-actions">
    @php
        $currentMembershipName = 'No Membership';
        if (Auth::check()) {
            $devoteeRecord = \DB::table('devotees')->where('user_id', Auth::user()->id)->first();
            if ($devoteeRecord && $devoteeRecord->membership_id) {
                $m = \DB::table('memberships')->where('membership_id', $devoteeRecord->membership_id)->first();
                if ($m) {
                    $currentMembershipName = $m->membership_name;
                }
            }
        }
    @endphp

    @if($currentMembershipName !== 'No Membership')
    <span class="membership-badge {{ strtolower($currentMembershipName) }} animate__animated animate__pulse animate__infinite">
      <i class="bi bi-trophy-fill"></i> {{ $currentMembershipName }}
    </span>
    @endif

    <a href="{{ route('ehundi.show') }}" class="btn btn-warning rounded-pill text-white fw-bold px-3 py-2 d-inline-flex align-items-center gap-1" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border: none; font-size: 0.85rem; box-shadow: 0 4px 10px rgba(184, 134, 58, 0.2);">
      🪔 e-Hundi
    </a>

    <div class="dropdown">
      <button class="profile-toggle dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-person-circle"></i>
        <span>{{ Auth::check() ? Auth::user()->name : 'Devotee' }}</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="border-radius: 20px; padding: 8px;">
        <li><a class="dropdown-item" href="{{ route('devotee.dashboard') }}?tab=profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
        <li><a class="dropdown-item" href="{{ route('devotee.dashboard') }}?tab=membership"><i class="bi bi-gem me-2"></i>Membership</a></li>
        <li><a class="dropdown-item" href="{{ route('devotee.dashboard') }}?tab=bookings"><i class="bi bi-clock-history me-2"></i>My Bookings</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" id="topbarLogoutBtn" style="cursor:pointer;"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</header>
