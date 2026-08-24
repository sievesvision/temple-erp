<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="logo-area">
    <div class="logo-icon">🛕</div>
    <div class="logo-text">Trustee<span>ERP</span></div>
  </div>

  <ul class="nav flex-column">
    <li class="nav-item">
      <a href="{{ route('trustee.dashboard') }}" class="nav-link {{ request()->routeIs('trustee.dashboard') && !request()->has('tab') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('trustee.dashboard') }}?tab=performance" class="nav-link {{ request()->get('tab') === 'performance' ? 'active' : '' }}">
        <i class="bi bi-graph-up-arrow"></i> Temple Performance
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('trustee.dashboard') }}?tab=revenue" class="nav-link {{ request()->get('tab') === 'revenue' ? 'active' : '' }}">
        <i class="bi bi-coin"></i> Revenue Overview
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('trustee.dashboard') }}?tab=reports" class="nav-link {{ request()->get('tab') === 'reports' ? 'active' : '' }}">
        <i class="bi bi-file-earmark-bar-graph"></i> Reports
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('trustee.dashboard') }}?tab=events" class="nav-link {{ request()->get('tab') === 'events' ? 'active' : '' }}">
        <i class="bi bi-stars"></i> Events
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('trustee.dashboard') }}?tab=today-poojas" class="nav-link {{ request()->get('tab') === 'today-poojas' ? 'active' : '' }}">
        <i class="bi bi-calendar-day"></i> Today's Poojas
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('trustee.dashboard') }}?tab=upcoming-poojas" class="nav-link {{ request()->get('tab') === 'upcoming-poojas' ? 'active' : '' }}">
        <i class="bi bi-calendar-week"></i> Upcoming Poojas
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('trustee.dashboard') }}?tab=pooja-calendar" class="nav-link {{ request()->get('tab') === 'pooja-calendar' ? 'active' : '' }}">
        <i class="bi bi-calendar3"></i> Pooja Calendar
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('trustee.dashboard') }}?tab=meetings" class="nav-link {{ request()->get('tab') === 'meetings' ? 'active' : '' }}">
        <i class="bi bi-people-fill"></i> Meetings
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link logout-link" id="sidebarLogoutBtn" style="cursor: pointer;">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </li>
  </ul>
</aside>
