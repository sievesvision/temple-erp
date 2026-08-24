@extends('admin.layouts.app')

@section('title','Manage Priests')

@section('page-css')
<style>
    /* Page Header */
    .page-header {
        background: white;
        border-radius: 24px;
        padding: 24px 32px;
        margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
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

    /* Add Priest Button */
    .btn-add-priest {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        border: none;
        padding: 12px 28px;
        border-radius: 40px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn-add-priest:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 8px 24px rgba(184, 134, 58, 0.3);
        color: white;
    }

    /* Table Card */
    .table-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        overflow: hidden;
        padding: 0;
    }
    .table-card .card-header {
        background: transparent;
        border-bottom: 1px solid #f0ece6;
        padding: 18px 24px;
        font-weight: 600;
        font-size: 1.05rem;
        color: #2d1f0e;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .table-card .card-header .table-info {
        font-size: 0.85rem;
        font-weight: 400;
        color: #7b6b5a;
    }
    .table-card .table {
        margin: 0;
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
        transition: 0.2s;
    }
    .table-card .table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Status Badges */
    .badge-status {
        padding: 6px 16px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: capitalize;
    }
    .badge-status.active {
        background: #d1fae5;
        color: #065f46;
    }
    .badge-status.inactive {
        background: #fee2e2;
        color: #991b1b;
    }
    .badge-status.on-leave {
        background: #fef3c7;
        color: #92400e;
    }
    .badge-status.retired {
        background: #e5e7eb;
        color: #4b5563;
    }

    /* Action Buttons */
    .btn-action {
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s;
        margin: 2px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        cursor: pointer;
        border: none;
    }
    .btn-action.view {
        background: #dbeafe;
        color: #1e40af;
    }
    .btn-action.view:hover {
        background: #bfdbfe;
        color: #1e3a8a;
        transform: translateY(-1px);
    }
    .btn-action.edit {
        background: #fef3c7;
        color: #92400e;
    }
    .btn-action.edit:hover {
        background: #fde68a;
        color: #78350f;
        transform: translateY(-1px);
    }
    .btn-action.delete {
        background: #fee2e2;
        color: #991b1b;
    }
    .btn-action.delete:hover {
        background: #fecaca;
        color: #7f1d1d;
        transform: translateY(-1px);
    }

    /* DataTables customization */
    .dataTables_wrapper .dataTables_filter input {
        border-radius: 40px;
        padding: 8px 20px;
        border: 1px solid #e5e7eb;
        background: #faf8f5;
        font-family: 'Inter', sans-serif;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #b8863a;
        box-shadow: 0 0 0 3px rgba(184, 134, 58, 0.1);
        outline: none;
    }
    .dataTables_wrapper .dataTables_length select {
        border-radius: 40px;
        padding: 6px 12px;
        border: 1px solid #e5e7eb;
        background: #faf8f5;
        font-family: 'Inter', sans-serif;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 40px !important;
        padding: 6px 16px !important;
        margin: 0 2px !important;
        border: 1px solid #e5e7eb !important;
        font-family: 'Inter', sans-serif;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: linear-gradient(135deg, #b8863a, #d4a05a) !important;
        color: white !important;
        border-color: #b8863a !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f3ebe0 !important;
        border-color: #b8863a !important;
    }

    /* responsive */
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
        }
        .sidebar.show {
            transform: translateX(0);
        }
        .main-content {
            margin-left: 0;
        }
        .topbar {
            padding: 14px 20px;
        }
        .page-header {
            padding: 20px;
        }
        .page-header h1 {
            font-size: 1.4rem;
        }
    }

    .sidebar::-webkit-scrollbar {
        width: 4px;
    }
    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }
    .sidebar::-webkit-scrollbar-thumb {
        background: #d6cbbc;
        border-radius: 12px;
    }

    /* Modal Styles */
    .modal-content {
        border-radius: 24px;
        border: none;
        box-shadow: 0 24px 48px rgba(0,0,0,0.08);
    }
    .modal-header {
        border-bottom: 1px solid #f0ece6;
        padding: 20px 24px;
    }
    .modal-header .modal-title {
        font-weight: 700;
        color: #2d1f0e;
    }
    .modal-header .modal-title i {
        color: #b8863a;
        margin-right: 8px;
    }
    .modal-footer {
        border-top: 1px solid #f0ece6;
        padding: 16px 24px;
    }
    .modal-footer .btn-danger {
        background: #b34a4a;
        border: none;
        border-radius: 40px;
        padding: 10px 24px;
        font-weight: 600;
    }
    .modal-footer .btn-secondary {
        border-radius: 40px;
        padding: 10px 24px;
        font-weight: 500;
        background: #f0ece6;
        border: none;
        color: #1e1e2a;
    }
    .modal-footer .btn-danger:hover {
        background: #9e3a3a;
    }
    .modal-footer .btn-success {
        background: linear-gradient(135deg, #1f9d6a, #3dbd8a);
        border: none;
        border-radius: 40px;
        padding: 10px 24px;
        font-weight: 600;
    }
    .modal-footer .btn-success:hover {
        transform: scale(1.02);
    }
    .modal-body {
        padding: 24px;
    }
    .modal-body label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #5a4e3e;
        margin-bottom: 4px;
    }
    .modal-body .form-control,
    .modal-body .form-select {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 10px 14px;
        font-family: 'Inter', sans-serif;
        background: #faf8f5;
    }
    .modal-body .form-control:focus,
    .modal-body .form-select:focus {
        border-color: #b8863a;
        box-shadow: 0 0 0 3px rgba(184, 134, 58, 0.1);
    }
    .modal-body .form-control[readonly] {
        background: #f5f0ea;
        cursor: not-allowed;
    }

    .close-btn {
        background: #b8863a;
        color: white;
        border: none;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.3s;
    }
    .close-btn:hover {
        transform: rotate(90deg);
        background: #a07431;
    }

    /* Custom Alert Styles */
    .alert-custom {
        border-radius: 16px;
        padding: 16px 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.5s ease-out;
    }
    .alert-custom i {
        font-size: 1.5rem;
    }
    .alert-custom .btn-close {
        margin-left: auto;
    }
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Priest count badge */
    .count-badge {
        background: #b8863a15;
        color: #b8863a;
        padding: 4px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.85rem;
    }
