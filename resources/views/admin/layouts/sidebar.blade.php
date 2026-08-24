<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="logo-area">
    <div class="logo-icon">🛕</div>
    <div class="logo-text">Temple<span>ERP</span></div>
  </div>

  <ul class="nav flex-column">
    <li class="nav-item">
      <a href="{{ route('admin.dashboard') }}" class="nav-link {{ (request()->routeIs('admin.dashboard') && !in_array(request()->get('tab'), ['chats', 'prev_chats'])) ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.dashboard') }}?tab=chats" class="nav-link {{ (request()->routeIs('admin.dashboard') && request()->get('tab') === 'chats') ? 'active' : '' }}">
        <i class="bi bi-chat-dots-fill"></i> Support Chats
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.dashboard') }}?tab=prev_chats" class="nav-link {{ (request()->routeIs('admin.dashboard') && request()->get('tab') === 'prev_chats') ? 'active' : '' }}">
        <i class="bi bi-clock-history"></i> Previous Chats
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.devotees.index') }}" class="nav-link {{ request()->routeIs('admin.devotees.*') ? 'active' : '' }}">
        <i class="bi bi-people-fill"></i> Devotees
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.priests.index') }}" class="nav-link {{ request()->routeIs('admin.priests.*') ? 'active' : '' }}">
        <i class="bi bi-person-badge"></i> Priests
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.trustees.index') }}" class="nav-link {{ request()->routeIs('admin.trustees.*') ? 'active' : '' }}"><i class="bi bi-person-workspace"></i> Trustees</a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.staff.index') }}" class="nav-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}"><i class="bi bi-person-lines-fill"></i> Staff</a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.leaves.index') }}" class="nav-link {{ request()->routeIs('admin.leaves.*') ? 'active' : '' }}"><i class="bi bi-calendar-x-fill"></i> Leaves</a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.accountants.index') }}" class="nav-link {{ request()->routeIs('admin.accountants.*') ? 'active' : '' }}"><i class="bi bi-cash-stack"></i> Accountants</a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.bookings.index') }}" class="nav-link {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">
        <i class="bi bi-calendar-event"></i> Pooja Bookings
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.donations.index') }}" class="nav-link {{ request()->routeIs('admin.donations.*') ? 'active' : '' }}"><i class="bi bi-wallet2"></i> Donations</a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.events.index') }}" class="nav-link {{ request()->routeIs('admin.events.*') ? 'active' : '' }}"><i class="bi bi-stars"></i> Events</a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.inventory.index') }}" class="nav-link {{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}"><i class="bi bi-box-seam"></i> Inventory</a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.salaries.index') }}" class="nav-link {{ request()->routeIs('admin.salaries.*') ? 'active' : '' }}"><i class="bi bi-cash-stack"></i> Salaries</a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"><i class="bi bi-bar-chart-fill"></i> Reports</a>
    </li>
    <li class="nav-item">
      <a class="nav-link logout-link" id="sidebarLogoutBtn" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#logoutModal" style="cursor: pointer;">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </li>
  </ul>
</aside>