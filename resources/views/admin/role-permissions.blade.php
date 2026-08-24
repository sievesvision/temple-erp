@extends('admin.layouts.app')

@section('title', 'Role Management')

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
    .role-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        overflow-x: auto;
        padding-bottom: 5px;
    }
    .role-tab {
        background: white;
        border: 1px solid rgba(184, 134, 58, 0.15);
        color: #7b6b5a;
        padding: 10px 22px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .role-tab:hover {
        border-color: #b8863a;
        color: #b8863a;
    }
    .role-tab.active {
        background: #b8863a;
        color: white;
        border-color: #b8863a;
        box-shadow: 0 4px 12px rgba(184, 134, 58, 0.2);
    }
    .role-tab.admin-tab {
        opacity: 0.6;
    }
    .grid-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .grid-card .table thead th {
        font-weight: 700;
        color: #5a4e3e;
        border-bottom: 2px solid #f0ece6;
        padding: 14px 16px;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #faf8f5;
        text-align: center;
    }
    .grid-card .table thead th:first-child {
        text-align: left;
    }
    .grid-card .table tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #f5f0ea;
        vertical-align: middle;
        text-align: center;
    }
    .grid-card .table tbody td:first-child {
        text-align: left;
        font-weight: 600;
        color: #2d1f0e;
    }
    .grid-card .form-check-input {
        width: 1.3em;
        height: 1.3em;
        cursor: pointer;
    }
    .grid-card .form-check-input:checked {
        background-color: #b8863a;
        border-color: #b8863a;
    }
    .btn-save {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        border: none;
        padding: 12px 32px;
        border-radius: 40px;
        font-weight: 600;
    }
    .btn-save:hover {
        color: white;
        box-shadow: 0 8px 24px rgba(184, 134, 58, 0.3);
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1><i class="bi bi-shield-lock-fill"></i>Role Management</h1>
    <div class="subtitle">Control which sections each role can view, add to, edit, and delete from</div>
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

<div class="role-tabs">
    @foreach($roles as $role)
        <a href="{{ route('admin.role-permissions.index', ['role' => $role]) }}" class="role-tab {{ $selectedRole === $role ? 'active' : '' }} {{ $role === 'Admin' ? 'admin-tab' : '' }}">
            {{ $role }}
        </a>
    @endforeach
</div>

@if($selectedRole === 'Admin')
    <div class="grid-card p-4">
        <p class="mb-0 text-muted"><i class="bi bi-info-circle me-2"></i>Admin always has full access to every section and cannot be restricted from this screen.</p>
    </div>
@else
    <form method="POST" action="{{ route('admin.role-permissions.update', $selectedRole) }}">
        @csrf
        <div class="grid-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>View</th>
                            <th>Add</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resources as $key => $label)
                            @php $perm = $permissions->get($key); @endphp
                            <tr>
                                <td>{{ $label }}</td>
                                @foreach(['view', 'add', 'edit', 'delete'] as $action)
                                    <td>
                                        <div class="form-check d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox"
                                                   name="grid[{{ $key }}][{{ $action }}]"
                                                   value="1"
                                                   {{ $perm && $perm->{'can_' . $action} ? 'checked' : '' }}>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn-save"><i class="bi bi-check-lg me-1"></i> Save {{ $selectedRole }} Permissions</button>
        </div>
    </form>
@endif
@endsection
