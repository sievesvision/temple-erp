@extends('trustee.layouts.app')

@section('title', 'Trustee Dashboard')

@section('header-title')
  @if(request()->get('tab') == 'performance')
    <i class="bi bi-graph-up-arrow text-warning"></i> Temple Performance
  @elseif(request()->get('tab') == 'revenue')
    <i class="bi bi-coin text-warning"></i> Revenue Overview
  @elseif(request()->get('tab') == 'reports')
    <i class="bi bi-file-earmark-bar-graph text-warning"></i> Audit Reports
  @elseif(request()->get('tab') == 'events')
    <i class="bi bi-stars text-warning"></i> Temple Events
  @elseif(request()->get('tab') == 'today-poojas')
    <i class="bi bi-calendar-day text-warning"></i> Today's Assigned Poojas
  @elseif(request()->get('tab') == 'upcoming-poojas')
    <i class="bi bi-calendar-week text-warning"></i> Upcoming Pooja Bookings
  @elseif(request()->get('tab') == 'pooja-calendar')
    <i class="bi bi-calendar3 text-warning"></i> Pooja Calendar View
  @elseif(request()->get('tab') == 'meetings')
    <i class="bi bi-people-fill text-warning"></i> Board Meetings
  @elseif(request()->get('tab') == 'profile')
    <i class="bi bi-person-circle text-warning"></i> My Profile
  @else
    <i class="bi bi-speedometer2 text-warning"></i> Trustee Dashboard
  @endif
@endsection

