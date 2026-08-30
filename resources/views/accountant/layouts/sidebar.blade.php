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
      <a href="{{ route('accountant.dashboard') }}" class="nav-link {{ request()->routeIs('accountant.dashboard') && !request()->has('tab') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('accountant.dashboard') }}?tab=income" class="nav-link {{ request()->get('tab') === 'income' ? 'active' : '' }}">
        <i class="bi bi-graph-up"></i> Income
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('accountant.dashboard') }}?tab=expenses" class="nav-link {{ request()->get('tab') === 'expenses' ? 'active' : '' }}">
        <i class="bi bi-graph-down"></i> Expenses
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('accountant.dashboard') }}?tab=payroll" class="nav-link {{ request()->get('tab') === 'payroll' ? 'active' : '' }}">
        <i class="bi bi-person-workspace"></i> Payroll
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('accountant.dashboard') }}?tab=donations" class="nav-link {{ request()->get('tab') === 'donations' ? 'active' : '' }}">
        <i class="bi bi-wallet2"></i> Donations
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('accountant.dashboard') }}?tab=reports" class="nav-link {{ request()->get('tab') === 'reports' ? 'active' : '' }}">
        <i class="bi bi-file-earmark-bar-graph"></i> Financial Reports
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('accountant.dashboard') }}?tab=salary" class="nav-link {{ request()->get('tab') === 'salary' ? 'active' : '' }}">
        <i class="bi bi-cash-stack"></i> My Salary
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('accountant.dashboard') }}?tab=invoices" class="nav-link {{ request()->get('tab') === 'invoices' ? 'active' : '' }}">
        <i class="bi bi-receipt"></i> Invoices
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('accountant.dashboard') }}?tab=transactions" class="nav-link {{ request()->get('tab') === 'transactions' ? 'active' : '' }}">
        <i class="bi bi-arrow-left-right"></i> Transactions
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link logout-link" id="sidebarLogoutBtn" style="cursor: pointer;">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </li>
  </ul>
</aside>
