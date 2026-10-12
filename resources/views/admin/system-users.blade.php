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
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="bi bi-person-vcard-fill"></i>System Users</h1>
        <div class="subtitle">Every account in the system, their role, event/ticket access, and login/password history.</div>
    </div>
    <button type="button" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-person-plus-fill me-1"></i>Create User
    </button>
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

{{-- Testing Mode only (real emails aren't actually sent there) — the same "here's the
     link directly, since there's no inbox to check" fallback used elsewhere. --}}
@if(session('pos_pin_setup_link'))
    <div class="alert alert-info alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert">
        PIN setup link for <strong>{{ session('pos_pin_setup_link.name') }}</strong>:
        <a href="{{ session('pos_pin_setup_link.url') }}" target="_blank">{{ session('pos_pin_setup_link.url') }}</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Testing Mode only — same reasoning as the PIN-link alert above. --}}
@if(session('success_user_created'))
    <div class="alert alert-info alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert">
        Password setup link for <strong>{{ session('success_user_created.name') }}</strong> ({{ session('success_user_created.role') }}):
        <a href="{{ session('success_user_created.setup_url') }}" target="_blank">Click to set password</a>
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
                    <th>POS Username</th>
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
                            <input type="text" name="username" value="{{ $u->username }}" maxlength="10" placeholder="none" title="6-10 characters, letters/numbers only">
                            <button type="submit">Save</button>
                        </form>
                        @if($u->pos_pin_locked_at)
                            <form action="{{ route('admin.pos-pin.reset-lockout', $u->id) }}" method="POST" class="d-inline mt-1">
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
                        <form action="{{ route('admin.users.toggle-status', $u->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ $u->status === 'Active' ? 'Deactivate' : 'Activate' }} {{ $u->email }}? {{ $u->status === 'Active' ? 'They will no longer be able to log in.' : '' }}')">
                            @csrf
                            <button type="submit" class="badge border-0 {{ $u->status === 'Active' ? 'bg-success' : 'bg-secondary' }}" style="cursor:pointer;">
                                {{ $u->status === 'Active' ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
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
                        <button type="button" class="btn-action-reset" onclick="openEditModal({{ $u->id }}, {{ json_encode($u->name) }}, {{ json_encode($u->email) }}, {{ json_encode($u->mobile) }}, {{ json_encode($u->status) }})">
                            <i class="bi bi-pencil-fill"></i> Edit
                        </button>
                        <button type="button" class="btn-action-reset" onclick="openAccessModal({{ $u->id }}, {{ json_encode($u->name) }})">
                            <i class="bi bi-calendar-check"></i> Event &amp; Ticket Access
                        </button>
                        <a href="{{ route('admin.logs.index', ['search' => $u->name]) }}" class="btn-action-reset">
                            <i class="bi bi-clock-history"></i> View Activity
                        </a>
                        <form action="{{ route('admin.users.send-reset-link', $u->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Send a password reset link to {{ $u->email }}?')">
                            @csrf
                            <button type="submit" class="btn-action-reset">
                                <i class="bi bi-envelope-arrow-up"></i> Send Reset Link
                            </button>
                        </form>
                        <form action="{{ route('admin.pos-pin.send-setup-link', $u->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Send a POS PIN setup link to {{ $u->email }}?')">
                            @csrf
                            <button type="submit" class="btn-action-reset">
                                <i class="bi bi-grid-3x3-gap-fill"></i> Send PIN Link
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

{{-- Create User — one centralized path alongside the existing per-role Add pages (see
     SystemUserController::store()'s own docblock). Event Coordinator/Ticket Controller
     are deliberately not offered here — granting those needs an event picked too, which
     the Event & Ticket Access modal below already covers via the existing routes. --}}
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-plus-fill text-warning me-2"></i>Create User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name</label>
                        <input type="text" name="name" class="form-control rounded-3" required value="{{ old('name') }}">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control rounded-3" required value="{{ old('email') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mobile</label>
                            <input type="text" name="mobile" class="form-control rounded-3" required value="{{ old('mobile') }}">
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role" class="form-select rounded-3" required>
                            <option value="">Select a role</option>
                            @foreach($creatableRoles as $r)
                                <option value="{{ $r }}" {{ old('role') === $r ? 'selected' : '' }}>{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-muted small mt-2 mb-0">If the email or mobile already belongs to an existing account, this just grants them the selected role — their existing login is untouched. Otherwise a new account is created and a password setup link is sent.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit User — name/email/mobile/status. Role is never editable here; see the
     controller's own docblock on why a generic role-transfer tool isn't part of this. --}}
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form id="editUserForm" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-fill text-warning me-2"></i>Edit — <span id="editUserName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name</label>
                        <input type="text" name="name" id="editName" class="form-control rounded-3" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" id="editEmail" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mobile</label>
                            <input type="text" name="mobile" id="editMobile" class="form-control rounded-3" required>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" id="editStatus" class="form-select rounded-3" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Event & Ticket Access — a user-centric view of access that already lives entirely in
     event_coordinators/ticket_controllers; every add/remove/change-level action below
     posts straight to the existing EventCoordinatorController/
     TicketControllerAssignmentController routes, unchanged. Driven by fetch() rather than
     full-page form posts specifically so the Ticket Controller routes' own hardcoded
     redirect to the Ticket Console (pre-existing, not something this page changes)
     doesn't yank the admin off this page. --}}
<div class="modal fade" id="accessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-check text-warning me-2"></i>Event &amp; Ticket Access — <span id="accessUserName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div id="accessLoading" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
                <div id="accessContent" style="display:none;">
                    <h6 class="fw-bold text-dark mb-2">Events</h6>
                    <div id="accessEventsList" class="mb-3"></div>
                    <form id="addEventAccessForm" class="row g-2 align-items-end mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold mb-1">Add access to event</label>
                            <select id="addEventSelect" class="form-select form-select-sm rounded-3"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Level</label>
                            <select id="addEventLevel" class="form-select form-select-sm rounded-3">
                                <option value="view">View</option>
                                <option value="entry" selected>Entry</option>
                                <option value="pos">POS</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-warning text-white btn-sm fw-bold rounded-pill w-100" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Add</button>
                        </div>
                    </form>

                    <h6 class="fw-bold text-dark mb-2">Ticket Sales</h6>
                    <div id="accessTicketRow"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    const CSRF_TOKEN = @json(csrf_token());
    // __USER__/__EVENT__ placeholders swapped in at call time — same pattern already used
    // elsewhere on this page's own POS-credential modal (POS_CRED_URL_BASE).
    const USER_UPDATE_URL_BASE = @json(route('admin.users.update', '__USER__'));
    const USER_EVENT_ACCESS_URL_BASE = @json(route('admin.users.event-access', '__USER__'));
    const EVENT_COORD_STORE_URL_BASE = @json(route('admin.events.coordinators.store', '__EVENT__'));
    const EVENT_COORD_LEVEL_URL_BASE = @json(route('admin.events.coordinators.updateLevel', ['__EVENT__', '__USER__']));
    const EVENT_COORD_DESTROY_URL_BASE = @json(route('admin.events.coordinators.destroy', ['__EVENT__', '__USER__']));
    const TICKET_CTRL_STORE_URL = @json(route('admin.ticket-controllers.store'));
    const TICKET_CTRL_LEVEL_URL_BASE = @json(route('admin.ticket-controllers.updateLevel', '__USER__'));
    const TICKET_CTRL_DESTROY_URL_BASE = @json(route('admin.ticket-controllers.destroy', '__USER__'));

    function openEditModal(id, name, email, mobile, status) {
        document.getElementById('editUserForm').action = USER_UPDATE_URL_BASE.replace('__USER__', id);
        document.getElementById('editUserName').textContent = name;
        document.getElementById('editName').value = name;
        document.getElementById('editEmail').value = email;
        document.getElementById('editMobile').value = mobile;
        document.getElementById('editStatus').value = status;
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    }

    let accessUserId = null;
    function openAccessModal(id, name) {
        accessUserId = id;
        document.getElementById('accessUserName').textContent = name;
        document.getElementById('accessLoading').style.display = 'block';
        document.getElementById('accessContent').style.display = 'none';
        new bootstrap.Modal(document.getElementById('accessModal')).show();
        loadAccessData();
    }

    function loadAccessData() {
        fetch(USER_EVENT_ACCESS_URL_BASE.replace('__USER__', accessUserId), { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) { renderAccessData(data); })
            .catch(function () { document.getElementById('accessLoading').textContent = 'Failed to load — please try again.'; });
    }

    function renderAccessData(data) {
        const eventsList = document.getElementById('accessEventsList');
        if (!data.events.length) {
            eventsList.innerHTML = '<p class="text-muted small mb-0">No event access yet.</p>';
        } else {
            eventsList.innerHTML = data.events.map(function (e) {
                return '<div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2 mb-2">' +
                    '<span>' + escapeHtmlSU(e.event_name) + '</span>' +
                    '<span class="d-flex align-items-center gap-2">' +
                        '<select class="form-select form-select-sm rounded-3" style="width:auto;" onchange="changeEventLevel(' + e.event_id + ', this.value)">' +
                            ['view', 'entry', 'pos', 'admin'].map(function (lvl) {
                                return '<option value="' + lvl + '" ' + (lvl === e.level ? 'selected' : '') + '>' + lvl.charAt(0).toUpperCase() + lvl.slice(1) + '</option>';
                            }).join('') +
                        '</select>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="removeEventAccess(' + e.event_id + ')"><i class="bi bi-x-lg"></i></button>' +
                    '</span>' +
                '</div>';
            }).join('');
        }

        const addSelect = document.getElementById('addEventSelect');
        const assignedIds = data.events.map(function (e) { return e.event_id; });
        addSelect.innerHTML = data.all_events.filter(function (e) { return assignedIds.indexOf(e.event_id) === -1; })
            .map(function (e) { return '<option value="' + e.event_id + '">' + escapeHtmlSU(e.event_name) + '</option>'; }).join('');

        const ticketRow = document.getElementById('accessTicketRow');
        if (data.ticket_level) {
            ticketRow.innerHTML = '<div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2">' +
                '<span>Ticket Sales</span>' +
                '<span class="d-flex align-items-center gap-2">' +
                    '<select class="form-select form-select-sm rounded-3" style="width:auto;" onchange="changeTicketLevel(this.value)">' +
                        ['view', 'entry', 'admin'].map(function (lvl) {
                            return '<option value="' + lvl + '" ' + (lvl === data.ticket_level ? 'selected' : '') + '>' + lvl.charAt(0).toUpperCase() + lvl.slice(1) + '</option>';
                        }).join('') +
                    '</select>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="removeTicketAccess()"><i class="bi bi-x-lg"></i></button>' +
                '</span>' +
            '</div>';
        } else {
            ticketRow.innerHTML = '<div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2">' +
                '<span class="text-muted small">No ticket sales access</span>' +
                '<button type="button" class="btn btn-sm btn-warning text-white fw-bold rounded-pill" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;" onclick="addTicketAccess()">Grant Entry Access</button>' +
            '</div>';
        }

        document.getElementById('accessLoading').style.display = 'none';
        document.getElementById('accessContent').style.display = 'block';
    }

    function escapeHtmlSU(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function postForm(url, fields, method) {
        const body = new URLSearchParams(Object.assign({ _token: CSRF_TOKEN }, method ? { _method: method } : {}, fields));
        return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, body: body.toString() });
    }

    document.getElementById('addEventAccessForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const eventId = document.getElementById('addEventSelect').value;
        if (!eventId) { return; }
        const level = document.getElementById('addEventLevel').value;
        postForm(EVENT_COORD_STORE_URL_BASE.replace('__EVENT__', eventId), { user_id: accessUserId, level: level })
            .then(loadAccessData);
    });

    function changeEventLevel(eventId, level) {
        postForm(EVENT_COORD_LEVEL_URL_BASE.replace('__EVENT__', eventId).replace('__USER__', accessUserId), { level: level }).then(loadAccessData);
    }

    function removeEventAccess(eventId) {
        if (!confirm('Remove access to this event?')) { return; }
        postForm(EVENT_COORD_DESTROY_URL_BASE.replace('__EVENT__', eventId).replace('__USER__', accessUserId), {}, 'DELETE').then(loadAccessData);
    }

    function addTicketAccess() {
        postForm(TICKET_CTRL_STORE_URL, { user_id: accessUserId, level: 'entry' }).then(loadAccessData);
    }

    function changeTicketLevel(level) {
        postForm(TICKET_CTRL_LEVEL_URL_BASE.replace('__USER__', accessUserId), { level: level }).then(loadAccessData);
    }

    function removeTicketAccess() {
        if (!confirm('Remove ticket sales access?')) { return; }
        postForm(TICKET_CTRL_DESTROY_URL_BASE.replace('__USER__', accessUserId), {}, 'DELETE').then(loadAccessData);
    }
</script>
@endsection