</style>
@endsection

@section('content')
<!-- ===== MESSAGES SECTION - MOVED TO TOP ===== -->
<!-- Success Message -->
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show alert-custom mb-4 animate__animated animate__fadeIn" 
     style="background: #d1fae5; color: #065f46; border-left: 4px solid #059669;">
    <i class="bi bi-check-circle-fill"></i>
    <div>
        <strong>Success!</strong> {{ session('success') }}
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Error Message -->
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show alert-custom mb-4 animate__animated animate__fadeIn" 
     style="background: #fee2e2; color: #991b1b; border-left: 4px solid #dc3545;">
    <i class="bi bi-exclamation-circle-fill"></i>
    <div>
        <strong>Error!</strong> {{ session('error') }}
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Password Alert (when generated) -->
@if(session('generated_password'))
<div class="alert alert-warning alert-dismissible fade show alert-custom mb-4 animate__animated animate__fadeIn" 
     style="background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b;">
    <i class="bi bi-key-fill"></i>
    <div>
        <strong>User's 6-digit password is:</strong> 
        <span style="font-weight: 700; font-family: monospace; font-size: 1.1rem; background: white; padding: 2px 12px; border-radius: 6px; border: 1px solid #fde68a;">
            {{ session('generated_password') }}
        </span>
        <span class="ms-2" style="font-size: 0.85rem;">
            <i class="bi bi-info-circle"></i> Please share this with the priest
        </span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
<!-- ===== END MESSAGES ===== -->