@section('page-css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
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
  .stat-icon {
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
  @if(request()->get('tab') == 'performance')
    <!-- PERFORMANCE TAB -->
    <div class="row g-4">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
          <h5 class="fw-bold mb-3">Key Performance Metrics</h5>
          <canvas id="performanceChart" style="max-height: 250px;"></canvas>
          <div class="mt-3">
            <p class="text-muted small">Daily pooja bookings workload and devotee footfall have increased by <strong>12.4%</strong> this month.</p>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="table-wrap">
          <div class="card-header">Auditing Action Logs</div>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Pooja Name</th>
                  <th>Status From</th>
                  <th>Status To</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                @forelse($auditLogs as $log)
                <tr>
                  <td><strong>#BK{{ $log->booking_id }}</strong></td>
                  <td>{{ $log->pooja_name }}</td>
                  <td><span class="badge bg-secondary">{{ $log->status_from ?? 'Pending' }}</span></td>
                  <td><span class="badge bg-warning text-dark">{{ $log->status_to }}</span></td>
                  <td>{{ date('d M Y', strtotime($log->created_at)) }}</td>
                </tr>
                @empty
                <tr>
                  <td colspan="5" class="text-center text-muted py-3">No audits recorded.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'revenue')
    <!-- REVENUE OVERVIEW -->
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-coin text-warning me-2"></i> Recent Inflow (Donations & Bookings)</div>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Donor/Devotee</th>
                  <th>Amount</th>
                  <th>Source</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                @foreach($recentDonations as $don)
                <tr>
                  <td><strong>{{ $don->donor_name ?? 'Anonymous' }}</strong></td>
                  <td class="text-success fw-bold">+ ₹{{ number_format($don->amount) }}</td>
                  <td><span class="badge bg-success bg-opacity-10 text-success">Donation</span></td>
                  <td>{{ date('d M Y', strtotime($don->donation_date)) }}</td>
                </tr>
                @endforeach
                @foreach($recentBookings as $bk)
                <tr>
                  <td><strong>Pooja Booking #{{ $bk->booking_id }}</strong></td>
                  <td class="text-success fw-bold">+ ₹{{ number_format($bk->total_amount) }}</td>
                  <td><span class="badge bg-info bg-opacity-10 text-info">Pooja Booking</span></td>
                  <td>{{ date('d M Y', strtotime($bk->created_at)) }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center" style="background: white;">
          <h5 class="fw-bold mb-2">Total Treasury Revenue</h5>
          <h2 class="fw-bold text-success mb-3">₹{{ number_format($totalRevenue, 2) }}</h2>
          <p class="text-muted small">Aggregated revenue from all paid booking receipts and donations.</p>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'reports')
    <!-- AUDIT REPORTS -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-file-earmark-bar-graph text-warning me-2"></i>Governance Financial Reports</h5>
      <p class="text-muted">Generate audit sheets and financial logs for board members.</p>
      <div class="row g-3">
        <div class="col-md-4">
          <button class="btn btn-warning w-100 rounded-pill py-3 fw-semibold" onclick="alert('Downloading Annual Audit Report...')">
            <i class="bi bi-file-pdf me-2"></i> Annual Audit FY 2025-26
          </button>
        </div>
        <div class="col-md-4">
          <button class="btn btn-outline-warning w-100 rounded-pill py-3 fw-semibold" onclick="alert('Downloading Monthly Statement...')">
            <i class="bi bi-file-earmark-spreadsheet me-2"></i> Monthly Statement
          </button>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'events')
    <!-- EVENTS -->
    <div class="table-wrap">
      <div class="card-header"><i class="bi bi-stars text-warning me-2"></i> Temple Events Schedule</div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Event Name</th>
              <th>Description</th>
              <th>Date</th>
              <th>Timing</th>
              <th>Location</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($upcomingEvents as $event)
            <tr>
              <td><strong>{{ $event->event_name }}</strong></td>
              <td><p class="small mb-0 text-muted">{{ $event->description }}</p></td>
              <td>{{ date('d M Y', strtotime($event->event_date)) }}</td>
              <td>{{ date('h:i A', strtotime($event->start_time)) }} - {{ date('h:i A', strtotime($event->end_time)) }}</td>
              <td>{{ $event->location }}</td>
              <td><span class="badge bg-warning text-dark">{{ $event->status }}</span></td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No events found.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  @elseif(request()->get('tab') == 'today-poojas')
    <!-- TODAY'S POOJAS -->
    <div class="table-wrap">
      <div class="card-header"><i class="bi bi-calendar-day text-warning me-2"></i> Today's Assigned Poojas</div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Pooja Name</th>
              <th>Time</th>
              <th>Devotee</th>
              <th>Priest</th>
              <th>Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($todayPoojas as $bk)
            <tr>
              <td><strong>BK{{ str_pad($bk->booking_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
              <td>{{ $bk->pooja_name }}</td>
              <td>{{ date('h:i A', strtotime($bk->booking_time)) }}</td>
              <td>{{ $bk->devotee_name ?? 'N/A' }}</td>
              <td>{{ $bk->priest_name ?? 'Not Assigned' }}</td>
              <td>
                <span class="badge bg-{{ $bk->booking_status === 'Completed' ? 'success' : ($bk->booking_status === 'Cancelled' ? 'danger' : 'warning') }} text-dark">
                  {{ $bk->booking_status }}
                </span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="showPoojaDetails({{ json_encode($bk) }})">View Details</button>
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

  @elseif(request()->get('tab') == 'upcoming-poojas')
    <!-- UPCOMING POOJAS -->
    <div class="table-wrap">
      <div class="card-header"><i class="bi bi-calendar-week text-warning me-2"></i> Upcoming Pooja Bookings</div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Pooja Name</th>
              <th>Date & Time</th>
              <th>Devotee</th>
              <th>Priest</th>
              <th>Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($upcomingPoojas as $bk)
            <tr>
              <td><strong>BK{{ str_pad($bk->booking_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
              <td>{{ $bk->pooja_name }}</td>
              <td>{{ date('d M Y', strtotime($bk->booking_date)) }} | {{ date('h:i A', strtotime($bk->booking_time)) }}</td>
              <td>{{ $bk->devotee_name ?? 'N/A' }}</td>
              <td>{{ $bk->priest_name ?? 'Not Assigned' }}</td>
              <td>
                <span class="badge bg-{{ $bk->booking_status === 'Completed' ? 'success' : ($bk->booking_status === 'Cancelled' ? 'danger' : 'warning') }} text-dark">
                  {{ $bk->booking_status }}
                </span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="showPoojaDetails({{ json_encode($bk) }})">View Details</button>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">No upcoming poojas found.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  @elseif(request()->get('tab') == 'pooja-calendar')
    <!-- POOJA CALENDAR -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-calendar3 text-warning me-2"></i>Pooja Calendar Schedule</h5>
      <div id="pooja-calendar-view" style="min-height: 600px;"></div>
    </div>

  @elseif(request()->get('tab') == 'meetings')
    <!-- MEETINGS -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-people-fill text-warning me-2"></i>Governance Board Meetings</h5>
      <ul class="list-group list-group-flush">
        <div class="list-group-item py-3 px-0 border-0 border-bottom d-flex align-items-center justify-content-between">
          <div>
            <h6 class="mb-1 fw-bold">Quarterly Board Audit</h6>
            <p class="mb-0 text-muted small"><i class="bi bi-clock me-1"></i> 2:00 PM | <i class="bi bi-geo-alt me-1"></i> Temple Conference Room</p>
          </div>
          <span class="badge bg-warning text-dark">July 15, 2026</span>
        </div>
      </ul>
    </div>

  @elseif(request()->get('tab') == 'profile')
    <!-- PROFILE -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-person-circle text-warning me-2"></i>My Profile Information</h5>
      
      @if(session('success'))
          <div class="alert alert-success border-0 rounded-3 p-3 mb-4 shadow-sm" style="background: #e6f9f0; color: #1f7a52;">
              <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
          </div>
      @endif

      @if(session('error'))
          <div class="alert alert-danger border-0 rounded-3 p-3 mb-4 shadow-sm" style="background: #ffebe6; color: #cc3300;">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
          </div>
      @endif

      @if ($errors->any())
          <div class="alert alert-danger border-0 rounded-3 p-3 mb-4 shadow-sm" style="background: #ffebe6; color: #cc3300;">
              <ul class="mb-0">
                  @foreach ($errors->all() as $error)
                      <li>{{ $error }}</li>
                  @endforeach
              </ul>
          </div>
      @endif

      <form action="{{ route('profile.update') }}" method="POST">
        @csrf
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Name</label>
            <input type="text" name="name" class="form-control rounded-3" value="{{ $user->name }}" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" class="form-control rounded-3" value="{{ $user->email }}" disabled>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Mobile</label>
            <input type="text" name="mobile" class="form-control rounded-3" value="{{ $user->mobile }}" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Gender</label>
            <select name="gender" class="form-select rounded-3">
              <option value="">Select Gender</option>
              <option value="Male" {{ ($trustee && $trustee->gender == 'Male') ? 'selected' : '' }}>Male</option>
              <option value="Female" {{ ($trustee && $trustee->gender == 'Female') ? 'selected' : '' }}>Female</option>
              <option value="Other" {{ ($trustee && $trustee->gender == 'Other') ? 'selected' : '' }}>Other</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Date of Birth</label>
            <input type="date" name="dob" class="form-control rounded-3" value="{{ $trustee->dob ?? '' }}">
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Address</label>
            <textarea name="address" class="form-control rounded-3" rows="3">{{ $trustee->address ?? '' }}</textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-warning rounded-pill px-5 fw-semibold mt-4">Save Changes</button>
      </form>
    </div>

  @else
    <!-- DEFAULT DASHBOARD OVERVIEW -->
    <div class="row g-4 mb-4">
      <div class="col-md-3 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Total Devotees</div>
            <div class="stat-number">{{ number_format($totalDevotees) }}</div>
          </div>
          <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Total Priests</div>
            <div class="stat-number">{{ number_format($totalPriests) }}</div>
          </div>
          <div class="stat-icon"><i class="bi bi-person-workspace"></i></div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Pooja Bookings</div>
            <div class="stat-number">{{ number_format($totalBookings) }}</div>
          </div>
          <div class="stat-icon"><i class="bi bi-calendar3"></i></div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-number">
              @if($totalRevenue >= 100000)
                ₹{{ number_format($totalRevenue / 100000, 2) }}L
              @elseif($totalRevenue >= 1000)
                ₹{{ number_format($totalRevenue / 1000, 1) }}K
              @else
                ₹{{ number_format($totalRevenue) }}
              @endif
            </div>
          </div>
          <div class="stat-icon"><i class="bi bi-currency-rupee"></i></div>
        </div>
      </div>
    </div>

    <!-- QUICK OVERVIEW ROWS -->
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-calendar-day text-warning me-2"></i> Today's Pooja Highlights</div>
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Pooja Name</th>
                <th>Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse($todayPoojas->take(4) as $p)
              <tr>
                <td><strong>{{ $p->pooja_name }}</strong></td>
                <td>{{ date('h:i A', strtotime($p->booking_time)) }}</td>
                <td><span class="badge bg-warning bg-opacity-20 text-warning">{{ $p->booking_status }}</span></td>
              </tr>
              @empty
              <tr>
                <td colspan="3" class="text-center py-3 text-muted">No poojas scheduled for today.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-coin text-warning me-2"></i> Recent Income Inflow</div>
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Donor/Booking</th>
                <th>Amount</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentDonations->take(2) as $don)
              <tr>
                <td><strong>{{ $don->donor_name ?? 'Anonymous' }}</strong></td>
                <td class="text-success fw-bold">+ ₹{{ number_format($don->amount) }}</td>
                <td>{{ date('d M Y', strtotime($don->donation_date)) }}</td>
              </tr>
              @endforeach
              @foreach($recentBookings->take(2) as $bk)
              <tr>
                <td><strong>Pooja Booking #{{ $bk->booking_id }}</strong></td>
                <td class="text-success fw-bold">+ ₹{{ number_format($bk->total_amount) }}</td>
                <td>{{ date('d M Y', strtotime($bk->created_at)) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  <!-- POOJA DETAIL MODAL -->
  <div class="modal fade" id="poojaDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg rounded-4" style="background: #fdfbf7; border: 1px solid #b8863a !important;">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-warning"><i class="bi bi-info-circle-fill me-2"></i>Pooja Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-3">
          <div class="d-flex flex-column gap-3" style="font-family: 'Inter', sans-serif;">
            <div>
              <span class="text-muted small fw-semibold d-block uppercase" style="letter-spacing: 0.5px;">POOJA NAME</span>
              <strong id="modalPoojaName" class="fs-5 text-dark"></strong>
            </div>
            <div class="row">
              <div class="col-6">
                <span class="text-muted small fw-semibold d-block" style="letter-spacing: 0.5px;">DATE</span>
                <span id="modalPoojaDate" class="text-dark fw-bold"></span>
              </div>
              <div class="col-6">
                <span class="text-muted small fw-semibold d-block" style="letter-spacing: 0.5px;">TIME</span>
                <span id="modalPoojaTime" class="text-dark fw-bold"></span>
              </div>
            </div>
            <div>
              <span class="text-muted small fw-semibold d-block" style="letter-spacing: 0.5px;">DEVOTEE DETAILS</span>
              <span id="modalDevoteeName" class="text-dark fw-bold d-block"></span>
              <span id="modalDevoteeContact" class="text-muted small d-block"></span>
            </div>
            <div>
              <span class="text-muted small fw-semibold d-block" style="letter-spacing: 0.5px;">ASSIGNED PRIEST</span>
              <span id="modalPriestName" class="text-dark fw-bold"></span>
            </div>
            <div class="row">
              <div class="col-6">
                <span class="text-muted small fw-semibold d-block" style="letter-spacing: 0.5px;">AMOUNT PAID</span>
                <span id="modalPoojaAmount" class="text-success fw-bold"></span>
              </div>
              <div class="col-6">
                <span class="text-muted small fw-semibold d-block" style="letter-spacing: 0.5px;">STATUS</span>
                <span id="modalPoojaStatus" class="badge"></span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Close</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('page-js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
  function showPoojaDetails(pooja) {
      document.getElementById('modalPoojaName').textContent = pooja.pooja_name;
      document.getElementById('modalPoojaDate').textContent = formatDate(pooja.booking_date);
      document.getElementById('modalPoojaTime').textContent = formatTime(pooja.booking_time);
      document.getElementById('modalDevoteeName').textContent = pooja.devotee_name || 'N/A';
      document.getElementById('modalDevoteeContact').textContent = (pooja.devotee_email || '') + ' | ' + (pooja.devotee_mobile || '');
      document.getElementById('modalPriestName').textContent = pooja.priest_name || 'Not Assigned';
      document.getElementById('modalPoojaAmount').textContent = '₹' + parseFloat(pooja.total_amount || 0).toLocaleString();
      
      const statusEl = document.getElementById('modalPoojaStatus');
      statusEl.textContent = pooja.booking_status;
      statusEl.className = 'badge bg-' + (pooja.booking_status === 'Completed' ? 'success' : (pooja.booking_status === 'Cancelled' ? 'danger' : 'warning')) + ' text-dark';

      const modal = new bootstrap.Modal(document.getElementById('poojaDetailModal'));
      modal.show();
  }

  function formatDate(dateStr) {
      if (!dateStr) return 'N/A';
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function formatTime(timeStr) {
      if (!timeStr) return 'N/A';
      const [hours, minutes] = timeStr.split(':');
      const h = parseInt(hours);
      const ampm = h >= 12 ? 'PM' : 'AM';
      const formattedHours = h % 12 || 12;
      return `${formattedHours}:${minutes} ${ampm}`;
  }

  $(document).ready(function() {
    // 1. Chart initialization
    const ctx = document.getElementById('performanceChart');
    if (ctx) {
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['Devotees', 'Priests', 'Bookings'],
          datasets: [{
            label: 'Temple ERP Capacity Footprint',
            data: [{{ $totalDevotees }}, {{ $totalPriests }}, {{ $totalBookings }}],
            backgroundColor: ['#b8863a', '#2a6fdb', '#1f9d6a'],
            borderRadius: 8
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });
    }

    // 2. FullCalendar initialization
    const calendarEl = document.getElementById('pooja-calendar-view');
    if (calendarEl) {
        const bookings = @json($allBookings);
        const events = bookings.map(b => {
            let color = '#ff9f00'; // Pending/Assigned (warning orange)
            if (b.booking_status === 'Completed') color = '#28a745'; // Completed (success green)
            if (b.booking_status === 'Cancelled') color = '#dc3545'; // Cancelled (danger red)
            if (b.booking_status === 'In Progress') color = '#17a2b8'; // In Progress (info blue)

            return {
                id: b.booking_id,
                title: b.pooja_name + ' (' + (b.devotee_name || 'Guest') + ')',
                start: b.booking_date + 'T' + b.booking_time,
                color: color,
                extendedProps: b
            };
        });

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            themeSystem: 'standard',
            events: events,
            eventClick: function(info) {
                showPoojaDetails(info.event.extendedProps);
            }
        });
        calendar.render();
    }
  });
</script>
@endsection