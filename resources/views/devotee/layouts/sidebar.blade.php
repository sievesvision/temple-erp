<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="logo-area">
    <div class="logo-icon">🛕</div>
    <div class="logo-text">Temple<span>Connect</span></div>
  </div>

  @php
      $currentMembership = null;
      if (Auth::check() && Auth::user()->role === 'Devotee') {
          $devoteeRecord = \DB::table('devotees')->where('user_id', Auth::user()->id)->first();
          if ($devoteeRecord && $devoteeRecord->membership_id) {
              $currentMembership = \DB::table('memberships')->where('membership_id', $devoteeRecord->membership_id)->first();
          }
      }
  @endphp

  @if($currentMembership)
  <div class="membership-sidebar-card">
      <div class="membership-tier">Your Membership</div>
      <div class="tier-name {{ strtolower($currentMembership->membership_name) }}-text fw-bold">
          @if(strtolower($currentMembership->membership_name) == 'gold')
              <i class="bi bi-trophy-fill" style="color:#b8863a;"></i>
          @elseif(strtolower($currentMembership->membership_name) == 'silver')
              🥈
          @elseif(strtolower($currentMembership->membership_name) == 'platinum')
              💎
          @else
              🥉
          @endif
          {{ $currentMembership->membership_name }}
      </div>
      <div class="membership-benefits">{{ $currentMembership->discount_percentage }}% Off on Poojas</div>
      @if($devoteeRecord && $devoteeRecord->membership_end_date)
          @php
              $diff = strtotime($devoteeRecord->membership_end_date) - time();
              $rem = max(0, intval(ceil($diff / (60 * 60 * 24))));
          @endphp
          <div class="small text-muted mt-1" style="font-size: 0.75rem; border-top: 1px dashed rgba(184, 134, 58, 0.15); padding-top: 4px;">
              Expires: {{ date('d M Y', strtotime($devoteeRecord->membership_end_date)) }}
              <br>({{ $rem }} days left)
          </div>
      @endif
  </div>
  @endif

  <ul class="nav flex-column">
    <li class="nav-item">
      <a href="{{ route('devotee.dashboard') }}" class="nav-link {{ request()->routeIs('devotee.dashboard') && !request()->has('tab') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('devotee.book-pooja') }}" class="nav-link {{ request()->routeIs('devotee.book-pooja') ? 'active' : '' }}">
        <i class="bi bi-calendar-event"></i> Book Pooja
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('devotee.dashboard') }}?tab=bookings" class="nav-link {{ request()->get('tab') === 'bookings' ? 'active' : '' }}">
        <i class="bi bi-clock-history"></i> My Bookings
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('devotee.dashboard') }}?tab=donations" class="nav-link {{ request()->get('tab') === 'donations' ? 'active' : '' }}">
        <i class="bi bi-wallet2"></i> Donations
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('ehundi.show') }}" class="nav-link {{ request()->routeIs('ehundi.show') ? 'active' : '' }}">
        <i class="bi bi-coin"></i> e-Hundi
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('devotee.dashboard') }}?tab=membership" class="nav-link {{ request()->get('tab') === 'membership' ? 'active' : '' }}">
        <i class="bi bi-gem"></i> Membership
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('devotee.dashboard') }}?tab=events" class="nav-link {{ request()->get('tab') === 'events' ? 'active' : '' }}">
        <i class="bi bi-stars"></i> Events
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('devotee.dashboard') }}?tab=profile" class="nav-link {{ request()->get('tab') === 'profile' ? 'active' : '' }}">
        <i class="bi bi-person-circle"></i> Profile
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('devotee.dashboard') }}?tab=notifications" class="nav-link {{ request()->get('tab') === 'notifications' ? 'active' : '' }}">
        <i class="bi bi-bell"></i> Notifications
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link logout-link" id="sidebarLogoutBtn" style="cursor: pointer;">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </li>
  </ul>
</aside>
