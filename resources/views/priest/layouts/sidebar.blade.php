<!-- SIDEBAR -->
@php
    $adminLogoText = $temple['admin_logo_text'] ?? 'SSVK ERP';
    $adminLogoSplitAt = strrpos($adminLogoText, ' ');
    $adminLogoMain = $adminLogoSplitAt !== false ? substr($adminLogoText, 0, $adminLogoSplitAt) : $adminLogoText;
    $adminLogoAccent = $adminLogoSplitAt !== false ? substr($adminLogoText, $adminLogoSplitAt + 1) : '';
@endphp
<aside class="sidebar" id="sidebar">
  <div class="logo-area">
    <div class="logo-icon">
        @if(!empty($temple['admin_logo_icon']))
            <img src="{{ $temple['admin_logo_icon'] }}" alt="{{ $adminLogoText }} logo" style="width:44px;height:44px;object-fit:contain;">
        @else
            🛕
        @endif
    </div>
    <div class="logo-text">{{ $adminLogoMain }}@if($adminLogoAccent) <span>{{ $adminLogoAccent }}</span>@endif</div>
  </div>

  <ul class="nav flex-column">
    <li class="nav-item">
      <a href="{{ route('priest.dashboard') }}" class="nav-link {{ request()->routeIs('priest.dashboard') && !request()->has('tab') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('priest.dashboard') }}?tab=poojas" class="nav-link {{ request()->get('tab') === 'poojas' ? 'active' : '' }}">
        <i class="bi bi-calendar-check"></i> Assigned Poojas
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('priest.dashboard') }}?tab=schedule" class="nav-link {{ request()->get('tab') === 'schedule' ? 'active' : '' }}">
        <i class="bi bi-calendar3"></i> Schedule Calendar
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('priest.dashboard') }}?tab=devotees" class="nav-link {{ request()->get('tab') === 'devotees' ? 'active' : '' }}">
        <i class="bi bi-people"></i> Devotees
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('priest.dashboard') }}?tab=attendance" class="nav-link {{ request()->get('tab') === 'attendance' ? 'active' : '' }}">
        <i class="bi bi-person-check"></i> Attendance
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('priest.dashboard') }}?tab=leaves" class="nav-link {{ request()->get('tab') === 'leaves' ? 'active' : '' }}">
        <i class="bi bi-calendar-x"></i> Leave Requests
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('priest.dashboard') }}?tab=wallet" class="nav-link {{ request()->get('tab') === 'wallet' ? 'active' : '' }}">
        <i class="bi bi-wallet2"></i> My Wallet
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('priest.dashboard') }}?tab=salary" class="nav-link {{ request()->get('tab') === 'salary' ? 'active' : '' }}">
        <i class="bi bi-cash-stack"></i> My Salary
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('priest.dashboard') }}?tab=profile" class="nav-link {{ request()->get('tab') === 'profile' ? 'active' : '' }}">
        <i class="bi bi-person-circle"></i> Profile
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('priest.dashboard') }}?tab=notifications" class="nav-link {{ request()->get('tab') === 'notifications' ? 'active' : '' }}">
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