<!-- PAGE CONTENT -->
<div class="container-fluid px-4 py-4">

    <!-- Page Header -->
    <div class="page-header animate__animated animate__fadeIn">
        <div>
            <h1><i class="bi bi-person-badge"></i> Manage Priests</h1>
            <div class="subtitle">
                <i class="bi bi-database me-1"></i> Total Priests: <strong>{{ $priests->count() }}</strong>
                <span class="count-badge ms-2"><i class="bi bi-person-check"></i> Active: <strong>{{ $priests->where('employment_status', 'Active')->count() }}</strong></span>
            </div>
        </div>
        <a href="{{ url('/admin/add-priest') }}" class="btn-add-priest animate__animated animate__pulse animate__infinite">
            <i class="bi bi-person-plus-fill"></i> Add Priest
        </a>
    </div>

    <!-- Table Card -->
    <div class="table-card animate__animated animate__fadeInUp">
        <div class="card-header">
            <span><i class="bi bi-table me-2" style="color:#b8863a;"></i> Priest List</span>
            <span class="table-info">
                <i class="bi bi-search me-1"></i> Search, filter & manage priests
            </span>
        </div>
        <div style="padding: 0 20px 20px 20px; overflow-x: auto;">
            <table class="table table-striped" id="priestsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Specialization</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($priests as $priest)
                    <tr>
                        <td><strong>{{ $priest->priest_id }}</strong></td>
                        <td><i class="bi bi-person-circle me-2" style="color:#b8863a;"></i> {{ $priest->name }}</td>
                        <td>{{ $priest->mobile }}</td>
                        <td>{{ $priest->email }}</td>
                        <td>
                            <span class="badge bg-primary bg-opacity-10 text-primary">
                                {{ $priest->specialization ?? 'N/A' }}
                            </span>
                        </td>
                        <td>₹{{ number_format($priest->monthly_salary) }}</td>
                        <td>
                            @php
                                $statusClass = 'active';
                                if($priest->employment_status == 'Inactive') $statusClass = 'inactive';
                                elseif($priest->employment_status == 'On Leave') $statusClass = 'on-leave';
                                elseif($priest->employment_status == 'Retired') $statusClass = 'retired';
                            @endphp
                            <span class="badge-status {{ $statusClass }}">
                                {{ $priest->employment_status }}
                            </span>
                        </td>
                        <td>{{ $priest->joining_date }}</td>
                        <td>
                            <button class="btn-action view viewBtn"
                                data-bs-toggle="modal"
                                data-bs-target="#viewPriestModal"
                                data-id="{{ $priest->priest_id }}"
                                data-name="{{ $priest->name }}"
                                data-mobile="{{ $priest->mobile }}"
                                data-email="{{ $priest->email }}"
                                data-gender="{{ $priest->gender ?? 'N/A' }}"
                                data-dob="{{ $priest->dob ?? 'N/A' }}"
                                data-specialization="{{ $priest->specialization ?? 'N/A' }}"
                                data-salary="{{ $priest->monthly_salary }}"
                                data-status="{{ $priest->employment_status }}"
                                data-current="{{ $priest->current_status ?? 'N/A' }}"
                                data-joining="{{ $priest->joining_date }}"
                                data-experience="{{ $priest->experience_years ?? '0' }}"
                                data-wallet="{{ $priest->wallet_balance ?? '0' }}"
                                data-account="{{ $priest->account_number ?? 'N/A' }}"
                                data-bank="{{ $priest->bank_name ?? 'N/A' }}"
                                data-ifsc="{{ $priest->ifsc_code ?? 'N/A' }}"
                                data-address="{{ $priest->address ?? 'N/A' }}"
                                data-account-holder="{{ $priest->account_holder_name ?? 'N/A' }}"
                                data-branch="{{ $priest->branch_name ?? 'N/A' }}">
                                <i class="bi bi-eye"></i> View
                            </button>

                            <button class="btn-action edit editBtn"
                                data-bs-toggle="modal"
                                data-bs-target="#editPriestModal"
                                data-id="{{ $priest->priest_id }}"
                                data-name="{{ $priest->name }}"
                                data-mobile="{{ $priest->mobile }}"
                                data-email="{{ $priest->email }}"
                                data-specialization="{{ $priest->specialization }}"
                                data-salary="{{ $priest->monthly_salary }}"
                                data-employment_status="{{ $priest->employment_status }}"
                                data-current_status="{{ $priest->current_status }}"
                                data-joining_date="{{ $priest->joining_date }}"
                                data-address="{{ $priest->address }}"
                                data-account_holder_name="{{ $priest->account_holder_name }}"
                                data-account_number="{{ $priest->account_number }}"
                                data-ifsc_code="{{ $priest->ifsc_code }}"
                                data-bank_name="{{ $priest->bank_name }}"
                                data-branch_name="{{ $priest->branch_name }}">
                                <i class="bi bi-pencil"></i> Edit
                            </button>

                            <button class="btn-action delete" data-bs-toggle="modal" data-bs-target="#deleteModal" data-priest-id="{{ $priest->priest_id }}" data-priest-name="{{ $priest->name }}">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 text-muted-light small d-flex justify-content-end">
        <i class="bi bi-droplet me-1"></i> Last updated: {{ now()->format('d M Y, h:i A') }}
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Delete Priest</h5>
                <button type="button" class="close-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-2" style="font-weight: 450; color: #2d1f0e;">Are you sure you want to delete this priest?</p>
                <p class="text-muted" style="font-size:0.95rem;">
                    <strong>Priest:</strong> <span id="deletePriestName"></span><br>
                    <strong>ID:</strong> <span id="deletePriestId"></span>
                </p>
                <p class="text-danger small"><i class="bi bi-info-circle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- VIEW PRIEST MODAL -->
