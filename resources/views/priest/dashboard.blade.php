@extends('priest.layouts.app')

@section('title', 'Priest Dashboard')

@section('header-title')
  @if(request()->get('tab') == 'poojas')
    <i class="bi bi-calendar-check text-warning"></i> Assigned Poojas
  @elseif(request()->get('tab') == 'schedule')
    <i class="bi bi-calendar3 text-warning"></i> Schedule Calendar
  @elseif(request()->get('tab') == 'devotees')
    <i class="bi bi-people text-warning"></i> Associated Devotees
  @elseif(request()->get('tab') == 'attendance')
    <i class="bi bi-person-check text-warning"></i> My Attendance
  @elseif(request()->get('tab') == 'leaves')
    <i class="bi bi-calendar-x text-warning"></i> Leave Requests
  @elseif(request()->get('tab') == 'earnings')
    <i class="bi bi-currency-rupee text-warning"></i> My Earnings & Wallet
  @elseif(request()->get('tab') == 'profile')
    <i class="bi bi-person-circle text-warning"></i> Profile & Bank Details
  @elseif(request()->get('tab') == 'notifications')
    <i class="bi bi-bell text-warning"></i> Notifications
  @else
    <i class="bi bi-speedometer2 text-warning"></i> Priest Dashboard
  @endif
@endsection

@section('page-css')
<style>
  .stat-card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    border: 1px solid rgba(184, 134, 58, 0.08);
    box-shadow: 0 4px 20px rgba(0,0,0,0.01);
  }
  .stat-card .stat-label {
    font-size: 0.85rem;
    text-transform: uppercase;
    color: #7b6b5a;
    font-weight: 600;
  }
  .stat-card .stat-number {
    font-size: 2rem;
    font-weight: 700;
    margin-top: 4px;
    color: #1e1e2a;
  }
  .stat-card .icon-box {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: #faf6f0;
    color: #b8863a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
  }
  .table-wrap {
    background: white;
    border-radius: 20px;
    border: 1px solid rgba(184, 134, 58, 0.08);
    overflow: hidden;
    margin-bottom: 24px;
  }
  .table-wrap .card-header {
    background: transparent;
    border-bottom: 1px solid #f0ece6;
    padding: 16px 24px;
    font-weight: 600;
    color: #2d1f0e;
  }
  .table-wrap .table { margin: 0; }
  .table-wrap .table th {
    font-weight: 600;
    color: #7b6b5a;
    padding: 12px 24px;
    font-size: 0.8rem;
    text-transform: uppercase;
  }
  .table-wrap .table td {
    padding: 12px 24px;
    border-bottom: 1px solid #f8f4f0;
  }
</style>
@endsection

