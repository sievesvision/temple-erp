@extends('admin.layouts.app')

@section('title','Manage Bookings')

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

    /* Cards */
    .stat-card {
        background: white;
        border-radius: 24px;
        padding: 20px 24px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(184, 134, 58, 0.06);
        height: 100%;
    }
    .stat-card .stat-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        color: #7b6b5a;
        font-weight: 600;
    }
    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #1e1e2a;
        margin-top: 4px;
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

    /* Status Badges */
    .badge-status {
        padding: 6px 16px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: capitalize;
    }
    .badge-status.confirmed {
        background: #d1fae5;
        color: #065f46;
    }
    .badge-status.pending {
        background: #fef3c7;
        color: #92400e;
    }
    .badge-status.completed {
        background: #dbeafe;
        color: #1e40af;
    }
    .badge-status.cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-pay {
        font-size: 0.7rem;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 40px;
    }
    .badge-pay.paid {
        background: #e6f9f0;
        color: #1f9d6a;
    }
    .badge-pay.pending {
        background: #fff9e6;
        color: #f59e0b;
    }
    .badge-pay.refunded {
        background: #eef2f6;
        color: #4b5563;
    }
    .badge-pay.failed {
        background: #fef2f2;
        color: #ef4444;
    }

    /* Action Buttons */
    .btn-action {
        padding: 6px 12px;
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
    .btn-action.assign {
        background: #e0f2fe;
        color: #0369a1;
    }
    .btn-action.assign:hover {
        background: #bae6fd;
        transform: translateY(-1px);
    }
    .btn-action.reschedule {
        background: #fef3c7;
        color: #d97706;
    }
    .btn-action.reschedule:hover {
        background: #fde68a;
        transform: translateY(-1px);
    }
    .btn-action.status {
        background: #f3e8ff;
        color: #6b21a8;
    }
    .btn-action.status:hover {
        background: #e9d5ff;
        transform: translateY(-1px);
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
        background: #faf8f5;
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
    }
</style>
@endsection

@section('content')
<!-- ===== MESSAGES ===== -->
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show alert-custom mx-4 mt-4 mb-0 animate__animated animate__fadeIn" 
     style="background: #d1fae5; color: #065f46; border-left: 4px solid #059669; border-radius:16px;">
    <i class="bi bi-check-circle-fill me-2"></i>
    <strong>Success!</strong> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show alert-custom mx-4 mt-4 mb-0 animate__animated animate__fadeIn" 
     style="background: #fee2e2; color: #991b1b; border-left: 4px solid #dc3545; border-radius:16px;">
    <i class="bi bi-exclamation-circle-fill me-2"></i>
    <strong>Error!</strong> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="container-fluid px-4 py-4">

    <!-- Page Header -->
    <div class="page-header animate__animated animate__fadeIn">
        <div>
            <h1><i class="bi bi-calendar-event-fill"></i> Manage Pooja Bookings</h1>
            <div class="subtitle">
                <i class="bi bi-info-circle me-1"></i> Reschedule, override priests, and approve bookings
            </div>
        </div>
    </div>

    <!-- STATS ROW -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-number">{{ number_format($totalBookings) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Total Paid Revenue</div>
                <div class="stat-number">₹{{ number_format($totalRevenue, 2) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Membership Discounts Given</div>
                <div class="stat-number">₹{{ number_format($totalDiscount, 2) }}</div>
            </div>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="table-card mb-4 p-4 animate__animated animate__fadeIn">
        <form method="GET" action="{{ route('admin.bookings.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold">Search Devotee / Priest</label>
                    <input type="text" name="search" class="form-control rounded-pill px-3" value="{{ request('search') }}" placeholder="Search name or mobile...">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold">Booking Status</label>
                    <select name="booking_status" class="form-select rounded-pill px-3">
                        <option value="">All Statuses</option>
                        <option value="Pending" {{ request('booking_status') === 'Pending' ? 'selected' : '' }}>Pending</option>
                        <option value="Confirmed" {{ request('booking_status') === 'Confirmed' ? 'selected' : '' }}>Confirmed</option>
                        <option value="Completed" {{ request('booking_status') === 'Completed' ? 'selected' : '' }}>Completed</option>
                        <option value="Cancelled" {{ request('booking_status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label fw-bold">Pooja Mode</label>
                    <select name="booking_type" class="form-select rounded-pill px-3">
                        <option value="">All Modes</option>
                        <option value="Offline" {{ request('booking_type') === 'Offline' ? 'selected' : '' }}>Temple</option>
                        <option value="Online" {{ request('booking_type') === 'Online' ? 'selected' : '' }}>Online</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label fw-bold">Booking Date</label>
                    <input type="date" name="date" class="form-control rounded-pill px-3" value="{{ request('date') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-warning w-100 rounded-pill fw-bold">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- BOOKINGS TABLE -->
    <div class="table-card animate__animated animate__fadeInUp">
        <div class="card-header">
            <span><i class="bi bi-table me-2" style="color:#b8863a;"></i> Booking Records</span>
        </div>
        <div style="padding: 0 20px 20px 20px; overflow-x: auto;">
            <table class="table table-striped" id="bookingsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Devotee Details</th>
                        <th>Pooja Name</th>
                        <th>Schedule</th>
                        <th>Mode</th>
                        <th>Assigned Priest</th>
                        <th>Amount</th>
                        <th>Booking/Pay Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $booking)
                    <tr>
                        <td><strong>BK{{ str_pad($booking->booking_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                        <td>
                            <div class="fw-bold">{{ $booking->devotee_name }}</div>
                            <div class="small text-muted">{{ $booking->devotee_mobile }}</div>
                        </td>
                        <td>{{ $booking->pooja_name }}</td>
                        <td>
                            <div>{{ date('d M Y', strtotime($booking->booking_date)) }}</div>
                            <div class="small text-muted">{{ date('h:i A', strtotime($booking->booking_time)) }}</div>
                        </td>
                        <td>
                            @if($booking->booking_type === 'Online')
                                <span class="badge bg-info bg-opacity-10 text-info">Online</span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary">Temple</span>
                            @endif
                        </td>
                        <td>
                            <div><i class="bi bi-person-heart text-warning"></i> {{ $booking->priest_name }}</div>
                        </td>
                        <td>
                            <div class="fw-bold">₹{{ number_format($booking->total_amount, 2) }}</div>
                            <div class="small text-muted">Base: ₹{{ number_format($booking->amount) }}</div>
                        </td>
                        <td>
                            <span class="badge-status {{ strtolower($booking->booking_status) }}">{{ $booking->booking_status }}</span>
                            <div class="mt-1">
                                <span class="badge-pay {{ strtolower($booking->payment_status) }}">{{ $booking->payment_status }}</span>
                            </div>
                        </td>
                        <td>
                            <button class="btn-action assign assignBtn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#assignPriestModal"
                                    data-id="{{ $booking->booking_id }}"
                                    data-pooja-name="{{ $booking->pooja_name }}"
                                    data-date="{{ $booking->booking_date }}"
                                    data-time="{{ $booking->booking_time }}"
                                    data-current-priest="{{ $booking->priest_name }}"
                                    data-priest-id="{{ $booking->priest_id }}">
                                <i class="bi bi-person-gear"></i> Priest
                            </button>

                            <button class="btn-action reschedule rescheduleBtn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rescheduleModal"
                                    data-id="{{ $booking->booking_id }}"
                                    data-pooja-name="{{ $booking->pooja_name }}"
                                    data-date="{{ $booking->booking_date }}"
                                    data-time="{{ $booking->booking_time }}">
                                <i class="bi bi-clock-history"></i> Reschedule
                            </button>

                            <button class="btn-action status statusBtn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#statusModal"
                                    data-id="{{ $booking->booking_id }}"
                                    data-booking-status="{{ $booking->booking_status }}"
                                    data-payment-status="{{ $booking->payment_status }}">
                                <i class="bi bi-sliders"></i> Status
                            </button>

                            <a href="{{ route('devotee.bookings.receipt', $booking->booking_id) }}" class="btn-action view text-decoration-none">
                                <i class="bi bi-file-earmark-arrow-down"></i> Receipt
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No booking records found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 1. ASSIGN PRIEST OVERRIDE MODAL -->
<div class="modal fade" id="assignPriestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="assignForm" method="POST" action="">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-gear me-2 text-warning"></i> Reassign Priest</h5>
                    <button type="button" class="close-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Booking Pooja</label>
                        <input type="text" id="assign_pooja" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Assigned Priest</label>
                        <input type="text" id="assign_current" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Choose New Active Priest</label>
                        <select name="priest_id" id="assign_new_priest" class="form-select" required>
                            <option value="">Select priest</option>
                            @foreach($priests as $priest)
                                <option value="{{ $priest->priest_id }}">{{ $priest->name }} ({{ $priest->specialization }})</option>
                            @endforeach
                        </select>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-info-circle"></i> Showing active priests. System will check for capacity constraints.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Confirm Reassign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. RESCHEDULE MODAL -->
<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="rescheduleForm" method="POST" action="">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2 text-warning"></i> Reschedule Pooja</h5>
                    <button type="button" class="close-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Booking Pooja</label>
                        <input type="text" id="resched_pooja" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Date</label>
                        <input type="date" name="booking_date" id="resched_date" class="form-control" min="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Time Slot</label>
                        <select name="booking_time" id="resched_time" class="form-select" required>
                            <option value="09:00:00">09:00 AM</option>
                            <option value="10:00:00">10:00 AM</option>
                            <option value="11:00:00">11:00 AM</option>
                            <option value="12:00:00">12:00 PM</option>
                            <option value="14:00:00">02:00 PM</option>
                            <option value="15:00:00">03:00 PM</option>
                            <option value="16:00:00">04:00 PM</option>
                            <option value="17:00:00">05:00 PM</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Confirm Reschedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. UPDATE STATUS MODAL -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="statusForm" method="POST" action="">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2 text-warning"></i> Update Booking Status</h5>
                    <button type="button" class="close-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Booking Status</label>
                        <select name="booking_status" id="stat_booking" class="form-select">
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Status</label>
                        <select name="payment_status" id="stat_payment" class="form-select">
                            <option value="Pending">Pending</option>
                            <option value="Paid">Paid</option>
                            <option value="Failed">Failed</option>
                            <option value="Refunded">Refunded</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Change Remarks / Audit Note</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Describe the reason for status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('page-js')
<script>
    $(document).ready(function() {
        // Embed approved leaves
        const approvedLeaves = {!! json_encode($leaves) !!};

        // Capture all original options from the priest select dropdown
        const allPriestOptions = [];
        $('#assign_new_priest option').each(function() {
            allPriestOptions.push({
                value: $(this).val(),
                text: $(this).text()
            });
        });

        // Initialize DataTable
        $('#bookingsTable').DataTable({
            pageLength: 10,
            responsive: true,
            order: [[3, 'desc']],
            columnDefs: [
                { orderable: false, targets: 8 }
            ]
        });

        // Auto-hide alerts
        setTimeout(function() {
            $('.alert-success, .alert-danger').fadeOut('slow');
        }, 4000);

        // Populate Assign modal
        $('.assignBtn').on('click', function() {
            const data = $(this).data();
            $('#assign_pooja').val(data.poojaName);
            $('#assign_current').val(data.currentPriest);
            
            const bookingDate = data.date;
            const selectEl = $('#assign_new_priest');
            selectEl.empty();
            
            allPriestOptions.forEach(opt => {
                if (opt.value === '') {
                    selectEl.append($('<option>', { value: '', text: opt.text }));
                    return;
                }
                
                // Check if this priest is on approved leave on the booking date
                const onLeave = approvedLeaves.some(leave => {
                    return leave.priest_id == opt.value && 
                           leave.start_date <= bookingDate && 
                           leave.end_date >= bookingDate;
                });
                
                if (!onLeave) {
                    selectEl.append($('<option>', { value: opt.value, text: opt.text }));
                }
            });

            selectEl.val(data.priestId);
            $('#assignForm').attr('action', `/admin/bookings/override-priest/${data.id}`);
        });

        // Populate Reschedule modal
        $('.rescheduleBtn').on('click', function() {
            const data = $(this).data();
            $('#resched_pooja').val(data.poojaName);
            $('#resched_date').val(data.date);
            $('#resched_time').val(data.time);
            $('#rescheduleForm').attr('action', `/admin/bookings/reschedule/${data.id}`);
        });

        // Populate Status modal
        $('.statusBtn').on('click', function() {
            const data = $(this).data();
            $('#stat_booking').val(data.bookingStatus);
            $('#stat_payment').val(data.paymentStatus);
            $('#statusForm').attr('action', `/admin/bookings/status/${data.id}`);
        });
    });
</script>
@endsection
