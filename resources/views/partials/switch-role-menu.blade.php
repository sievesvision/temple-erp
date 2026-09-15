@php
    $switchRoleOptions = array_diff(auth()->user()->grantedRoles(), [session('active_role', auth()->user()->role)]);
    $roleIconMap = [
        'Devotee' => 'bi-person-fill',
        'Priest' => 'bi-mortarboard-fill',
        'Trustee' => 'bi-briefcase-fill',
        'Staff' => 'bi-person-workspace',
        'Accountant' => 'bi-cash-coin',
        'Committee' => 'bi-people-fill',
        'Event Coordinator' => 'bi-calendar-check-fill',
        'Admin' => 'bi-gear-fill',
    ];
@endphp
@if(count($switchRoleOptions))
<li><h6 class="dropdown-header">Switch Role</h6></li>
@foreach($switchRoleOptions as $switchRole)
<li>
    <form action="{{ route('switch-role') }}" method="POST">
        @csrf
        <input type="hidden" name="role" value="{{ $switchRole }}">
        <button type="submit" class="dropdown-item d-flex align-items-center" style="background:none; border:none; width:100%; text-align:left;">
            <i class="bi {{ $roleIconMap[$switchRole] ?? 'bi-arrow-left-right' }} me-2"></i>{{ $switchRole }}
        </button>
    </form>
</li>
@endforeach
<li><hr class="dropdown-divider"></li>
@endif
