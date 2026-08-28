<!-- SIDEBAR -->
@php
    $adminLogoText = $temple['admin_logo_text'] ?? 'SSVK ERP';
    $adminLogoSplitAt = strrpos($adminLogoText, ' ');
    $adminLogoMain = $adminLogoSplitAt !== false ? substr($adminLogoText, 0, $adminLogoSplitAt) : $adminLogoText;
    $adminLogoAccent = $adminLogoSplitAt !== false ? substr($adminLogoText, $adminLogoSplitAt + 1) : '';
    $isFullAdmin = auth()->check() && auth()->user()->role === 'Admin';
    $dashboardRoute = match (auth()->user()->role ?? null) {
        'Admin' => 'admin.dashboard',
        'Committee' => 'committee.dashboard',
        'Accountant' => 'accountant.dashboard',
        default => 'login',
    };
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
      <a href="{{ route($dashboardRoute) }}" class="nav-link {{ (request()->routeIs('admin.dashboard') && !in_array(request()->get('tab'), ['chats', 'prev_chats'])) || request()->routeIs('committee.dashboard') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </li>
    @if($isFullAdmin)
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
      <a href="{{ route('admin.committee.index') }}" class="nav-link {{ request()->routeIs('admin.committee.*') ? 'active' : '' }}"><i class="bi bi-people-fill"></i> Committee</a>
    </li>
    @endif
    @if($isFullAdmin || auth()->user()->role === 'Committee')
    <li class="nav-item">
      <a href="{{ route('admin.bookings.index') }}" class="nav-link {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">
        <i class="bi bi-calendar-event"></i> Pooja Bookings
      </a>
    </li>
    @endif
    <li class="nav-item">
      <a href="{{ route('admin.donations.index') }}" class="nav-link {{ request()->routeIs('admin.donations.*') ? 'active' : '' }}"><i class="bi bi-wallet2"></i> Donations</a>
    </li>
    @if($isFullAdmin || auth()->user()->role === 'Committee')
    <li class="nav-item">
      <a href="{{ route('admin.events.index') }}" class="nav-link {{ request()->routeIs('admin.events.*') ? 'active' : '' }}"><i class="bi bi-stars"></i> Events</a>
    </li>
    @endif
    @if($isFullAdmin)
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
      <a href="{{ route('admin.role-permissions.index') }}" class="nav-link {{ request()->routeIs('admin.role-permissions.*') ? 'active' : '' }}"><i class="bi bi-shield-lock-fill"></i> Role Management</a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.qrlinks.index') }}" class="nav-link {{ request()->routeIs('admin.qrlinks.*') ? 'active' : '' }}"><i class="bi bi-qr-code"></i> QR Links</a>
    </li>
    @endif
    <li class="nav-item">
      <a class="nav-link logout-link" id="sidebarLogoutBtn" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#logoutModal" style="cursor: pointer;">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </li>
  </ul>
</aside>