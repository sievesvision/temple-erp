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
    .btn-action-edit, .btn-action-delete, .btn-action-resend, .btn-action-approve, .btn-action-checkstatus {
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
    .btn-action-checkstatus {
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
    }
    .btn-action-checkstatus:hover {
        background: #8b5cf6;
        color: white;
    }
    .quick-filter-btn {
        background: #faf8f5;
        border: 1px solid #f0ece6;
        color: #7b6b5a;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 8px 18px;
        border-radius: 40px;
        transition: 0.2s;
    }
    .quick-filter-btn:hover {
        background: #f0ece6;
        color: #5a4e3e;
    }
    .quick-filter-btn.active {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        border-color: transparent;
    }
    .event-sidebar-list .list-group-item {
        border: none;
        border-radius: 14px !important;
        margin-bottom: 6px;
        color: #5a4e3e;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .event-sidebar-list .list-group-item:hover {
        background: #faf5eb;
    }
    .event-sidebar-list .list-group-item.active {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
    }
    .event-sidebar-list .list-group-item.active .badge {
        background: rgba(255,255,255,0.25) !important;
        color: white !important;
    }
    .donation-tier-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border: 1px solid #f0ece6;
        border-radius: 12px;
        margin-bottom: 8px;
        cursor: pointer;
    }
    .donation-tier-option.selected {
        border-color: #b8863a;
        background: #fdf9f2;
    }
    .option-chip {
        background: #faf5eb;
        border: 1px solid #f0ece6;
        color: #7b6b5a;
        font-size: 0.75rem;
        padding: 4px 12px;
        border-radius: 40px;
        cursor: pointer;
    }
    .option-chip:hover {
        background: #b8863a;
        color: white;
        border-color: transparent;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1><i class="bi bi-wallet2"></i>Manage Donations</h1>
        <div class="subtitle">Log, view, and audit donations received from registered devotees and guest donors</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.donations.export') }}" class="btn-add" style="background: linear-gradient(135deg, #1f9d6a, #34b380);">
            <i class="bi bi-file-earmark-excel-fill"></i> Export to Excel
        </a>
        @if($canAddDonation)
        <button class="btn-add" data-bs-toggle="modal" data-bs-target="#recordDevoteeDonationModal">
            <i class="bi bi-person-check-fill"></i> Log Devotee Donation
        </button>
        <button class="btn-add" style="background: linear-gradient(135deg, #2a6fdb, #548ee8);" data-bs-toggle="modal" data-bs-target="#recordGuestDonationModal">
            <i class="bi bi-person-heart"></i> Log Guest Donation
        </button>
        @endif
    </div>
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
                <button class="nav-link active" id="all-donations-tab" data-bs-toggle="tab" data-bs-target="#all-donations-pane" type="button" role="tab"><i class="bi bi-wallet2 text-warning me-1"></i>All Donations</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="ehundi-tab" data-bs-toggle="tab" data-bs-target="#ehundi-pane" type="button" role="tab"><i class="bi bi-coin text-warning me-1"></i>e-Hundi Offerings</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="event-summary-tab" data-bs-toggle="tab" data-bs-target="#event-summary-pane" type="button" role="tab"><i class="bi bi-calendar-event text-warning me-1"></i>By Event</button>
            </li>
        </ul>
    </div>

    <div class="tab-content p-4" id="donationTabsContent">
        <!-- All Donations Pane -->
        <div class="tab-pane fade show active" id="all-donations-pane" role="tabpanel">
            <div class="d-flex flex-wrap gap-2 mb-3" id="donationQuickFilters">
                <button type="button" class="quick-filter-btn active" data-filter="all">All</button>
                <button type="button" class="quick-filter-btn" data-filter="type:devotee">Devotee Donations</button>
                <button type="button" class="quick-filter-btn" data-filter="type:guest">Guest Donations</button>
                <button type="button" class="quick-filter-btn" data-filter="status:pending">Pending</button>
                <button type="button" class="quick-filter-btn" data-filter="status:paid">Approved</button>
                <button type="button" class="quick-filter-btn" data-filter="stripe:issues">Stripe Issues</button>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <label class="small text-muted mb-0 fw-semibold">Date</label>
                <input type="date" id="donationDateFrom" class="form-control form-control-sm rounded-3" style="width:150px;" title="From date">
                <span class="text-muted small">to</span>
                <input type="date" id="donationDateTo" class="form-control form-control-sm rounded-3" style="width:150px;" title="To date">
                <button type="button" id="donationDateClear" class="btn btn-sm btn-outline-secondary rounded-3">Clear dates</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle" id="allDonationsTable">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Amount</th>
                            <th>Event / Purpose</th>
                            <th>Payment Method</th>
                            <th>Transaction ID</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allDonations as $row)
                            @include('admin.partials.donation-row', ['row' => $row])
                        @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                <i class="bi bi-wallet2 fs-1 d-block mb-2 text-warning"></i>
                                No donations found.
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
                                    <label class="form-label fw-semibold">Donation Option / Purpose</label>
                                    <input type="text" name="purpose" class="form-control rounded-3" value="{{ $d->purpose }}" placeholder="e.g. Sponsorship for a Conch">
                                    @if($d->event_id && isset($eventOptionsByEventId[$d->event_id]) && count($eventOptionsByEventId[$d->event_id]))
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        @foreach($eventOptionsByEventId[$d->event_id] as $opt)
                                        <span class="option-chip" onclick="appendOptionChip(this, 'input[name=purpose]')">{{ $opt->label }}</span>
                                        @endforeach
                                    </div>
                                    @endif
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
                                    @if($g->event_id && isset($eventOptionsByEventId[$g->event_id]) && count($eventOptionsByEventId[$g->event_id]))
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        @foreach($eventOptionsByEventId[$g->event_id] as $opt)
                                        <span class="option-chip" onclick="appendOptionChip(this, 'input[name=purpose]')">{{ $opt->label }}</span>
                                        @endforeach
                                    </div>
                                    @endif
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

        <!-- By Event Pane -->
        <div class="tab-pane fade" id="event-summary-pane" role="tabpanel">
            <div class="row g-3">
                <!-- Collapsible event sidebar -->
                <div class="col-md-3">
                    <div class="list-group event-sidebar-list" id="eventSidebarList">
                        <button type="button" class="list-group-item list-group-item-action active" data-event-target="event-overview-pane">
                            <i class="bi bi-grid-1x2-fill me-2"></i>Overview
                        </button>
                        @foreach($eventSummary as $ev)
                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-event-target="event-detail-pane-{{ $ev->event_id }}">
                            <span>{{ $ev->event_name }}</span>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill">{{ $ev->donation_count }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>

                <!-- Overview (current summary view) or a specific event's full detail -->
                <div class="col-md-9">
                    <div class="event-content-pane" id="event-overview-pane">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Donations Received</th>
                                        <th>Paid Total</th>
                                        <th>Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($eventSummary as $ev)
                                    <tr class="event-summary-row" data-event-target="event-detail-pane-{{ $ev->event_id }}" style="cursor:pointer;">
                                        <td><span class="fw-semibold text-dark">{{ $ev->event_name }}</span></td>
                                        <td>{{ $ev->donation_count }}</td>
                                        <td><span class="fw-bold text-success">{{ $temple['currency'] }} {{ number_format($ev->paid_total, 2) }}</span> <span class="text-muted small">({{ $ev->paid_count }})</span></td>
                                        <td>
                                            @if($ev->pending_count > 0)
                                                <span class="text-warning fw-semibold">{{ $temple['currency'] }} {{ number_format($ev->pending_total, 2) }}</span> <span class="text-muted small">({{ $ev->pending_count }})</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-5">
                                            <i class="bi bi-calendar-event fs-1 d-block mb-2 text-warning"></i>
                                            No event-linked donations recorded yet.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @foreach($eventSummary as $ev)
                    <div class="event-content-pane" id="event-detail-pane-{{ $ev->event_id }}" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="fw-bold mb-0 text-dark">{{ $ev->event_name }}</h5>
                            @if($canAddDonation)
                            <div class="d-flex gap-2">
                                <button type="button" class="btn-add" style="padding: 8px 18px; font-size: 0.85rem;" onclick="openDonationModalForEvent('devotee', {{ $ev->event_id }})">
                                    <i class="bi bi-person-check-fill"></i> Add Devotee Donation
                                </button>
                                <button type="button" class="btn-add" style="padding: 8px 18px; font-size: 0.85rem; background: linear-gradient(135deg, #2a6fdb, #548ee8);" onclick="openDonationModalForEvent('guest', {{ $ev->event_id }})">
                                    <i class="bi bi-person-heart"></i> Add Guest Donation
                                </button>
                            </div>
                            @endif
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Amount</th>
                                        <th>Donation Option</th>
                                        <th>Payment Method</th>
                                        <th>Transaction ID</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($allDonations->where('event_id', $ev->event_id) as $row)
                                        @include('admin.partials.donation-row', ['row' => $row, 'purposeOverride' => $row->display_option ?: '—'])
                                    @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-4">No donations recorded for this event yet.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endforeach
                </div>
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
                        <label class="form-label fw-semibold">Event (Optional)</label>
                        <select name="event_id" id="devotee_event_id" class="form-select rounded-3 donation-event-select" data-tiers-target="devoteeEventTiers" data-manual-target="devoteeManualAmount">
                            <option value="">-- General Fund --</option>
                            @foreach($events as $event)
                                <option value="{{ $event->event_id }}">{{ $event->event_name }} ({{ date('d M Y', strtotime($event->event_date)) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="devoteeEventTiers" style="display:none;"></div>
                    <div class="mb-3" id="devoteeManualAmount">
                        <label class="form-label fw-semibold">Donation Amount ({{ $temple['currency'] }})</label>
                        <input type="number" step="0.01" name="amount" id="devotee_amount_input" class="form-control rounded-3" placeholder="e.g. 1000.00" required>
                    </div>
                    <input type="hidden" name="purpose" id="devotee_purpose_hidden">
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
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event (Optional)</label>
                        <select name="event_id" id="guest_event_id" class="form-select rounded-3">
                            <option value="">-- General Fund --</option>
                            @foreach($events as $event)
                                <option value="{{ $event->event_id }}">{{ $event->event_name }} ({{ date('d M Y', strtotime($event->event_date)) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="guestEventTiers" style="display:none;"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6" id="guestManualAmount">
                            <label class="form-label fw-semibold">Donation Amount ({{ $temple['currency'] }})</label>
                            <input type="number" step="0.01" name="amount" id="guest_amount_input" class="form-control rounded-3" placeholder="e.g. 500.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Donation Date</label>
                            <input type="date" name="donation_date" class="form-control rounded-3" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="mb-3" id="guest_purpose_wrap">
                        <label class="form-label fw-semibold">Purpose</label>
                        <select name="purpose" id="guest_purpose_select" class="form-select rounded-3" required>
                            <option value="General" selected>General Donation</option>
                            <option value="Temple Expansion">Temple Expansion</option>
                            <option value="Annadanam">Annadanam (Food Offering)</option>
                            <option value="Festival Pooja">Festival Pooja</option>
                        </select>
                    </div>
                    <input type="hidden" id="guest_purpose_tier_hidden" value="">
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
<script>
    $(function () {
        let activeDonationFilter = 'all';

        // Combined quick-filter (type/status) + date-range filter for the unified
        // donations table. Runs alongside DataTables' own built-in search box, which
        // already covers name/email/mobile since those are visible table columns.
        $.fn.dataTable.ext.search.push(function (settings, searchData, index, rowData, counter) {
            if (settings.nTable.id !== 'allDonationsTable') {
                return true;
            }

            const $row = $(settings.aoData[index].nTr);
            const type = $row.data('type');
            const status = String($row.data('status'));
            const method = String($row.data('method'));
            const date = String($row.data('date'));

            if (activeDonationFilter !== 'all') {
                const [key, value] = activeDonationFilter.split(':');
                if (key === 'type' && type !== value) return false;
                if (key === 'status' && status !== value) return false;
                if (key === 'stripe' && value === 'issues') {
                    if (method !== 'stripe' || !['pending', 'cancelled', 'failed'].includes(status)) return false;
                }
            }

            const from = $('#donationDateFrom').val();
            const to = $('#donationDateTo').val();
            if (from && date < from) return false;
            if (to && date > to) return false;

            return true;
        });

        const donationsTable = $('#allDonationsTable').DataTable({
            pageLength: 25,
            order: [],
            language: {
                emptyTable: 'No donations found.',
                search: '',
                searchPlaceholder: 'Search by name, email or mobile...'
            },
            columnDefs: [
                { orderable: false, targets: -1 }
            ]
        });

        $('#donationQuickFilters .quick-filter-btn').on('click', function () {
            $('#donationQuickFilters .quick-filter-btn').removeClass('active');
            $(this).addClass('active');
            activeDonationFilter = $(this).data('filter');
            donationsTable.draw();
        });

        $('#donationDateFrom, #donationDateTo').on('change', function () {
            donationsTable.draw();
        });

        $('#donationDateClear').on('click', function () {
            $('#donationDateFrom').val('');
            $('#donationDateTo').val('');
            donationsTable.draw();
        });
    });
</script>
<script>
    // Event-scoped donation options (tiers), keyed by event_id, for the Add Donation
    // modals — lets an admin pick from the same options a donor sees on the event's own
    // donation page instead of a generic Purpose dropdown.
    const EVENT_DONATION_OPTIONS = @json($eventDonationOptionsForJs);
    const DONATION_CURRENCY = @json($temple['currency']);

    function escapeHtmlText(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Appends an event option's label into a nearby free-text Purpose input — used by the
    // "quick option" chips on the Edit Donation modals (kept as free text there since the
    // stored value is a comma-joined string, not a fixed set of checkboxes).
    function appendOptionChip(chipEl, selector) {
        const scope = chipEl.closest('.modal-body') || document;
        const input = scope.querySelector(selector);
        if (!input) { return; }
        const label = chipEl.textContent.trim();
        const existing = input.value.split(',').map(s => s.trim()).filter(Boolean);
        if (!existing.includes(label)) {
            existing.push(label);
        }
        input.value = existing.join(', ');
    }

    // Wires an Add Donation modal's Event dropdown to render that event's configured
    // donation options as checkboxes (mirroring the public donate-form tiers UI) in place
    // of the manual Amount field whenever the selected event has any configured. Returns
    // an `apply()` function so external code can force a re-render after setting the
    // dropdown value programmatically (see openDonationModalForEvent below).
    function initEventTierPicker(config) {
        const eventSelect = document.getElementById(config.eventSelectId);
        const tiersContainer = document.getElementById(config.tiersContainerId);
        const manualWrap = document.getElementById(config.manualWrapId);
        const amountInput = document.getElementById(config.amountInputId);

        function setPurposeMode(useTiers) {
            if (config.purposeMode !== 'select') { return; }
            const selectEl = document.getElementById(config.purposeFieldId);
            const hiddenEl = document.getElementById(config.purposeHiddenId);
            const wrap = document.getElementById(config.purposeWrapId);
            if (useTiers) {
                selectEl.removeAttribute('name');
                if (wrap) { wrap.style.display = 'none'; }
                hiddenEl.name = 'purpose';
            } else {
                selectEl.setAttribute('name', 'purpose');
                if (wrap) { wrap.style.display = ''; }
                hiddenEl.removeAttribute('name');
                hiddenEl.value = '';
            }
        }

        function apply() {
            const eventId = eventSelect.value;
            const options = EVENT_DONATION_OPTIONS[eventId] || [];

            if (!options.length) {
                tiersContainer.style.display = 'none';
                tiersContainer.innerHTML = '';
                manualWrap.style.display = '';
                amountInput.required = true;
                setPurposeMode(false);
                if (config.purposeMode === 'hidden') {
                    document.getElementById(config.purposeHiddenId).value = '';
                }
                return;
            }

            manualWrap.style.display = 'none';
            amountInput.required = false;
            setPurposeMode(true);

            let html = '';
            options.forEach(function (opt, idx) {
                const hasAmount = opt.amount !== null;
                html += '<div class="donation-tier-option" data-idx="' + idx + '">'
                    + '<label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer; flex:1;">'
                    + '<input type="checkbox" class="tier-cb" data-idx="' + idx + '">'
                    + '<span><strong>' + escapeHtmlText(opt.label) + '</strong><br><span class="text-muted small">'
                    + (hasAmount ? (DONATION_CURRENCY + ' ' + opt.amount.toFixed(2) + (opt.allow_quantity ? ' each' : '')) : 'Any amount')
                    + '</span></span></label>'
                    + (opt.allow_quantity ? '<input type="number" min="1" value="1" class="form-control form-control-sm tier-qty" style="width:70px; display:none;">' : '')
                    + (!hasAmount ? '<input type="number" min="0" step="0.01" placeholder="Amount" class="form-control form-control-sm tier-free" style="width:110px;">' : '')
                    + '</div>';
            });
            html += '<div class="d-flex justify-content-between fw-bold small mb-3 mt-1"><span>Total</span><span class="tier-total-display">' + DONATION_CURRENCY + ' 0.00</span></div>';
            tiersContainer.innerHTML = html;
            tiersContainer.style.display = 'block';

            function recalc() {
                let total = 0;
                const labels = [];
                tiersContainer.querySelectorAll('.donation-tier-option').forEach(function (row) {
                    const idx = row.dataset.idx;
                    const cb = row.querySelector('.tier-cb');
                    const qtyInput = row.querySelector('.tier-qty');
                    const freeInput = row.querySelector('.tier-free');
                    if (qtyInput) { qtyInput.style.display = cb.checked ? 'inline-block' : 'none'; }
                    row.classList.toggle('selected', cb.checked);
                    if (!cb.checked) { return; }
                    const opt = options[idx];
                    let label = opt.label;
                    let amount = 0;
                    if (opt.amount !== null) {
                        const qty = (qtyInput && opt.allow_quantity) ? (parseInt(qtyInput.value, 10) || 1) : 1;
                        amount = opt.amount * qty;
                        if (opt.allow_quantity && qty > 1) { label += ' (x' + qty + ')'; }
                    } else {
                        amount = freeInput ? (parseFloat(freeInput.value) || 0) : 0;
                    }
                    if (amount > 0) {
                        total += amount;
                        labels.push(label);
                    }
                });
                amountInput.value = total.toFixed(2);
                const totalDisplay = tiersContainer.querySelector('.tier-total-display');
                if (totalDisplay) { totalDisplay.textContent = DONATION_CURRENCY + ' ' + total.toFixed(2); }
                const purposeHidden = document.getElementById(config.purposeHiddenId);
                purposeHidden.value = labels.length ? labels.join(', ').substring(0, 250) : 'Event Donation';
            }

            tiersContainer.addEventListener('change', recalc);
            tiersContainer.addEventListener('input', recalc);
            recalc();
        }

        eventSelect.addEventListener('change', apply);
        apply();
        return apply;
    }

    const applyDevoteeEventTiers = initEventTierPicker({
        eventSelectId: 'devotee_event_id',
        tiersContainerId: 'devoteeEventTiers',
        manualWrapId: 'devoteeManualAmount',
        amountInputId: 'devotee_amount_input',
        purposeMode: 'hidden',
        purposeHiddenId: 'devotee_purpose_hidden',
    });

    const applyGuestEventTiers = initEventTierPicker({
        eventSelectId: 'guest_event_id',
        tiersContainerId: 'guestEventTiers',
        manualWrapId: 'guestManualAmount',
        amountInputId: 'guest_amount_input',
        purposeMode: 'select',
        purposeFieldId: 'guest_purpose_select',
        purposeWrapId: 'guest_purpose_wrap',
        purposeHiddenId: 'guest_purpose_tier_hidden',
    });

    // Opens the Log Devotee/Guest Donation modal with a given event pre-selected — used by
    // the "Add Donation" buttons inside each event's detail pane on the By Event tab.
    function openDonationModalForEvent(type, eventId) {
        const isDevotee = type === 'devotee';
        const select = document.getElementById(isDevotee ? 'devotee_event_id' : 'guest_event_id');
        select.value = String(eventId);
        select.dispatchEvent(new Event('change'));
        const modalEl = document.getElementById(isDevotee ? 'recordDevoteeDonationModal' : 'recordGuestDonationModal');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // By Event tab: collapsible event sidebar + overview/detail pane switching.
    document.addEventListener('DOMContentLoaded', function () {
        function showEventPane(target) {
            document.querySelectorAll('.event-content-pane').forEach(function (p) { p.style.display = 'none'; });
            const pane = document.getElementById(target);
            if (pane) { pane.style.display = 'block'; }
            document.querySelectorAll('#eventSidebarList .list-group-item').forEach(function (b) { b.classList.remove('active'); });
            const sidebarBtn = document.querySelector('#eventSidebarList [data-event-target="' + target + '"]');
            if (sidebarBtn) { sidebarBtn.classList.add('active'); }
        }

        document.querySelectorAll('#eventSidebarList [data-event-target]').forEach(function (el) {
            el.addEventListener('click', function () { showEventPane(this.dataset.eventTarget); });
        });

        document.querySelectorAll('.event-summary-row[data-event-target]').forEach(function (el) {
            el.addEventListener('click', function () { showEventPane(this.dataset.eventTarget); });
        });
    });
</script>
@endsection