<div class="modal fade" id="viewPriestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge"></i> Priest Details</h5>
                <button type="button" class="close-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-12 mb-3">
                        <h6 class="text-muted" style="font-weight:700; border-bottom:2px solid #b8863a; padding-bottom:8px;">
                            <i class="bi bi-person me-2" style="color:#b8863a;"></i>Personal Information
                        </h6>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Priest ID</label>
                        <input type="text" id="view_id" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Name</label>
                        <input type="text" id="view_name" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Mobile</label>
                        <input type="text" id="view_mobile" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Email</label>
                        <input type="text" id="view_email" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Gender</label>
                        <input type="text" id="view_gender" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Date of Birth</label>
                        <input type="text" id="view_dob" class="form-control" readonly>
                    </div>
                    <div class="col-12 mb-3">
                        <label>Address</label>
                        <textarea id="view_address" class="form-control" rows="2" readonly></textarea>
                    </div>

                    <!-- Professional Information -->
                    <div class="col-12 mb-3 mt-2">
                        <h6 class="text-muted" style="font-weight:700; border-bottom:2px solid #b8863a; padding-bottom:8px;">
                            <i class="bi bi-briefcase me-2" style="color:#b8863a;"></i>Professional Information
                        </h6>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Specialization</label>
                        <input type="text" id="view_specialization" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Experience (Years)</label>
                        <input type="text" id="view_experience" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Monthly Salary</label>
                        <input type="text" id="view_salary" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Joining Date</label>
                        <input type="text" id="view_joining" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Employment Status</label>
                        <input type="text" id="view_status" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Current Status</label>
                        <input type="text" id="view_current" class="form-control" readonly>
                    </div>

                    <!-- Banking Information -->
                    <div class="col-12 mb-3 mt-2">
                        <h6 class="text-muted" style="font-weight:700; border-bottom:2px solid #b8863a; padding-bottom:8px;">
                            <i class="bi bi-bank me-2" style="color:#b8863a;"></i>Banking Information
                        </h6>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Account Holder Name</label>
                        <input type="text" id="view_account_holder" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Account Number</label>
                        <input type="text" id="view_account" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Bank Name</label>
                        <input type="text" id="view_bank" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Branch Name</label>
                        <input type="text" id="view_branch" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>IFSC Code</label>
                        <input type="text" id="view_ifsc" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Wallet Balance</label>
                        <input type="text" id="view_wallet" class="form-control" readonly>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT PRIEST MODAL -->