@section('content')
  @if(request()->get('tab') == 'poojas')
    <!-- ASSIGNED POOJAS -->
    <div class="row g-4">
      <div class="col-12">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-clock-history text-warning me-2"></i> Today's Assigned Poojas</div>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Pooja Name</th>
                  <th>Scheduled Time</th>
                  <th>Devotee Name</th>
                  <th>Devotee Mobile</th>
                  <th>Mode</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($todayBookings as $booking)
                <tr>
                  <td><strong>BK{{ str_pad($booking->booking_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                  <td>{{ $booking->pooja_name }}</td>
                  <td class="fw-bold text-warning">{{ date('h:i A', strtotime($booking->booking_time)) }}</td>
                  <td>{{ $booking->devotee_name }}</td>
                  <td>{{ $booking->devotee_mobile }}</td>
                  <td><span class="badge bg-{{ $booking->booking_type === 'Online' ? 'info' : 'secondary' }}">{{ $booking->booking_type }}</span></td>
                  <td>
                    <span class="badge bg-{{ $booking->booking_status === 'Completed' ? 'success' : ($booking->booking_status === 'Cancelled' ? 'danger' : 'warning') }} text-dark">
                      {{ $booking->booking_status }}
                    </span>
                    @if(!in_array($booking->booking_status, ['Completed', 'Cancelled']))
                    <form action="{{ route('priest.pooja.complete', $booking->booking_id) }}" method="POST" class="d-inline ms-2" onsubmit="return confirm('Are you sure you want to mark this pooja as Completed? This will credit 25% reward to your wallet and cannot be undone.')">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold" style="font-size: 0.75rem;">
                        <i class="bi bi-check-circle"></i> Pooja Completed
                      </button>
                    </form>
                    @endif
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">No poojas scheduled for today.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-calendar3 text-warning me-2"></i> Upcoming Assigned Poojas</div>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Pooja Name</th>
                  <th>Scheduled Date & Time</th>
                  <th>Devotee Name</th>
                  <th>Devotee Mobile</th>
                  <th>Mode</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($upcomingBookings as $booking)
                <tr>
                  <td><strong>BK{{ str_pad($booking->booking_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                  <td>{{ $booking->pooja_name }}</td>
                  <td>
                    <div class="fw-semibold">{{ date('d M Y', strtotime($booking->booking_date)) }}</div>
                    <div class="small text-muted">{{ date('h:i A', strtotime($booking->booking_time)) }}</div>
                  </td>
                  <td>{{ $booking->devotee_name }}</td>
                  <td>{{ $booking->devotee_mobile }}</td>
                  <td><span class="badge bg-{{ $booking->booking_type === 'Online' ? 'info' : 'secondary' }}">{{ $booking->booking_type }}</span></td>
                  <td>
                    <span class="badge bg-{{ $booking->booking_status === 'Completed' ? 'success' : ($booking->booking_status === 'Cancelled' ? 'danger' : 'warning') }} text-dark">
                      {{ $booking->booking_status }}
                    </span>
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">No upcoming bookings assigned.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'schedule')
    <!-- SCHEDULE CALENDAR -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-calendar3 text-warning me-2"></i>My Monthly Schedule List</h5>
      <p class="text-muted">Below is your upcoming schedule compiled sequentially.</p>
      <div class="list-group list-group-flush">
        @php
          $allSchedules = collect($todayBookings)->merge($upcomingBookings)->sortBy('booking_date');
        @endphp
        @forelse($allSchedules as $sched)
          <div class="list-group-item py-3 px-0 border-0 border-bottom d-flex align-items-center justify-content-between">
            <div>
              <h6 class="mb-1 fw-bold">{{ $sched->pooja_name }}</h6>
              <p class="mb-0 text-muted small"><i class="bi bi-person me-1"></i> Devotee: {{ $sched->devotee_name }} | <i class="bi bi-telephone me-1"></i> Mobile: {{ $sched->devotee_mobile }}</p>
            </div>
            <div class="text-end">
              <span class="badge bg-warning text-dark mb-1 d-block">{{ date('d M Y', strtotime($sched->booking_date)) }}</span>
              <span class="small text-muted fw-semibold">{{ date('h:i A', strtotime($sched->booking_time)) }}</span>
            </div>
          </div>
        @empty
          <div class="text-center py-4 text-muted">No schedules found.</div>
        @endforelse
      </div>
    </div>

  @elseif(request()->get('tab') == 'devotees')
    <!-- ASSOCIATED DEVOTEES -->
    <div class="table-wrap">
      <div class="card-header"><i class="bi bi-people-fill text-warning me-2"></i> Associated Devotees</div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Devotee Name</th>
              <th>Mobile</th>
              <th>Pooja Name</th>
              <th>Date Assigned</th>
            </tr>
          </thead>
          <tbody>
            @php
              $mergedBookings = collect($todayBookings)->merge($upcomingBookings);
            @endphp
            @forelse($mergedBookings as $sched)
            <tr>
              <td><strong>{{ $sched->devotee_name }}</strong></td>
              <td>{{ $sched->devotee_mobile }}</td>
              <td>{{ $sched->pooja_name }}</td>
              <td>{{ date('d M Y', strtotime($sched->booking_date)) }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-4">No associated devotee records found.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  @elseif(request()->get('tab') == 'attendance')
    <!-- ATTENDANCE -->
    <div class="row g-4">
      <div class="col-lg-8">
        <!-- Today's Attendance Session Summary -->
        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-warning border-3 mb-4" style="background: white;">
          <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-warning me-2"></i>Today's Session Summary</h5>
          
          <div class="row g-3">
            <div class="col-sm-6">
              <div class="p-3 bg-light rounded-3">
                <div class="small text-muted mb-1">Check-in Time</div>
                <div class="fw-bold text-dark fs-5">{{ $checkinTime }}</div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="p-3 bg-light rounded-3">
                <div class="small text-muted mb-1">Check-out Time</div>
                <div class="fw-bold text-dark fs-5">{{ $checkoutTime }}</div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="p-3 bg-light rounded-3">
                <div class="small text-muted mb-1">Last Online Time</div>
                <div class="fw-bold text-success fs-5">{{ $lastOnlineTime }}</div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="p-3 bg-light rounded-3">
                <div class="small text-muted mb-1">Last Offline Time</div>
                <div class="fw-bold text-muted fs-5">{{ $lastOfflineTime }}</div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="p-3 bg-light rounded-3">
                <div class="small text-muted mb-1">Total Online Sessions</div>
                <div class="fw-bold text-primary fs-5">{{ $totalOnlineSessions }}</div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="p-3 bg-light rounded-3">
                <div class="small text-muted mb-1">Worked Today</div>
                <div class="fw-bold text-warning fs-5" id="attendanceWorkedDisplay">{{ $workedHoursToday }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-calendar-check text-warning me-2"></i> Attendance Log</div>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Check In</th>
                  <th>Check Out</th>
                  <th>Hours Worked</th>
                </tr>
              </thead>
              <tbody>
                @php
                  $attendances = DB::table('attendances')
                      ->where('user_id', $user->id)
                      ->whereNotNull('check_in_time')
                      ->orderBy('date', 'desc')
                      ->get();
                @endphp
                @forelse($attendances as $att)
                <tr>
                  <td>{{ date('d M Y', strtotime($att->date)) }}</td>
                  <td>
                    <span class="badge bg-{{ $att->check_out_time ? 'danger' : 'success' }}">
                      {{ $att->check_out_time ? 'Offline (Checked Out)' : 'Checked In' }}
                    </span>
                  </td>
                  <td>{{ $att->check_in_time ? date('h:i A', strtotime($att->check_in_time)) : 'N/A' }}</td>
                  <td>{{ $att->check_out_time ? date('h:i A', strtotime($att->check_out_time)) : 'N/A' }}</td>
                  <td>{{ round($att->worked_minutes / 60, 2) }} hrs</td>
                </tr>
                @empty
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">No attendance history found.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <!-- Live Shift Timer -->
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center mb-4" style="background: linear-gradient(135deg, #2d1f0e, #1e1e2a); color: white;">
          <h6 class="text-warning text-uppercase fw-bold mb-2">Worked Today</h6>
          <h2 class="fw-bold mb-2" id="live-shift-timer">{{ $workedHoursToday }}</h2>
          <div class="small text-white-50">
            @if($isOnline)
              <span class="badge bg-success"><i class="bi bi-play-fill me-1"></i> Running (Online)</span>
            @else
              <span class="badge bg-secondary"><i class="bi bi-pause-fill me-1"></i> Paused (Offline)</span>
            @endif
          </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-warning border-3 mb-4" style="background: white;">
          <h5 class="fw-bold mb-3"><i class="bi bi-fingerprint text-warning me-2"></i>Mark Attendance</h5>
          
          @if(!$hasCheckedIn)
            <form action="{{ route('priest.attendance.present') }}" method="POST">
              @csrf
              <button type="submit" class="btn btn-warning w-100 rounded-pill fw-semibold mb-2" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none; color: white;">Present Today</button>
            </form>
          @elseif($hasCheckedIn && !$hasCheckedOut)
            <div class="alert alert-success border-0 rounded-3 py-2 px-3 small mb-3" style="background: #e6faf0; color: #0a5c36;">
              <i class="bi bi-check-circle-fill me-1"></i> Checked-In today at {{ $checkinTime }}
            </div>
            <form action="{{ route('priest.attendance.end') }}" method="POST" onsubmit="return confirm('Are you sure you want to end today\'s work? This will disable your Online switch for today.')">
              @csrf
              <button type="submit" class="btn btn-danger w-100 rounded-pill fw-semibold mb-2">End Today's Work</button>
            </form>
          @else
            <div class="alert alert-secondary border-0 rounded-3 py-2 px-3 small mb-0" style="background: #f0ece6; color: #5a4e3e;">
              <i class="bi bi-check-circle-fill me-1"></i> Checked-In today: <strong>{{ $checkinTime }}</strong><br>
              <i class="bi bi-x-circle-fill me-1"></i> Checked-Out today: <strong>{{ $checkoutTime }}</strong>
            </div>
          @endif
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-warning border-3" style="background: white;">
          <h5 class="fw-bold mb-3"><i class="bi bi-broadcast text-warning me-2"></i>Duty Status</h5>
          <p class="text-muted small">Toggling status is controlled in the topbar switch.</p>
          <div class="d-flex align-items-center mt-3">
              <span class="fw-semibold">Current State:</span>
              <span class="badge bg-{{ $isOnline ? 'success' : 'secondary' }} ms-2 fs-6">{{ $isOnline ? 'Online' : 'Offline' }}</span>
          </div>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'leaves')
    <!-- LEAVE REQUESTS -->
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-calendar-x text-warning me-2"></i> Leave History</div>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Dates</th>
                  <th>Reason</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($leaveRequests as $req)
                <tr>
                  <td>
                    <div class="fw-bold text-dark">{{ date('d M Y', strtotime($req->start_date)) }} to {{ date('d M Y', strtotime($req->end_date)) }}</div>
                  </td>
                  <td>{{ $req->reason }}</td>
                  <td>
                    <span class="badge rounded-pill bg-{{ $req->status === 'Approved' ? 'success' : ($req->status === 'Rejected' ? 'danger' : 'warning') }} bg-opacity-10 text-{{ $req->status === 'Approved' ? 'success' : ($req->status === 'Rejected' ? 'danger' : 'warning') }} px-3 py-2">
                      {{ $req->status }}
                    </span>
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="3" class="text-center text-muted py-4">No recent leave requests found.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-warning border-3" style="background: white;">
          <h5 class="fw-bold mb-3"><i class="bi bi-calendar-x-fill text-warning me-2"></i>Request Leave</h5>
          
          <div class="alert alert-warning py-2 small border-0 mb-3" style="background: #faf2e6; color: #856404;">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Leave request should be submitted 15 days early. You can only choose dates starting from 15 days after today.
          </div>
          
          <form action="{{ route('priest.leave.request') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold">Start Date</label>
              <input type="date" name="start_date" class="form-control rounded-3" min="{{ date('Y-m-d', strtotime('+15 days')) }}" required id="leave_start_date">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">End Date</label>
              <input type="date" name="end_date" class="form-control rounded-3" min="{{ date('Y-m-d', strtotime('+15 days')) }}" required id="leave_end_date">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Reason</label>
              <textarea name="reason" class="form-control rounded-3" rows="3" required placeholder="Describe your reason..."></textarea>
            </div>
            <button type="submit" class="btn btn-warning w-100 rounded-pill fw-semibold">Submit Request</button>
          </form>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'wallet')
    <!-- WALLET -->
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-wallet2 text-warning me-2"></i> Wallet Transactions</div>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Transaction ID</th>
                  <th>Amount</th>
                  <th>Type</th>
                  <th>Remarks</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                @forelse($walletTxns as $txn)
                <tr>
                  <td><strong>#TXN{{ $txn->transaction_id }}</strong></td>
                  <td class="fw-bold text-{{ $txn->transaction_type === 'Credit' ? 'success' : 'danger' }}">
                    {{ $txn->transaction_type === 'Credit' ? '+' : '-' }} ₹{{ number_format($txn->amount, 2) }}
                  </td>
                  <td><span class="badge bg-{{ $txn->transaction_type === 'Credit' ? 'success' : 'danger' }}">{{ $txn->transaction_type }}</span></td>
                  <td>{{ $txn->remarks }}</td>
                  <td>{{ date('d M Y h:i A', strtotime($txn->created_at)) }}</td>
                </tr>
                @empty
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">No transactions found.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center mb-4" style="background: white;">
          <h5 class="fw-bold mb-2">Wallet Balance</h5>
          <h2 class="fw-bold text-warning mb-3">₹{{ number_format($priest->wallet_balance ?? 0, 2) }}</h2>
          <p class="text-muted small mb-0">Earnings accumulated from completed booking commissions and deductions from penalty hours are adjusted here. Balance clears to 0 upon monthly salary payouts.</p>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-warning border-3 text-start mb-4" style="background: white;">
          <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-warning me-2"></i>Today's Status Timing</h5>
          <div class="mb-3">
            <span class="text-muted small d-block">Hours logged today:</span>
            <strong style="font-size: 1.4rem;">{{ $onlineHoursToday }} / 10.00 hrs</strong>
          </div>
          @if($onlineHoursToday < 10)
            <div class="alert alert-danger border-0 small py-2 px-3 mb-0" style="background: #fdf2f2; color: #9b1c1c;">
              <i class="bi bi-exclamation-octagon-fill me-1"></i> Under 10 hours limit. Penalty: <strong>₹{{ $penaltyToday }}</strong> (₹100/hour penalty).
            </div>
          @else
            <div class="alert alert-success border-0 small py-2 px-3 mb-0" style="background: #f3faf7; color: #03543f;">
              <i class="bi bi-check-circle-fill me-1"></i> Target hours met! No penalty today.
            </div>
          @endif
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'salary')
    <!-- SALARY -->
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-clock-history text-warning me-2"></i> Payout History</div>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Month</th>
                  <th>Base Salary</th>
                  <th>Wallet Adj.</th>
                  <th>Net Paid</th>
                  <th>Payment Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($salaryPayouts as $payout)
                <tr>
                  <td><strong>{{ date('F Y', strtotime($payout->salary_month . '-01')) }}</strong></td>
                  <td>₹{{ number_format($payout->base_salary, 2) }}</td>
                  <td class="text-{{ $payout->wallet_amount >= 0 ? 'success' : 'danger' }}">
                    {{ $payout->wallet_amount >= 0 ? '+' : '' }}₹{{ number_format($payout->wallet_amount, 2) }}
                  </td>
                  <td class="fw-bold">₹{{ number_format($payout->total_paid, 2) }}</td>
                  <td>{{ $payout->payment_date ? date('d M Y', strtotime($payout->payment_date)) : 'N/A' }}</td>
                  <td><span class="badge bg-success">{{ $payout->payment_status }}</span></td>
                </tr>
                @empty
                <tr>
                  <td colspan="6" class="text-center text-muted py-4">No payout history found.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-warning border-3 mb-4" style="background: white;">
          <h5 class="fw-bold mb-3"><i class="bi bi-cash-stack text-warning me-2"></i>Salary Breakdown</h5>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Monthly Salary:</span>
            <span class="fw-semibold">₹{{ number_format($priest->monthly_salary, 2) }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Wallet Balance:</span>
            <span class="fw-semibold text-{{ ($priest->wallet_balance ?? 0) >= 0 ? 'success' : 'danger' }}">
              {{ ($priest->wallet_balance ?? 0) >= 0 ? '+' : '' }}₹{{ number_format($priest->wallet_balance ?? 0, 2) }}
            </span>
          </div>
          <hr>
          <div class="d-flex justify-content-between mb-3">
            <span class="fw-bold">Net Payout (Est.):</span>
            <span class="fw-bold text-warning h4 mb-0">₹{{ number_format($priest->monthly_salary + ($priest->wallet_balance ?? 0), 2) }}</span>
          </div>
          <p class="text-muted small mb-0">Monthly payouts are approved at the end of the month by the Administrator/Accountant. The current month's wallet balance is adjusted into the final salary.</p>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-warning border-3 mb-4" style="background: white;">
          <h5 class="fw-bold mb-3"><i class="bi bi-bank text-warning me-2"></i>Bank Details</h5>
          <div class="mb-2">
            <span class="text-muted small d-block">Account Holder Name:</span>
            <strong class="text-dark">{{ $priest->account_holder_name ?? 'N/A' }}</strong>
          </div>
          <div class="mb-2">
            <span class="text-muted small d-block">Account Number:</span>
            <strong class="text-dark">{{ $priest->account_number ?? 'N/A' }}</strong>
          </div>
          <div class="mb-2">
            <span class="text-muted small d-block">Bank Name:</span>
            <strong class="text-dark">{{ $priest->bank_name ?? 'N/A' }}</strong>
          </div>
          <div class="mb-2">
            <span class="text-muted small d-block">IFSC Code:</span>
            <strong class="text-dark">{{ $priest->ifsc_code ?? 'N/A' }}</strong>
          </div>
          <div class="mb-0">
            <span class="text-muted small d-block">Branch:</span>
            <strong class="text-dark">{{ $priest->branch_name ?? 'N/A' }}</strong>
          </div>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'profile')
    <!-- PROFILE & BANK DETAILS -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-person-circle text-warning me-2"></i>My Profile & Bank Details</h5>
      <form action="{{ route('profile.update') }}" method="POST">
        @csrf
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Full Name</label>
            <input type="text" name="name" class="form-control rounded-3" value="{{ $user->name }}" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" class="form-control rounded-3" value="{{ $user->email }}" disabled>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Mobile Number</label>
            <input type="text" name="mobile" class="form-control rounded-3" value="{{ $user->mobile }}" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Gender</label>
            <select name="gender" class="form-select rounded-3">
              <option value="">Select Gender</option>
              <option value="Male" {{ ($priest && $priest->gender == 'Male') ? 'selected' : '' }}>Male</option>
              <option value="Female" {{ ($priest && $priest->gender == 'Female') ? 'selected' : '' }}>Female</option>
              <option value="Other" {{ ($priest && $priest->gender == 'Other') ? 'selected' : '' }}>Other</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Date of Birth</label>
            <input type="date" name="dob" class="form-control rounded-3" value="{{ $priest->dob ?? '' }}">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Specialization</label>
            <input type="text" name="specialization" class="form-control rounded-3" value="{{ $priest->specialization ?? '' }}">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Emergency Contact</label>
            <input type="text" name="emergency_contact" class="form-control rounded-3" value="{{ $priest->emergency_contact ?? '' }}">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Joining Date</label>
            <input type="date" class="form-control rounded-3" value="{{ $priest->joining_date ?? '' }}" disabled>
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Address</label>
            <textarea name="address" class="form-control rounded-3" rows="3">{{ $priest->address ?? '' }}</textarea>
          </div>
          
          <h5 class="fw-bold mt-4 mb-2"><i class="bi bi-bank text-warning me-2"></i>Bank Account Information</h5>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Holder Name</label>
            <input type="text" name="account_holder_name" class="form-control rounded-3" value="{{ $priest->account_holder_name ?? '' }}">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Number</label>
            <input type="text" name="account_number" class="form-control rounded-3" value="{{ $priest->account_number ?? '' }}">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Bank Name</label>
            <input type="text" name="bank_name" class="form-control rounded-3" value="{{ $priest->bank_name ?? '' }}">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">IFSC Code</label>
            <input type="text" name="ifsc_code" class="form-control rounded-3" value="{{ $priest->ifsc_code ?? '' }}">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Branch Name</label>
            <input type="text" name="branch_name" class="form-control rounded-3" value="{{ $priest->branch_name ?? '' }}">
          </div>
        </div>
        <button type="submit" class="btn btn-warning rounded-pill px-5 fw-semibold mt-4">Save Changes</button>
      </form>
    </div>

  @elseif(request()->get('tab') == 'notifications')
    <!-- NOTIFICATIONS -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-bell text-warning me-2"></i>Duty Notifications</h5>
      
      @php
        $statusLogs = DB::table('booking_status_logs')
            ->join('pooja_bookings', 'booking_status_logs.booking_id', '=', 'pooja_bookings.booking_id')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->where('pooja_bookings.priest_id', $priest->priest_id)
            ->select('booking_status_logs.*', 'poojas.pooja_name')
            ->orderBy('booking_status_logs.created_at', 'desc')
            ->get();
      @endphp

      <div class="list-group list-group-flush">
        @forelse($statusLogs as $log)
          <div class="list-group-item py-3 px-0 border-0 border-bottom">
            <div class="d-flex w-100 justify-content-between">
              <h6 class="mb-1 fw-bold">Pooja Assigned Status Shift</h6>
              <small class="text-muted">{{ date('M d, Y h:i A', strtotime($log->created_at)) }}</small>
            </div>
            <p class="mb-1 text-muted small">
              Pooja assignment <strong>{{ $log->pooja_name }}</strong> (Booking ID #{{ $log->booking_id }}) changed to status 
              <span class="badge bg-warning bg-opacity-20 text-warning">{{ $log->status_to }}</span>.
            </p>
          </div>
        @empty
          <div class="text-center py-4 text-muted">
            No duty notifications found.
          </div>
        @endforelse
      </div>
    </div>

  @else
    <!-- DEFAULT DASHBOARD -->
    <div class="row g-4 mb-4">
      <div class="col-md-3 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Today's Poojas</div>
            <div class="stat-number">{{ $todayCount }}</div>
          </div>
          <div class="icon-box"><i class="bi bi-calendar2-event"></i></div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">This Month's Poojas</div>
            <div class="stat-number">{{ $monthlyCount }}</div>
          </div>
          <div class="icon-box"><i class="bi bi-calendar3"></i></div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Total Earnings</div>
            <div class="stat-number">₹{{ number_format($totalEarnings) }}</div>
          </div>
          <div class="icon-box"><i class="bi bi-wallet2"></i></div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Wallet Balance</div>
            <div class="stat-number">₹{{ number_format($priest->wallet_balance ?? 0) }}</div>
          </div>
          <div class="icon-box"><i class="bi bi-coin"></i></div>
        </div>
      </div>
    </div>

    <!-- TODAY'S POOJAS TABLE -->
    <div class="table-wrap">
      <div class="card-header"><i class="bi bi-clock-history text-warning me-2"></i> Today's Schedule</div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Pooja Name</th>
              <th>Scheduled Time</th>
              <th>Devotee Name</th>
              <th>Devotee Mobile</th>
              <th>Mode</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($todayBookings as $booking)
            <tr>
              <td><strong>BK{{ str_pad($booking->booking_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
              <td>{{ $booking->pooja_name }}</td>
              <td class="fw-bold text-warning">{{ date('h:i A', strtotime($booking->booking_time)) }}</td>
              <td>{{ $booking->devotee_name }}</td>
              <td>{{ $booking->devotee_mobile }}</td>
              <td><span class="badge bg-{{ $booking->booking_type === 'Online' ? 'info' : 'secondary' }}">{{ $booking->booking_type }}</span></td>
              <td>
                <span class="badge bg-{{ $booking->booking_status === 'Completed' ? 'success' : ($booking->booking_status === 'Cancelled' ? 'danger' : 'warning') }} text-dark">
                  {{ $booking->booking_status }}
                </span>
                @if(!in_array($booking->booking_status, ['Completed', 'Cancelled']))
                <form action="{{ route('priest.pooja.complete', $booking->booking_id) }}" method="POST" class="d-inline ms-2" onsubmit="return confirm('Are you sure you want to mark this pooja as Completed? This will credit 25% reward to your wallet and cannot be undone.')">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold" style="font-size: 0.75rem;">
                    <i class="bi bi-check-circle"></i> Pooja Completed
                  </button>
                </form>
                @endif
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">No poojas scheduled for today.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endsection

@section('page-js')
<script>
  $(document).ready(function() {
    // Live shift timer
    const timerEl = document.getElementById('live-shift-timer');
    if (timerEl) {
      const isOnline = @json($isOnline);
      const lastOnlineTimeMs = @json($lastOnlineTimeMs);
      const totalWorkedMinutes = @json($totalWorkedMinutes);
      
      // Calculate previous closed session minutes
      let baseMinutes = totalWorkedMinutes;
      if (isOnline && lastOnlineTimeMs) {
          const activeSecs = Math.max(0, Math.floor((Date.now() - lastOnlineTimeMs) / 1000));
          const activeMins = Math.round(activeSecs / 60);
          baseMinutes = Math.max(0, totalWorkedMinutes - activeMins);
      }
      
      function updateTimer() {
          let totalSeconds = baseMinutes * 60;
          if (isOnline && lastOnlineTimeMs) {
              const activeSeconds = Math.max(0, Math.floor((Date.now() - lastOnlineTimeMs) / 1000));
              totalSeconds += activeSeconds;
          }
          
          const hrs = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
          const mins = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
          const secs = String(totalSeconds % 60).padStart(2, '0');
          timerEl.textContent = `${hrs}:${mins}:${secs}`;
          
          // Also update the summary text display
          const displayEl = document.getElementById('attendanceWorkedDisplay');
          if (displayEl) {
              displayEl.textContent = `${hrs} Hours ${mins} Minutes`;
          }
      }
      
      updateTimer();
      if (isOnline) {
          setInterval(updateTimer, 1000);
      }
    }



    // Leave start/end date logical constraints
    $('#leave_start_date').on('change', function() {
      let startDateVal = $(this).val();
      if (startDateVal) {
        $('#leave_end_date').attr('min', startDateVal);
      }
    });
  });
</script>
@endsection