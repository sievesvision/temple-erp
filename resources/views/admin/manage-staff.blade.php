@extends('admin.layouts.app')

@section('title', 'Manage Staff')

@section('page-css')
<style>
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
    .btn-add {
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
    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(184, 134, 58, 0.3);
        color: white;
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
    .btn-action-edit {
        background: rgba(184, 134, 58, 0.1);
        color: #b8863a;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action-edit:hover {
        background: #b8863a;
        color: white;
    }
    .btn-action-delete {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
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
    .btn-action-delete:hover {
        background: #dc3545;
        color: white;
    }
    .btn-action-view {
        background: rgba(42, 111, 219, 0.1);
        color: #2a6fdb;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action-view:hover {
        background: #2a6fdb;
        color: white;
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
    .dataTables_wrapper .row:first-child {
        padding: 14px 20px;
        background: #faf8f5;
        border-bottom: 1px solid #f0ece6;
    }
    .dataTables_wrapper .row:last-child {
        padding: 14px 20px;
        background: #faf8f5;
        border-top: 1px solid #f0ece6;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1><i class="bi bi-person-lines-fill"></i>Manage Staff</h1>
        <div class="subtitle">Add, modify, and manage temple administration staff members</div>
    </div>
    <div>
        <a href="{{ route('admin.staff.create') }}" class="btn-add">
            <i class="bi bi-plus-lg"></i> Add Staff Member
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert" style="background: #d1fae5; color: #065f46;">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        @if(session('generated_password'))
            <br><strong>Temporary Login Password:</strong> <code class="bg-white px-2 py-1 rounded text-dark">{{ session('generated_password') }}</code>
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert" style="background: #fee2e2; color: #991b1b;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 p-3" style="background: #fee2e2; color: #991b1b;">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="table-card">
    <div class="card-header">
        <span>Temple Staff Members List</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle" id="staffTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Designation</th>
                    <th>Salary</th>
                    <th>Status</th>
                    <th>Joining Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($staffList as $s)
                <tr>
                    <td><strong>ST{{ str_pad($s->staff_id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->email }}</td>
                    <td>{{ $s->mobile }}</td>
                    <td><span class="badge px-3 py-2 rounded-pill" style="color: #ffffff; background-color: #b8863a;">{{ $s->designation }}</span></td>
                    <td>₹{{ number_format($s->salary, 2) }}</td>
                    <td>
                        <span class="badge rounded-pill px-3 py-2 bg-{{ $s->employment_status === 'Active' ? 'success' : ($s->employment_status === 'On Leave' ? 'info' : 'danger') }} bg-opacity-10 text-{{ $s->employment_status === 'Active' ? 'success' : ($s->employment_status === 'On Leave' ? 'info' : 'danger') }}">
                            {{ $s->employment_status }}
                        </span>
                    </td>
                    <td>{{ date('d M Y', strtotime($s->joining_date)) }}</td>
                    <td class="text-end">
                        <button class="btn-action-view me-1" data-bs-toggle="modal" data-bs-target="#viewModal{{ $s->staff_id }}">
                            <i class="bi bi-eye"></i> View
                        </button>
                        <button class="btn-action-edit me-1" data-bs-toggle="modal" data-bs-target="#editModal{{ $s->staff_id }}">
                            <i class="bi bi-pencil-square"></i> Edit
                        </button>
                        <form action="{{ route('admin.staff.delete', $s->staff_id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this staff member?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-action-delete">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Modals outside of the table -->
@foreach($staffList as $s)
    <!-- View Modal -->
    <div class="modal fade" id="viewModal{{ $s->staff_id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-lines-fill text-warning me-2"></i>Staff Profile Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="bg-light p-3 rounded-3 border mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Staff ID:</span>
                            <span class="fw-bold">ST{{ str_pad($s->staff_id, 4, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Full Name:</span>
                            <span class="fw-bold">{{ $s->name }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Email Address:</span>
                            <span class="fw-bold">{{ $s->email }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Mobile Number:</span>
                            <span class="fw-bold">{{ $s->mobile }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Designation:</span>
                            <span class="fw-bold"><span class="badge bg-warning text-dark">{{ $s->designation }}</span></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Monthly Salary:</span>
                            <span class="fw-bold">₹{{ number_format($s->salary, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Current Status:</span>
                            <span class="fw-bold">
                                <span class="badge rounded-pill px-3 py-1 bg-{{ $s->employment_status === 'Active' ? 'success' : 'danger' }} bg-opacity-10 text-{{ $s->employment_status === 'Active' ? 'success' : 'danger' }}">
                                    {{ $s->employment_status }}
                                </span>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Joining Date:</span>
                            <span class="fw-bold">{{ date('d M Y', strtotime($s->joining_date)) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Gender:</span>
                            <span class="fw-bold">{{ $s->gender ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Date of Birth:</span>
                            <span class="fw-bold">{{ $s->dob ? date('d M Y', strtotime($s->dob)) : 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Current Wallet Balance:</span>
                            <span class="fw-bold text-{{ $s->wallet_balance >= 0 ? 'success' : 'danger' }}">{{ $s->wallet_balance >= 0 ? '+' : '' }}₹{{ number_format($s->wallet_balance, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Address:</span>
                            <span class="fw-bold text-end" style="max-width: 250px;">{{ $s->address ?? 'N/A' }}</span>
                        </div>
                    </div>
                    
                    <h6 class="fw-bold text-warning mb-2 small"><i class="bi bi-bank me-1"></i>Bank Account Information</h6>
                    <div class="bg-white p-3 rounded-3 border">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Account Holder:</span>
                            <span class="fw-bold">{{ $s->account_holder_name ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Account Number:</span>
                            <span class="fw-bold">{{ $s->account_number ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Bank Name:</span>
                            <span class="fw-bold">{{ $s->bank_name ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">IFSC Code:</span>
                            <span class="fw-bold">{{ $s->ifsc_code ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Branch Name:</span>
                            <span class="fw-bold">{{ $s->branch_name ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-warning text-white fw-bold rounded-pill px-4" data-bs-dismiss="modal" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal{{ $s->staff_id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.staff.update', $s->staff_id) }}" method="POST">
                    @csrf
                    <div class="modal-body py-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name</label>
                                <input type="text" name="name" class="form-control rounded-3" value="{{ $s->name }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Address</label>
                                <input type="email" name="email" class="form-control rounded-3" value="{{ $s->email }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Mobile Number</label>
                                <input type="text" name="mobile" class="form-control rounded-3" value="{{ $s->mobile }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Designation</label>
                                <input type="text" name="designation" class="form-control rounded-3" value="{{ $s->designation }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Salary (Monthly)</label>
                                <input type="number" name="salary" class="form-control rounded-3" value="{{ $s->salary }}" required step="0.01">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Employment Status</label>
                                <select name="employment_status" class="form-select rounded-3" required>
                                    <option value="Active" {{ $s->employment_status === 'Active' ? 'selected' : '' }}>Active</option>
                                    <option value="On Leave" {{ $s->employment_status === 'On Leave' ? 'selected' : '' }}>On Leave</option>
                                    <option value="Inactive" {{ $s->employment_status === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Joining Date</label>
                                <input type="date" name="joining_date" class="form-control rounded-3" value="{{ $s->joining_date }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Gender</label>
                                <select name="gender" class="form-select rounded-3">
                                    <option value="">Select Gender</option>
                                    <option value="Male" {{ $s->gender === 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ $s->gender === 'Female' ? 'selected' : '' }}>Female</option>
                                    <option value="Other" {{ $s->gender === 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Date of Birth</label>
                                <input type="date" name="dob" class="form-control rounded-3" value="{{ $s->dob }}">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Address</label>
                                <textarea name="address" class="form-control rounded-3" rows="3">{{ $s->address }}</textarea>
                            </div>

                            <h6 class="fw-bold text-warning mt-4 mb-2">Bank Details</h6>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Account Holder Name</label>
                                <input type="text" name="account_holder_name" class="form-control rounded-3" value="{{ $s->account_holder_name }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Account Number</label>
                                <input type="text" name="account_number" class="form-control rounded-3" value="{{ $s->account_number }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control rounded-3" value="{{ $s->bank_name }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">IFSC Code</label>
                                <input type="text" name="ifsc_code" class="form-control rounded-3" value="{{ $s->ifsc_code }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Branch Name</label>
                                <input type="text" name="branch_name" class="form-control rounded-3" value="{{ $s->branch_name }}">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                        <button type="submit" class="btn btn-warning rounded-pill px-4 text-white fw-bold" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

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
@endif

@endsection

@section('page-js')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var testingModalEl = document.getElementById('testingUserModal');
        if (testingModalEl) {
            var myModal = new bootstrap.Modal(testingModalEl);
            myModal.show();
        }
    });

    $(document).ready(function() {
        $('#staffTable').DataTable({
            pageLength: 10,
            responsive: true,
            language: {
                search: "<i class='bi bi-search me-1'></i> Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ staff members",
                infoEmpty: "No staff members found",
                infoFiltered: "(filtered from _MAX_ total staff members)",
                emptyTable: "No staff members found",
                zeroRecords: "No matching staff members found"
            },
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: 8 }
            ]
        });
    });
</script>
@endsection