<div class="modal fade" id="editPriestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Priest</h5>
                <button type="button" class="close-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST" action="">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Mobile</label>
                            <input type="text" name="mobile" id="edit_mobile" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Specialization</label>
                            <input type="text" name="specialization" id="edit_specialization" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Monthly Salary</label>
                            <input type="number" name="salary" id="edit_salary" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Employment Status</label>
                            <select name="employment_status" id="edit_employment_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="On Leave">On Leave</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Retired">Retired</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Current Status</label>
                            <select name="current_status" id="edit_current_status" class="form-select">
                                <option value="Online">Online</option>
                                <option value="Offline">Offline</option>
                                <option value="Busy">Busy</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Joining Date</label>
                            <input type="date" name="joining_date" id="edit_joining_date" class="form-control">
                        </div>
                        <div class="col-12 mb-3">
                            <label>Address</label>
                            <textarea name="address" id="edit_address" rows="2" class="form-control"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Account Holder Name</label>
                            <input type="text" name="account_holder_name" id="edit_account_holder_name" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Account Number</label>
                            <input type="text" name="account_number" id="edit_account_number" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>IFSC Code</label>
                            <input type="text" name="ifsc_code" id="edit_ifsc_code" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" id="edit_bank_name" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Branch Name</label>
                            <input type="text" name="branch_name" id="edit_branch_name" class="form-control">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-circle me-2"></i> Update Priest
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-js')
<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#priestsTable').DataTable({
            pageLength: 10,
            responsive: true,
            language: {
                search: "<i class='bi bi-search me-1'></i> Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ priests",
                infoEmpty: "No priests found",
                infoFiltered: "(filtered from _MAX_ total priests)"
            },
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: 8 }
            ]
        });

        // ===== AUTO-HIDE MESSAGES =====
        // Auto-hide success message after 3 seconds
        setTimeout(function() {
            $('.alert-success').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 3000);

        // Auto-hide error message after 5 seconds
        setTimeout(function() {
            $('.alert-danger').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);

        // Auto-hide password alert after 8 seconds
        setTimeout(function() {
            $('.alert-warning').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 8000);

        // Delete Modal - Set priest details
        $('#deleteModal').on('show.bs.modal', function(event) {
            const button = $(event.relatedTarget);
            const priestId = button.data('priest-id');
            const priestName = button.data('priest-name');
            const modal = $(this);
            modal.find('#deletePriestId').text(priestId);
            modal.find('#deletePriestName').text(priestName);
            modal.find('#deleteForm').attr('action', '/admin/priest/delete/' + priestId);
        });

        // View Modal - Populate all fields
        $('.viewBtn').on('click', function() {
            const data = $(this).data();
            
            $('#view_id').val(data.id);
            $('#view_name').val(data.name);
            $('#view_mobile').val(data.mobile);
            $('#view_email').val(data.email);
            $('#view_gender').val(data.gender);
            $('#view_dob').val(data.dob);
            $('#view_specialization').val(data.specialization);
            $('#view_experience').val(data.experience + ' Years');
            $('#view_salary').val('₹' + Number(data.salary).toLocaleString());
            $('#view_joining').val(data.joining);
            $('#view_status').val(data.status);
            $('#view_current').val(data.current);
            $('#view_account_holder').val(data.accountHolder);
            $('#view_account').val(data.account);
            $('#view_bank').val(data.bank);
            $('#view_branch').val(data.branch);
            $('#view_ifsc').val(data.ifsc);
            $('#view_wallet').val('₹' + Number(data.wallet).toLocaleString());
            $('#view_address').val(data.address);
        });

        // Edit Modal - Populate all fields
        $('#editPriestModal').on('show.bs.modal', function(event) {
            const button = $(event.relatedTarget);
            const data = button.data();
            
            $('#editForm').attr('action', '/admin/priest/update/' + data.id);
            
            $('#edit_name').val(data.name);
            $('#edit_mobile').val(data.mobile);
            $('#edit_email').val(data.email);
            $('#edit_specialization').val(data.specialization);
            $('#edit_salary').val(data.salary);
            $('#edit_employment_status').val(data.employment_status);
            $('#edit_current_status').val(data.current_status);
            $('#edit_joining_date').val(data.joining_date);
            $('#edit_address').val(data.address);
            $('#edit_account_holder_name').val(data.account_holder_name);
            $('#edit_account_number').val(data.account_number);
            $('#edit_ifsc_code').val(data.ifsc_code);
            $('#edit_bank_name').val(data.bank_name);
            $('#edit_branch_name').val(data.branch_name);
        });
    });
</script>

<!-- Testing Mode Success Modal -->
@if(session('success_user_created'))
<div class="modal fade" id="testingUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4" style="background: #fdfbf7; border: 1px solid #b8863a !important;">
            <div class="modal-header border-0 pb-0 text-center d-block">
                <span class="fs-1">✨</span>
                <h4 class="modal-title fw-bold text-success mt-2">User Created (Testing Mode)</h4>
            </div>
            <div class="modal-body py-4 px-4">
                <p class="text-muted text-center mb-4">Since the system is in <strong>Testing Mode</strong>, the credentials are shown below. No emails are sent unless configured otherwise.</p>
                <div class="bg-white p-3 rounded-3 border mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Name:</span>
                        <span class="fw-bold">{{ session('success_user_created.name') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Email:</span>
                        <span class="fw-bold">{{ session('success_user_created.email') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Role:</span>
                        <span class="fw-bold"><span class="badge bg-warning text-dark">{{ session('success_user_created.role') }}</span></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Temporary Password:</span>
                        <span class="fw-bold text-danger">{{ session('success_user_created.password') }}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-warning rounded-pill px-4 text-white fw-bold" data-bs-dismiss="modal" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var myModal = new bootstrap.Modal(document.getElementById('testingUserModal'));
        myModal.show();
    });
</script>
@endif
@endsection