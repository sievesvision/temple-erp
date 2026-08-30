@extends('admin.layouts.app')

@section('title', 'Manage Donations')

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
        padding: 12px 24px;
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
    .stat-card {
        background: white;
        border-radius: 24px;
        padding: 22px 24px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(184, 134, 58, 0.06);
        transition: transform 0.15s, box-shadow 0.2s;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 32px rgba(184, 134, 58, 0.08);
    }
    .stat-card .stat-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #7b6b5a;
        font-weight: 600;
    }
    .stat-card .stat-number {
        font-size: 2.2rem;
        font-weight: 700;
        color: #1e1e2a;
        letter-spacing: -0.5px;
        margin: 4px 0 0 0;
    }
    .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }
    .stat-icon.gold {
        background: #b8863a;
    }
    .stat-icon.blue {
        background: #2a6fdb;
    }
    .stat-icon.green {
        background: #1f9d6a;
    }
    .stat-icon.purple {
        background: #8b5cf6;
    }
    .table-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        overflow: hidden;
        margin-bottom: 24px;
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
    .nav-tabs .nav-link {
        border-radius: 12px 12px 0 0;
        color: #7b6b5a;
        font-weight: 500;
        border: none;
        padding: 12px 20px;
    }
    .nav-tabs .nav-link.active {
        color: #b8863a;
        border-bottom: 3px solid #b8863a;
        font-weight: 600;
        background: transparent;
    }
    .btn-action-edit, .btn-action-delete, .btn-action-resend, .btn-action-approve {
        border: none;
        padding: 6px 12px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.72rem;
        transition: 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .btn-action-edit {
        background: rgba(184, 134, 58, 0.1);
        color: #b8863a;
    }
    .btn-action-edit:hover {
        background: #b8863a;
        color: white;
    }
    .btn-action-delete {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    .btn-action-delete:hover {
        background: #dc3545;
        color: white;
    }
    .btn-action-resend {
        background: rgba(42, 111, 219, 0.1);
        color: #2a6fdb;
    }
    .btn-action-resend:hover {
        background: #2a6fdb;
        color: white;
    }
    .btn-action-approve {
        background: rgba(31, 157, 106, 0.1);
        color: #1f9d6a;
    }
    .btn-action-approve:hover {
        background: #1f9d6a;
        color: white;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1><i class="bi bi-wallet2"></i>Manage Donations</h1>
        <div class="subtitle">Log, view, and audit donations received from registered devotees and guest donors</div>
    </div>
    @if($canAddDonation)
    <div class="d-flex gap-2">
        <button class="btn-add" data-bs-toggle="modal" data-bs-target="#recordDevoteeDonationModal">
            <i class="bi bi-person-check-fill"></i> Log Devotee Donation
        </button>
        <button class="btn-add" style="background: linear-gradient(135deg, #2a6fdb, #548ee8);" data-bs-toggle="modal" data-bs-target="#recordGuestDonationModal">
            <i class="bi bi-person-heart"></i> Log Guest Donation
        </button>
    </div>
    @endif
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

@if ($errors->any())
    <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 p-3" style="background: #fee2e2; color: #991b1b;">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- STATS ROW -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Donations Received</div>
                <div class="stat-number">{{ $temple['currency'] }} {{ number_format($grandTotal, 2) }}</div>
            </div>
            <div class="stat-icon gold"><i class="bi bi-cash-coin"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Devotee Contributions</div>
                <div class="stat-number">{{ $temple['currency'] }} {{ number_format($devoteeTotal, 2) }}</div>
            </div>
            <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Guest / Walk-In Donations</div>
                <div class="stat-number">{{ $temple['currency'] }} {{ number_format($guestTotal, 2) }}</div>
            </div>
            <div class="stat-icon blue"><i class="bi bi-person-heart"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">e-Hundi Collections</div>
                <div class="stat-number">{{ $temple['currency'] }} {{ number_format($ehundiTotal, 2) }}</div>
            </div>
            <div class="stat-icon purple"><i class="bi bi-box-seam-fill"></i></div>
        </div>
    </div>
</div>

<!-- TABS NAVIGATION -->
<div class="card border-0 shadow-sm rounded-4 p-0" style="background: white;">
    <div class="px-4 pt-3 border-bottom">
        <ul class="nav nav-tabs border-0" id="donationTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="devotee-tab" data-bs-toggle="tab" data-bs-target="#devotee-pane" type="button" role="tab"><i class="bi bi-people-fill text-warning me-1"></i>Devotee Donations</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="guest-tab" data-bs-toggle="tab" data-bs-target="#guest-pane" type="button" role="tab"><i class="bi bi-person-heart text-warning me-1"></i>Guest Donations</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="ehundi-tab" data-bs-toggle="tab" data-bs-target="#ehundi-pane" type="button" role="tab"><i class="bi bi-coin text-warning me-1"></i>e-Hundi Offerings</button>
            </li>
        </ul>
    </div>

    <div class="tab-content p-4" id="donationTabsContent">
        <!-- Devotee Donations Pane -->
        <div class="tab-pane fade show active" id="devotee-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Donation ID</th>
                            <th>Devotee Name</th>
                            <th>Amount</th>
                            <th>Event</th>
                            <th>Payment Mode</th>
                            <th>Transaction ID</th>
                            <th>Date</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($devoteeDonations as $d)
                        <tr>
                            <td><strong>DN{{ str_pad($d->id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $d->devotee_name }}</div>
                                <div class="text-muted small">{{ $d->mobile ?? 'No mobile' }}</div>
                            </td>
                            <td><span class="fw-bold text-success">{{ $temple['currency'] }} {{ number_format($d->amount, 2) }}</span></td>
                            <td>{{ $d->event_name ?? 'General Fund' }}</td>
                            <td><span class="badge bg-light text-dark border px-3 py-2 rounded-pill">{{ $d->payment_method }}</span></td>
                            <td><code class="small text-dark">{{ $d->transaction_id }}</code></td>
                            <td>{{ date('d M Y', strtotime($d->donation_date)) }}</td>
                            <td><span class="text-muted small">{{ $d->remarks ?? 'N/A' }}</span></td>
                            <td>
                                @php
                                    $devoteeStatusColor = ['Paid' => 'success', 'Pending' => 'warning', 'Cancelled' => 'secondary', 'Failed' => 'danger'][$d->payment_status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $devoteeStatusColor }} bg-opacity-10 text-{{ $devoteeStatusColor }} px-3 py-2 rounded-pill">{{ $d->payment_status }}</span>
                            </td>
                            <td class="text-end">
                                @if($canEditDonation && $d->payment_status === 'Pending' && in_array($d->payment_method, ['Bank Transfer', 'Bank', 'Cash']))
                                <form action="{{ route('admin.donations.approveDevotee', $d->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm that this payment was received and approve this donation?')">
                                    @csrf
                                    <button type="submit" class="btn-action-approve" title="Approve this donation as received">
                                        <i class="bi bi-check-circle"></i> Approve
                                    </button>
                                </form>
                                @endif
                                @if($d->email && $d->payment_status === 'Paid')
                                <form action="{{ route('admin.donations.resendReceipt', ['type' => 'devotee', 'id' => $d->id]) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn-action-resend" title="Resend receipt to {{ $d->email }}">
                                        <i class="bi bi-envelope-arrow-up"></i> Resend
                                    </button>
                                </form>
                                @endif
                                @if($canEditDonation)
                                <button type="button" class="btn-action-edit" data-bs-toggle="modal" data-bs-target="#editDevoteeDonationModal{{ $d->id }}">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                @endif
                                @if($canDeleteDonation)
                                <form action="{{ route('admin.donations.deleteDevotee', $d->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this donation record? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action-delete">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <i class="bi bi-cash fs-1 d-block mb-2 text-warning"></i>
                                No devotee donations found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($canEditDonation)
            @foreach($devoteeDonations as $d)
            <div class="modal fade" id="editDevoteeDonationModal{{ $d->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <form action="{{ route('admin.donations.updateDevotee', $d->id) }}" method="POST">
                            @csrf
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Devotee Donation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body py-3">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Devotee</label>
                                    <input type="text" class="form-control rounded-3" value="{{ $d->devotee_name }}" disabled>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Donation Amount ({{ $temple['currency'] }})</label>
                                    <input type="number" step="0.01" name="amount" class="form-control rounded-3" value="{{ $d->amount }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Event (Optional)</label>
                                    <select name="event_id" class="form-select rounded-3">
                                        <option value="">-- General Fund --</option>
                                        @foreach($events as $event)
                                            <option value="{{ $event->event_id }}" {{ $d->event_id == $event->event_id ? 'selected' : '' }}>{{ $event->event_name }} ({{ date('d M Y', strtotime($event->event_date)) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Payment Mode</label>
                                        <select name="payment_mode" class="form-select rounded-3" required>
                                            @foreach(['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'Stripe'] as $mode)
                                                <option value="{{ $mode }}" {{ $d->payment_method === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Payment Status</label>
                                        <select name="payment_status" class="form-select rounded-3" required>
                                            @foreach(['Paid', 'Pending', 'Cancelled', 'Failed'] as $status)
                                                <option value="{{ $status }}" {{ $d->payment_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Transaction ID / Reference</label>
                                    <input type="text" name="transaction_id" class="form-control rounded-3" value="{{ $d->transaction_id }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Donation Date</label>
                                    <input type="date" name="donation_date" class="form-control rounded-3" value="{{ $d->donation_date }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Remarks</label>
                                    <input type="text" name="remarks" class="form-control rounded-3" value="{{ $d->remarks }}">
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                                <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
            @endif
        </div>

        <!-- Guest Donations Pane -->
        <div class="tab-pane fade" id="guest-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Donor Name</th>
                            <th>Contact Details</th>
                            <th>Amount</th>
                            <th>Purpose</th>
                            <th>Event</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($guestDonations as $g)
                        <tr>
                            <td><strong>GD{{ str_pad($g->id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                            <td><span class="fw-semibold text-dark">{{ $g->donor_name }}</span></td>
                            <td>
                                <div class="small text-dark">{{ $g->mobile ?? 'No mobile' }}</div>
                                <div class="small text-muted">{{ $g->email ?? 'No email' }}</div>
                            </td>
                            <td><span class="fw-bold text-success">{{ $temple['currency'] }} {{ number_format($g->amount, 2) }}</span></td>
                            <td>
                                <div class="fw-semibold small">{{ $g->purpose }}</div>
                                <div class="text-muted small text-truncate" style="max-width: 180px;">{{ $g->purpose_details }}</div>
                            </td>
                            <td>{{ $g->event_name ?? 'General Fund' }}</td>
                            <td><span class="badge bg-light text-dark border px-3 py-2 rounded-pill">{{ $g->payment_method }}</span></td>
                            <td>
                                @php
                                    $statusColor = ['Paid' => 'success', 'Pending' => 'warning', 'Cancelled' => 'secondary', 'Failed' => 'danger'][$g->payment_status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $statusColor }} bg-opacity-10 text-{{ $statusColor }} px-3 py-2 rounded-pill">{{ $g->payment_status }}</span>
                            </td>
                            <td><code class="small text-dark">{{ $g->transaction_id }}</code></td>
                            <td>{{ date('d M Y', strtotime($g->donation_date)) }}</td>
                            <td class="text-end">
                                @if($canEditDonation && $g->payment_status === 'Pending' && in_array($g->payment_method, ['Bank', 'Cash']))
                                <form action="{{ route('admin.donations.approveGuest', $g->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm that this payment was received and approve this donation?')">
                                    @csrf
                                    <button type="submit" class="btn-action-approve" title="Approve this donation as received">
                                        <i class="bi bi-check-circle"></i> Approve
                                    </button>
                                </form>
                                @endif
                                @if($g->email && $g->payment_status === 'Paid')
                                <form action="{{ route('admin.donations.resendReceipt', ['type' => 'guest', 'id' => $g->id]) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn-action-resend" title="Resend receipt to {{ $g->email }}">
                                        <i class="bi bi-envelope-arrow-up"></i> Resend
                                    </button>
                                </form>
                                @endif
                                @if($canEditDonation)
                                <button type="button" class="btn-action-edit" data-bs-toggle="modal" data-bs-target="#editGuestDonationModal{{ $g->id }}">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                @endif
                                @if($canDeleteDonation)
                                <form action="{{ route('admin.donations.deleteGuest', $g->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this donation record? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action-delete">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                <i class="bi bi-person-heart fs-1 d-block mb-2 text-warning"></i>
                                No guest donations recorded.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($canEditDonation)
            @foreach($guestDonations as $g)
            <div class="modal fade" id="editGuestDonationModal{{ $g->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <form action="{{ route('admin.donations.updateGuest', $g->id) }}" method="POST">
                            @csrf
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Guest Donation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body py-3">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Donor Full Name</label>
                                    <input type="text" name="donor_name" class="form-control rounded-3" value="{{ $g->donor_name }}" required>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Email</label>
                                        <input type="email" name="email" class="form-control rounded-3" value="{{ $g->email }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Mobile</label>
                                        <input type="text" name="mobile" class="form-control rounded-3" value="{{ $g->mobile }}">
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Donation Amount ({{ $temple['currency'] }})</label>
                                        <input type="number" step="0.01" name="amount" class="form-control rounded-3" value="{{ $g->amount }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Donation Date</label>
                                        <input type="date" name="donation_date" class="form-control rounded-3" value="{{ $g->donation_date }}" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Purpose</label>
                                    <input type="text" name="purpose" class="form-control rounded-3" value="{{ $g->purpose }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Event (Optional)</label>
                                    <select name="event_id" class="form-select rounded-3">
                                        <option value="">-- General Fund --</option>
                                        @foreach($events as $event)
                                            <option value="{{ $event->event_id }}" {{ $g->event_id == $event->event_id ? 'selected' : '' }}>{{ $event->event_name }} ({{ date('d M Y', strtotime($event->event_date)) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Purpose Details / Notes</label>
                                    <input type="text" name="purpose_details" class="form-control rounded-3" value="{{ $g->purpose_details }}">
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Payment Method</label>
                                        <select name="payment_method" class="form-select rounded-3" required>
                                            @foreach(['Cash', 'UPI', 'Bank', 'Stripe'] as $method)
                                                <option value="{{ $method }}" {{ $g->payment_method === $method ? 'selected' : '' }}>{{ $method }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Payment Status</label>
                                        <select name="payment_status" class="form-select rounded-3" required>
                                            @foreach(['Paid', 'Pending', 'Cancelled', 'Failed'] as $status)
                                                <option value="{{ $status }}" {{ $g->payment_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Transaction ID / Reference</label>
                                    <input type="text" name="transaction_id" class="form-control rounded-3" value="{{ $g->transaction_id }}">
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                                <button type="submit" class="btn btn-primary text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #2a6fdb, #548ee8); border:none;">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
            @endif
        </div>

        <!-- e-Hundi Offerings Pane -->
        <div class="tab-pane fade" id="ehundi-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Offering ID</th>
                            <th>Donor Name</th>
                            <th>Contact Details</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date / Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ehundiDonations as $eh)
                        <tr>
                            <td><strong>EH{{ str_pad($eh->id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                            <td>
                                @if($eh->devotee_id)
                                    <span class="fw-semibold text-dark">{{ $eh->devotee_name }}</span>
                                    <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Registered Devotee</span>
                                @else
                                    <span class="text-muted fst-italic">Anonymous Guest</span>
                                @endif
                            </td>
                            <td>
                                @if($eh->devotee_id)
                                    <div class="small text-dark">{{ $eh->mobile ?? 'N/A' }}</div>
                                    <div class="small text-muted">{{ $eh->email ?? 'N/A' }}</div>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td><span class="fw-bold text-success">{{ $temple['currency'] }} {{ number_format($eh->amount, 2) }}</span></td>
                            <td><span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">{{ $eh->payment_status }}</span></td>
                            <td>{{ date('d M Y h:i A', strtotime($eh->created_at)) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-coin fs-1 d-block mb-2 text-warning"></i>
                                No e-Hundi offerings recorded yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- RECORD DEVOTEE DONATION MODAL -->
<div class="modal fade" id="recordDevoteeDonationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="{{ route('admin.donations.storeDevotee') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-check-fill text-warning me-2"></i>Log Devotee Donation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Devotee</label>
                        <select name="devotee_id" class="form-select rounded-3" required>
                            <option value="">-- Choose Devotee --</option>
                            @foreach($devotees as $devotee)
                                <option value="{{ $devotee->devotee_id }}">{{ $devotee->name }} ({{ $devotee->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Donation Amount ({{ $temple['currency'] }})</label>
                        <input type="number" step="0.01" name="amount" class="form-control rounded-3" placeholder="e.g. 1000.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event (Optional)</label>
                        <select name="event_id" class="form-select rounded-3">
                            <option value="">-- General Fund --</option>
                            @foreach($events as $event)
                                <option value="{{ $event->event_id }}">{{ $event->event_name }} ({{ date('d M Y', strtotime($event->event_date)) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Mode</label>
                        <select name="payment_mode" class="form-select rounded-3" required>
                            <option value="Cash" selected>Cash</option>
                            <option value="UPI">UPI</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Transaction ID / Reference (Optional)</label>
                        <input type="text" name="transaction_id" class="form-control rounded-3" placeholder="e.g. TXN98234832">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Donation Date</label>
                            <input type="date" name="donation_date" class="form-control rounded-3" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Remarks</label>
                        <input type="text" name="remarks" class="form-control rounded-3" placeholder="e.g. Donation for temple expansion">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Record Donation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RECORD GUEST DONATION MODAL -->
<div class="modal fade" id="recordGuestDonationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="{{ route('admin.donations.storeGuest') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-heart text-primary me-2"></i>Log Guest / Walk-In Donation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Donor Full Name</label>
                        <input type="text" name="donor_name" class="form-control rounded-3" placeholder="e.g. Rajesh Kumar" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email (Optional)</label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="e.g. rajesh@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mobile (Optional)</label>
                            <input type="text" name="mobile" class="form-control rounded-3" placeholder="e.g. 9876543210">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Donation Amount ({{ $temple['currency'] }})</label>
                            <input type="number" step="0.01" name="amount" class="form-control rounded-3" placeholder="e.g. 500.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Donation Date</label>
                            <input type="date" name="donation_date" class="form-control rounded-3" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Purpose</label>
                        <select name="purpose" class="form-select rounded-3" required>
                            <option value="General" selected>General Donation</option>
                            <option value="Temple Expansion">Temple Expansion</option>
                            <option value="Annadanam">Annadanam (Food Offering)</option>
                            <option value="Festival Pooja">Festival Pooja</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event (Optional)</label>
                        <select name="event_id" class="form-select rounded-3">
                            <option value="">-- General Fund --</option>
                            @foreach($events as $event)
                                <option value="{{ $event->event_id }}">{{ $event->event_name }} ({{ date('d M Y', strtotime($event->event_date)) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Purpose Details / Notes</label>
                        <input type="text" name="purpose_details" class="form-control rounded-3" placeholder="e.g. In memory of parents">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Method</label>
                        <select name="payment_method" id="guest_payment_method" class="form-select rounded-3" required>
                            <option value="Cash" selected>Cash</option>
                            <option value="UPI">UPI</option>
                            <option value="Bank">Bank Transfer / Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Transaction ID / Reference (Optional)</label>
                        <input type="text" name="transaction_id" class="form-control rounded-3" placeholder="e.g. UPI87234832">
                    </div>

                    <!-- Bank Details Sub-form (Shown only for Bank Payment Method) -->
                    <div id="bank_details_fields" class="p-3 bg-light rounded-3 mb-3" style="display:none;">
                        <h6 class="fw-bold mb-2 small text-dark"><i class="bi bi-bank me-1"></i>Bank Deposit Details</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Bank Name</label>
                                <input type="text" name="bank_name" id="bank_name" class="form-control form-control-sm rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Account No</label>
                                <input type="text" name="bank_account_no" id="bank_account_no" class="form-control form-control-sm rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">IFSC Code</label>
                                <input type="text" name="bank_ifsc" id="bank_ifsc" class="form-control form-control-sm rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Branch Name</label>
                                <input type="text" name="bank_branch" id="bank_branch" class="form-control form-control-sm rounded-3">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                    <button type="submit" class="btn btn-primary text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #2a6fdb, #548ee8); border:none;">Record Donation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('page-js')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const paymentSelect = document.getElementById('guest_payment_method');
        const bankFields = document.getElementById('bank_details_fields');

        const bankName = document.getElementById('bank_name');
        const bankAcc = document.getElementById('bank_account_no');
        const bankIfsc = document.getElementById('bank_ifsc');
        const bankBranch = document.getElementById('bank_branch');

        function toggleBankFields() {
            if (paymentSelect.value === 'Bank') {
                bankFields.style.display = 'block';
                bankName.required = true;
                bankAcc.required = true;
                bankIfsc.required = true;
                bankBranch.required = true;
            } else {
                bankFields.style.display = 'none';
                bankName.required = false;
                bankAcc.required = false;
                bankIfsc.required = false;
                bankBranch.required = false;
            }
        }

        paymentSelect.addEventListener('change', toggleBankFields);
        toggleBankFields(); // Initial check
    });
</script>
@endsection
