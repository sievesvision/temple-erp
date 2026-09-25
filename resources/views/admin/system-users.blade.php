@extends('admin.layouts.app')

@section('title', 'System Users')

@section('page-css')
<style>
    .page-header {
        background: white;
        border-radius: 24px;
        padding: 24px 32px;
        margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .page-header h1 {
        font-weight: 700;
        font-size: 1.8rem;
        color: #2d1f0e;
        margin: 0;
    }
    .page-header h1 i {
        color: #b8863a;
        margin-right: 12px;
    }
    .page-header .subtitle {
        color: #7b6b5a;
        font-size: 0.95rem;
        margin-top: 4px;
    }
    .table-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .table-card .card-header {
        background: transparent;
        border-bottom: 1px solid #f0ece6;
        padding: 18px 24px;
        font-weight: 600;
        font-size: 1.05rem;
        color: #2d1f0e;
    }
    .table-card .table thead th {
        font-weight: 600;
        color: #5a4e3e;
        border-bottom: 2px solid #f0ece6;
        padding: 14px 16px;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #faf8f5;
    }
    .table-card .table tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #f5f0ea;
        color: #1e1e2a;
        font-weight: 500;
        vertical-align: middle;
    }
    .table-card .table tbody tr:hover {
        background: #faf8f5;
    }
    .btn-action-reset {
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action-reset:hover {
        background: #8b5cf6;
        color: white;
    }
    .btn-action-2fa {
        background: rgba(184, 134, 58, 0.1);
        color: #b8863a;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action-2fa:hover {
        background: #b8863a;
        color: white;
    }
    .btn-action-unlock {
        background: rgba(220, 38, 38, 0.1);
        color: #dc2626;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action-unlock:hover {
        background: #dc2626;
        color: white;
    }
    .username-form {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .username-form input {
        width: 84px;
        font-size: 0.8rem;
        padding: 4px 8px;
        border-radius: 20px;
        border: 1px solid #e5decf;
    }
    .username-form button {
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
        border: none;
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 0.72rem;
        font-weight: 600;
    }
    .username-form button:hover {
        background: #8b5cf6;
        color: white;
    }
    .filter-bar {
        background: white;
        border-radius: 20px;
        padding: 16px 20px;
        margin-bottom: 20px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1><i class="bi bi-person-vcard-fill"></i>System Users</h1>
    <div class="subtitle">Every account in the system, their role, and login/password history.</div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert" style="background: #d1fae5; color: #065f46;">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert" style="background: #fee2e2; color: #991b1b;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="filter-bar">
    <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-center">
        <div class="col-md-3">
            <select name="role" class="form-select rounded-3" onchange="this.form.submit()">
                <option value="">All Roles</option>
                @foreach($roles as $r)
                    <option value="{{ $r }}" {{ $roleFilter === $r ? 'selected' : '' }}>{{ $r }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-5">
            <input type="text" name="search" class="form-control rounded-3" placeholder="Search by name or email..." value="{{ $search }}">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Filter</button>
        </div>
        @if($roleFilter || $search)
        <div class="col-md-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Clear</a>
        </div>
        @endif
    </form>
</div>

<div class="table-card">
    <div class="card-header">
        <span>All System Users</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Kiosk Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Last Password Change</th>
                    <th>2FA</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                <tr>
                    <td><strong>{{ $u->name }}</strong></td>
                    <td>{{ $u->email }}</td>
                    <td>
                        <form action="{{ route('admin.users.set-username', $u->id) }}" method="POST" class="username-form">
                            @csrf
                            <input type="text" name="username" value="{{ $u->username }}" maxlength="6" placeholder="none" title="4-6 characters, letters/numbers only">
                            <button type="submit">Save</button>
                        </form>
                        @if($u->kiosk_pin_locked_at)
                            <form action="{{ route('admin.kiosk-pin.reset-lockout', $u->id) }}" method="POST" class="d-inline mt-1">
                                @csrf
                                <button type="submit" class="btn-action-unlock">
                                    <i class="bi bi-shield-lock-fill"></i> Unlock PIN
                                </button>
                            </form>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">{{ $u->role }}</span>
                        @foreach(array_diff($u->grantedRoles(), [$u->role, 'Devotee']) as $extraRole)
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-2 py-1 rounded-pill" style="font-size:0.7rem;">+ {{ $extraRole }}</span>
                        @endforeach
                    </td>
                    <td>
                        @if($u->status === 'Active')
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>{{ $u->last_login_at ? $u->last_login_at->format('d M Y, h:i A') : 'Never' }}</td>
                    <td>{{ $u->password_changed_at ? $u->password_changed_at->format('d M Y, h:i A') : 'Never' }}</td>
                    <td>
                        @if($u->two_factor_enabled)
                            <span class="badge bg-success">Enabled</span>
                        @else
                            <span class="badge bg-secondary">Disabled</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <form action="{{ route('admin.users.send-reset-link', $u->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Send a password reset link to {{ $u->email }}?')">
                            @csrf
                            <button type="submit" class="btn-action-reset">
                                <i class="bi bi-envelope-arrow-up"></i> Send Reset Link
                            </button>
                        </form>
                        <form action="{{ route('admin.users.toggle-2fa', $u->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ $u->two_factor_enabled ? 'Disable' : 'Enable' }} two-factor authentication for {{ $u->email }}?')">
                            @csrf
                            <button type="submit" class="btn-action-2fa">
                                <i class="bi bi-shield-lock"></i> {{ $u->two_factor_enabled ? 'Disable 2FA' : 'Enable 2FA' }}
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-person-vcard fs-1 d-block mb-2 text-warning"></i>
                        No users found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="p-3 border-top">
        {{ $users->links() }}
    </div>
    @endif
</div>
@endsection
