@extends('devotee.layouts.app')

@section('title', 'Devotee Dashboard')

@section('header-title')
  @if(request()->get('tab') == 'bookings')
    <i class="bi bi-clock-history text-warning"></i> My Pooja Bookings
  @elseif(request()->get('tab') == 'donations')
    <i class="bi bi-wallet2 text-warning"></i> Temple Donations
  @elseif(request()->get('tab') == 'membership')
    <i class="bi bi-gem text-warning"></i> Membership Plans
  @elseif(request()->get('tab') == 'events')
    <i class="bi bi-stars text-warning"></i> Temple Events
  @elseif(request()->get('tab') == 'profile')
    <i class="bi bi-person-circle text-warning"></i> My Profile
  @elseif(request()->get('tab') == 'notifications')
    <i class="bi bi-bell text-warning"></i> Notifications
  @else
    <i class="bi bi-speedometer2 text-warning"></i> Devotee Dashboard
  @endif
@endsection

@section('page-css')
<style>
  /* Premium dashboard components */
  body {
    background-color: transparent !important;
  }
  .card:not(.bg-custom), .table-wrap, .stat-card, .quick-link-card {
    border: 1px solid rgba(184, 134, 58, 0.15) !important;
    background-color: #ffffff;
    border-radius: 20px !important;
  }
  .quick-link-card {
    padding: 24px;
    transition: all 0.3s ease;
    text-align: center;
    text-decoration: none;
    color: inherit;
    display: block;
    height: 100%;
  }
  .quick-link-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(184, 134, 58, 0.1);
    border-color: #b8863a !important;
    color: inherit;
  }
  .quick-link-card .icon-wrap {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #faf6f0;
    color: #b8863a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin: 0 auto 16px;
    transition: all 0.3s;
  }
  .quick-link-card:hover .icon-wrap {
    background: linear-gradient(135deg, #b8863a, #d4a05a);
    color: white;
  }
  .quick-link-card h5 {
    font-weight: 600;
    margin-bottom: 8px;
    color: #2d1f0e;
  }
  .quick-link-card p {
    font-size: 0.85rem;
    color: #7b6b5a;
    margin: 0;
  }

  .stat-card {
    padding: 24px;
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

  .membership-card {
    background: white;
    border-radius: 24px !important;
    padding: 32px 24px;
    border: 2px solid transparent !important;
    transition: all 0.3s;
    height: 100%;
    position: relative;
  }
  .membership-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(184, 134, 58, 0.1);
  }
  .membership-card.active-tier {
    border-color: #ffd700 !important;
    background: linear-gradient(135deg, #ffffff, #fffdf8);
  }
  .membership-card.silver { border-color: #e8e8e8 !important; }
  .membership-card.gold { border-color: #ffd700 !important; }
  .membership-card.platinum { border-color: #b8b8b8 !important; }
  
  .badge-status {
    padding: 4px 12px;
    border-radius: 40px;
    font-size: 0.75rem;
    font-weight: 600;
  }
  .badge-status.confirmed { background: #d1fae5; color: #065f46; }
  .badge-status.pending { background: #fef3c7; color: #92400e; }
  .badge-status.completed { background: #dbeafe; color: #1e40af; }
  .badge-status.cancelled { background: #fee2e2; color: #991b1b; }
  .badge-status.assigned { background: #e0f2fe; color: #0369a1; }
  .badge-status.in-progress { background: #f3e8ff; color: #6b21a8; }

  .table-wrap {
    overflow: hidden;
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

  @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700;800&display=swap');

  /* Redesigned Devotee Welcome Banner */
  .devotee-welcome-banner {
    background: linear-gradient(135deg, #5c140c 0%, #7c1a0e 30%, #a82e16 65%, #c8531e 100%) !important;
    box-shadow: 0 12px 30px rgba(124, 26, 14, 0.15), inset 0 1px 1px rgba(255, 255, 255, 0.22) !important;
    border: 1px solid rgba(255, 215, 0, 0.2) !important;
    padding: 3.5rem 3rem !important;
  }

  .banner-ambient-glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    opacity: 0.35;
    z-index: 0;
  }
  .glow-orange {
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, #ff6f00 0%, transparent 70%);
    top: -30px;
    left: 40%;
  }
  .glow-gold {
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, #ffd700 0%, transparent 70%);
    bottom: -80px;
    right: 10%;
  }

  .banner-pattern-overlay {
    position: absolute;
    inset: 0;
    z-index: 0;
    opacity: 0.04;
    background-image: 
      repeating-linear-gradient(45deg, #ffd700 0px, #ffd700 1px, transparent 1px, transparent 15px),
      repeating-linear-gradient(-45deg, #ffd700 0px, #ffd700 1px, transparent 1px, transparent 15px);
  }

  .banner-mandala {
    position: absolute;
    right: -40px;
    top: 50%;
    transform: translateY(-50%);
    width: 260px;
    height: 260px;
    z-index: 0;
    opacity: 0.85;
    pointer-events: none;
  }
  .banner-mandala svg {
    width: 100%;
    height: 100%;
    animation: spinMandala 40s linear infinite;
  }

  @keyframes spinMandala {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }

  .banner-badge {
    background: rgba(255, 255, 255, 0.15) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
    backdrop-filter: blur(5px);
    font-weight: 600;
    letter-spacing: 0.5px;
    font-size: 0.8rem;
  }

  .banner-title {
    font-family: 'Cinzel', serif;
    font-size: 2.3rem !important;
    font-weight: 700;
    letter-spacing: 1px;
    text-shadow: 0 3px 12px rgba(0, 0, 0, 0.2);
  }

  .banner-desc {
    font-size: 0.95rem;
    line-height: 1.6;
    letter-spacing: 0.2px;
    max-width: 90%;
  }

  .banner-date-section {
    border-left: 1px dashed rgba(255, 255, 255, 0.2);
    padding-left: 30px;
  }

  @media (max-width: 767.98px) {
    .devotee-welcome-banner {
      padding: 2.5rem 2rem !important;
    }
    .banner-date-section {
      border-left: none;
      padding-left: 0;
      margin-top: 20px;
    }
    .banner-mandala {
      width: 180px;
      height: 180px;
      right: -20px;
      opacity: 0.45;
    }
    .banner-title {
      font-size: 1.8rem !important;
    }
  }
</style>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if(request()->get('tab') == 'bookings')
    <!-- MY BOOKINGS TAB -->
    <div class="table-wrap">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar3 me-2 text-warning"></i> My Complete Pooja Bookings</span>
        <a href="{{ route('devotee.book-pooja') }}" class="btn btn-warning rounded-pill px-4 btn-sm">
          <i class="bi bi-calendar-plus"></i> Book New Pooja
        </a>
      </div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Pooja Name</th>
              <th>Date & Time</th>
              <th>Assigned Priest</th>
              <th>Mode</th>
              <th>Total Amount</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Receipt</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentBookings as $booking)
            <tr>
              <td><strong>BK{{ str_pad($booking->booking_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
              <td>{{ $booking->pooja_name }}</td>
              <td>
                <div class="fw-semibold">{{ date('d M Y', strtotime($booking->booking_date)) }}</div>
                <div class="small text-muted">{{ date('h:i A', strtotime($booking->booking_time)) }}</div>
              </td>
              <td>{{ $booking->priest_name ?? 'Assigning...' }}</td>
              <td>
                @if($booking->booking_type === 'Online')
                  <span class="badge bg-info bg-opacity-10 text-info">Online</span>
                @else
                  <span class="badge bg-secondary bg-opacity-10 text-secondary">Offline</span>
                @endif
              </td>
              <td>
                @php
                  $bRecord = DB::table('pooja_bookings')->where('booking_id', $booking->booking_id)->first();
                @endphp
                ₹{{ number_format($bRecord->total_amount ?? 0) }}
              </td>
              <td>
                <span class="badge bg-{{ $booking->payment_status === 'Paid' ? 'success' : ($booking->payment_status === 'Refunded' ? 'info' : 'warning') }} text-white">
                  {{ $booking->payment_status }}
                </span>
              </td>
              <td>
                <span class="badge-status {{ strtolower($booking->booking_status) }}">{{ $booking->booking_status }}</span>
              </td>
              <td>
                <a href="{{ route('devotee.bookings.receipt', $booking->booking_id) }}" class="btn btn-sm btn-outline-warning rounded-pill px-3 py-1">
                  <i class="bi bi-download"></i> Receipt
                </a>
                @if($booking->payment_status === 'Pending')
                <a href="{{ route('devotee.payment', ['type' => 'pooja', 'booking_ids' => $booking->booking_id]) }}" class="btn btn-sm btn-success rounded-pill px-3 py-1 ms-1">
                  <i class="bi bi-credit-card"></i> Pay Now
                </a>
                @endif
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="9" class="text-center text-muted py-4">No pooja bookings found.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  @elseif(request()->get('tab') == 'donations')
    <!-- DONATIONS TAB -->
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-gift me-2 text-warning"></i> Donation History</div>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Donation ID</th>
                  <th>Amount</th>
                  <th>Payment Mode</th>
                  <th>Transaction ID</th>
                  <th>Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($recentDonations as $donation)
                <tr>
                  <td><strong>DN{{ str_pad($donation->donation_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                  <td class="fw-bold">₹{{ number_format($donation->amount) }}</td>
                  <td>{{ $donation->payment_mode }}</td>
                  <td><code class="small">{{ $donation->transaction_id ?? 'N/A' }}</code></td>
                  <td>{{ date('d M Y h:i A', strtotime($donation->donation_date)) }}</td>
                  <td><span class="badge bg-success">Completed</span></td>
                </tr>
                @empty
                <tr>
                  <td colspan="6" class="text-center text-muted py-4">No donation history found.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-warning border-3" style="background: white;">
          <h5 class="fw-bold mb-3"><i class="bi bi-heart-pulse-fill text-danger me-2"></i>Make a Donation</h5>
          <p class="text-muted small">Contribute generously to the maintenance of the temple and ongoing development programs.</p>
          <form action="{{ route('devotee.payment') }}" method="GET">
            <input type="hidden" name="type" value="donation">
            <div class="mb-3">
              <label class="form-label fw-semibold">Amount (₹)</label>
              <input type="number" name="amount" id="donAmt" class="form-control rounded-3" value="1000" min="10" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Purpose</label>
              <select name="purpose" class="form-select rounded-3">
                <option value="General Temple Fund">General Temple Fund</option>
                <option value="Annadhanam (Free Meals)">Annadhanam (Free Meals)</option>
                <option value="Goshala (Cow Shelter)">Goshala (Cow Shelter)</option>
                <option value="Festival Celebrations">Festival Celebrations</option>
              </select>
            </div>
            <button type="submit" class="btn btn-warning w-100 rounded-pill fw-semibold">Donate Now</button>
          </form>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'membership')
    <!-- MEMBERSHIP TAB -->
    @php
      $activeLevel = 0;
      if ($membership && isset($daysRemaining) && $daysRemaining > 0) {
          if ($membership->membership_name === 'Silver') $activeLevel = 1;
          elseif ($membership->membership_name === 'Gold') $activeLevel = 2;
          elseif ($membership->membership_name === 'Platinum') $activeLevel = 3;
      }
    @endphp
    <div class="row g-4">
      <div class="col-md-4">
        <div class="membership-card silver {{ ($membership && $membership->membership_name === 'Silver') ? 'active-tier' : '' }}">
          <div class="text-center">
            <span class="fs-1">🥈</span>
            <h4 class="fw-bold mt-2">Silver Tier</h4>
            <p class="text-muted">₹999 / month</p>
          </div>
          <hr>
          <ul class="list-unstyled">
            <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i> 10% Off on Poojas</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i> Priority Booking</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i> Monthly Prasadam</li>
          </ul>
          @if($membership && $membership->membership_name === 'Silver')
            <button class="btn btn-success w-100 rounded-pill mt-4 fw-semibold mb-2" disabled>Active Membership</button>
            <div class="text-center small text-success fw-semibold">
              Expires: {{ date('d M Y', strtotime($devotee->membership_end_date)) }}
              @if(isset($daysRemaining))
                <br>({{ $daysRemaining }} days remaining)
              @endif
            </div>
          @elseif($activeLevel > 1)
            <button class="btn btn-outline-secondary w-100 rounded-pill mt-4 fw-semibold" disabled><i class="bi bi-lock-fill"></i> Locked (Higher Tier Active)</button>
          @else
            <a href="{{ route('devotee.payment', ['type' => 'membership', 'membership_id' => 1]) }}" class="btn btn-outline-warning w-100 rounded-pill mt-4 fw-semibold">Subscribe Now</a>
          @endif
        </div>
      </div>
      <div class="col-md-4">
        <div class="membership-card gold {{ ($membership && $membership->membership_name === 'Gold') ? 'active-tier' : '' }}">
          <div class="text-center">
            <span class="fs-1">🥇</span>
            <h4 class="fw-bold mt-2">Gold Tier</h4>
            <p class="text-muted">₹1,999 / month</p>
          </div>
          <hr>
          <ul class="list-unstyled">
            <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i> 15% Off on Poojas</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i> VIP Darshana Access</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i> Weekly Prasadam Delivery</li>
          </ul>
          @if($membership && $membership->membership_name === 'Gold')
            <button class="btn btn-success w-100 rounded-pill mt-4 fw-semibold mb-2" disabled>Active Membership</button>
            <div class="text-center small text-success fw-semibold">
              Expires: {{ date('d M Y', strtotime($devotee->membership_end_date)) }}
              @if(isset($daysRemaining))
                <br>({{ $daysRemaining }} days remaining)
              @endif
            </div>
          @elseif($activeLevel > 2)
            <button class="btn btn-outline-secondary w-100 rounded-pill mt-4 fw-semibold" disabled><i class="bi bi-lock-fill"></i> Locked (Higher Tier Active)</button>
          @else
            <a href="{{ route('devotee.payment', ['type' => 'membership', 'membership_id' => 2]) }}" class="btn btn-warning w-100 rounded-pill mt-4 fw-semibold">Subscribe Now</a>
          @endif
        </div>
      </div>
      <div class="col-md-4">
        <div class="membership-card platinum {{ ($membership && $membership->membership_name === 'Platinum') ? 'active-tier' : '' }}">
          <div class="text-center">
            <span class="fs-1">💎</span>
            <h4 class="fw-bold mt-2">Platinum Tier</h4>
            <p class="text-muted">₹3,499 / month</p>
          </div>
          <hr>
          <ul class="list-unstyled">
            <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i> 25% Off on Poojas</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i> VIP Darshana & Direct Prasadam</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i> Personal Temple Guide</li>
          </ul>
          @if($membership && $membership->membership_name === 'Platinum')
            <button class="btn btn-success w-100 rounded-pill mt-4 fw-semibold mb-2" disabled>Active Membership</button>
            <div class="text-center small text-success fw-semibold">
              Expires: {{ date('d M Y', strtotime($devotee->membership_end_date)) }}
              @if(isset($daysRemaining))
                <br>({{ $daysRemaining }} days remaining)
              @endif
            </div>
          @else
            <a href="{{ route('devotee.payment', ['type' => 'membership', 'membership_id' => 3]) }}" class="btn btn-dark w-100 rounded-pill mt-4 fw-semibold">Subscribe Now</a>
          @endif
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'events')
    <!-- EVENTS TAB -->
    <div class="table-wrap">
      <div class="card-header"><i class="bi bi-stars text-warning me-2"></i> Upcoming Temple Events</div>
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
            @forelse($events as $event)
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

  @elseif(request()->get('tab') == 'profile')
    <!-- PROFILE TAB -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-person-check text-warning me-2"></i>My Devotee Profile Details</h5>
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
            <label class="form-label fw-semibold">Gothra</label>
            <input type="text" name="gothra" class="form-control rounded-3" value="{{ $devotee?->gothra ?? '' }}">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Nakshatra</label>
            <input type="text" name="nakshatra" class="form-control rounded-3" value="{{ $devotee?->nakshatra ?? '' }}">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Gender</label>
            <select name="gender" class="form-select rounded-3">
              <option value="Male" {{ ($devotee && $devotee->gender == 'Male') ? 'selected' : '' }}>Male</option>
              <option value="Female" {{ ($devotee && $devotee->gender == 'Female') ? 'selected' : '' }}>Female</option>
              <option value="Other" {{ ($devotee && $devotee->gender == 'Other') ? 'selected' : '' }}>Other</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Date of Birth</label>
            <input type="date" name="dob" class="form-control rounded-3" value="{{ $devotee?->dob ?? '' }}">
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Address</label>
            <textarea name="address" class="form-control rounded-3" rows="3">{{ $devotee?->address ?? '' }}</textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-warning rounded-pill px-5 fw-semibold mt-4">Save Profile Changes</button>
      </form>
    </div>

  @elseif(request()->get('tab') == 'notifications')
    <!-- NOTIFICATIONS TAB -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-bell-fill text-warning me-2"></i>My Alerts & Notifications</h5>
      
      @php
        $logs = [];
        if ($devotee) {
            $logs = DB::table('booking_status_logs')
                ->join('pooja_bookings', 'booking_status_logs.booking_id', '=', 'pooja_bookings.booking_id')
                ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
                ->where('pooja_bookings.devotee_id', $devotee->devotee_id)
                ->select('booking_status_logs.*', 'poojas.pooja_name')
                ->orderBy('booking_status_logs.created_at', 'desc')
                ->limit(10)
                ->get();
        }
      @endphp

      <div class="list-group list-group-flush">
        @forelse($logs as $log)
          <div class="list-group-item py-3 px-0 border-0 border-bottom">
            <div class="d-flex w-100 justify-content-between">
              <h6 class="mb-1 fw-bold">Pooja Booking Status Changed</h6>
              <small class="text-muted">{{ date('M d, Y h:i A', strtotime($log->created_at)) }}</small>
            </div>
            <p class="mb-1 text-muted small">
              Pooja <strong>{{ $log->pooja_name }}</strong> (Booking ID #{{ $log->booking_id }}) status transitioned from 
              <span class="badge-status {{ strtolower(str_replace(' ', '-', $log->status_from ?? 'Pending')) }}">{{ $log->status_from ?? 'Pending' }}</span> to 
              <span class="badge-status {{ strtolower(str_replace(' ', '-', $log->status_to)) }}">{{ $log->status_to }}</span>.
            </p>
            @if($log->remarks)
              <small class="text-warning"><i class="bi bi-chat-left-text me-1"></i> Remarks: {{ $log->remarks }}</small>
            @endif
          </div>
        @empty
          <div class="text-center py-4 text-muted">
            <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
            No alerts or notifications yet.
          </div>
        @endforelse
      </div>
    </div>

  @else
    <!-- DEFAULT DASHBOARD VIEW -->
    <!-- Welcome Banner -->
    <div class="card devotee-welcome-banner border-0 shadow mb-4 text-white position-relative overflow-hidden animate__animated animate__fadeInDown">
        <!-- Ambient Glowing Halos -->
        <div class="banner-ambient-glow glow-orange"></div>
        <div class="banner-ambient-glow glow-gold"></div>
        
        <!-- Repeating spiritual pattern texture overlay -->
        <div class="banner-pattern-overlay"></div>
        
        <!-- Rotating Gold Mandala SVG -->
        <div class="banner-mandala">
            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <circle cx="100" cy="100" r="95" fill="none" stroke="rgba(255,215,0,0.15)" stroke-width="1.5" stroke-dasharray="5,5"/>
                <circle cx="100" cy="100" r="85" fill="none" stroke="rgba(255,215,0,0.2)" stroke-width="1"/>
                <circle cx="100" cy="100" r="75" fill="none" stroke="rgba(255,215,0,0.1)" stroke-width="1.5" stroke-dasharray="10,5"/>
                <circle cx="100" cy="100" r="65" fill="none" stroke="rgba(255,215,0,0.25)" stroke-width="1.2"/>
                <path d="M100,5 A95,95 0 0,1 195,100 L100,100 Z" fill="none" stroke="rgba(255,215,0,0.08)" stroke-width="1"/>
                <!-- Mandala Petals -->
                <g stroke="rgba(255,215,0,0.22)" fill="none" stroke-width="1">
                    <path d="M100,65 Q110,75 100,85 Q90,75 100,65"/>
                    <path d="M100,115 Q110,125 100,135 Q90,125 100,115"/>
                    <path d="M65,100 Q75,110 85,100 Q75,90 65,100"/>
                    <path d="M115,100 Q125,110 135,100 Q125,90 115,100"/>
                    <!-- Diagonal Petals -->
                    <path d="M75,75 Q85,85 75,95 Q65,85 75,75" transform="rotate(45 100 100)"/>
                    <path d="M75,75 Q85,85 75,95 Q65,85 75,75" transform="rotate(135 100 100)"/>
                    <path d="M75,75 Q85,85 75,95 Q65,85 75,75" transform="rotate(225 100 100)"/>
                    <path d="M75,75 Q85,85 75,95 Q65,85 75,75" transform="rotate(315 100 100)"/>
                </g>
                <circle cx="100" cy="100" r="18" fill="none" stroke="rgba(255,215,0,0.3)" stroke-width="2"/>
                <circle cx="100" cy="100" r="8" fill="rgba(255,215,0,0.2)"/>
            </svg>
        </div>

        <div class="row align-items-center position-relative z-1">
            <div class="col-md-8">
                <span class="badge banner-badge rounded-pill px-3 py-2 mb-3">
                    <i class="bi bi-star-fill me-1 text-warning animate__animated animate__pulse animate__infinite"></i> Welcome to Devotee Portal
                </span>
                <h1 class="banner-title display-6 mb-2">Hare Krishna, {{ $user->name }} 🙏</h1>
                <p class="banner-desc mb-0 opacity-90">Step into the divine workspace of Temple ERP. Schedule rituals, view upcoming temple events, and manage your contributions seamlessly.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0 banner-date-section">
                <div class="text-white-50 small mb-1"><i class="bi bi-calendar3 me-1 text-warning"></i> Today's Date</div>
                <div class="banner-date fw-bold fs-5">{{ date('d M Y') }}</div>
            </div>
        </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT -->
    <div class="row g-4">
        <!-- LEFT PANEL (Statistics, Quick Actions, Calendar) -->
        <div class="col-lg-8">
            <!-- Stats Grid -->
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm rounded-4 p-3 position-relative overflow-hidden h-100" style="background: white; border: 1px solid rgba(184, 134, 58, 0.15);">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-muted small fw-semibold uppercase mb-1" style="font-size:0.75rem;">Total Bookings</div>
                                <h3 class="fw-bold mb-0 text-dark">{{ $poojaCount }}</h3>
                            </div>
                            <div class="icon-box p-3 bg-warning bg-opacity-10 text-warning rounded-3"><i class="bi bi-calendar-event fs-3"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm rounded-4 p-3 position-relative overflow-hidden h-100" style="background: white; border: 1px solid rgba(184, 134, 58, 0.15);">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-muted small fw-semibold uppercase mb-1" style="font-size:0.75rem;">Upcoming Poojas</div>
                                <h3 class="fw-bold mb-0 text-dark">{{ $upcomingPoojas }}</h3>
                            </div>
                            <div class="icon-box p-3 bg-success bg-opacity-10 text-success rounded-3"><i class="bi bi-clock-history fs-3"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm rounded-4 p-3 position-relative overflow-hidden h-100" style="background: white; border: 1px solid rgba(184, 134, 58, 0.15);">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-muted small fw-semibold uppercase mb-1" style="font-size:0.75rem;">Total Donations</div>
                                <h3 class="fw-bold mb-0 text-dark">₹{{ number_format($totalDonation) }}</h3>
                            </div>
                            <div class="icon-box p-3 bg-danger bg-opacity-10 text-danger rounded-3"><i class="bi bi-wallet2 fs-3"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: white; border: 1px solid rgba(184, 134, 58, 0.15);">
                <h5 class="fw-bold mb-3"><i class="bi bi-lightning-fill text-warning me-2"></i>Quick Actions</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('devotee.book-pooja') }}" class="btn btn-warning rounded-pill px-4 fw-semibold text-white" style="background: linear-gradient(135deg, #b8863a, #f27a1a); border: none;">
                        <i class="bi bi-calendar-plus me-1"></i> Book Pooja
                    </a>
                    <a href="{{ route('devotee.dashboard') }}?tab=donations" class="btn btn-outline-warning rounded-pill px-4 fw-semibold">
                        <i class="bi bi-gift me-1"></i> Donate to Temple
                    </a>
                    <a href="{{ route('devotee.dashboard') }}?tab=bookings" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
                        <i class="bi bi-list-task me-1"></i> View My Bookings
                    </a>
                    <a href="{{ route('devotee.dashboard') }}?tab=membership" class="btn btn-outline-info rounded-pill px-4 fw-semibold">
                        <i class="bi bi-gem me-1"></i> Memberships
                    </a>
                </div>
            </div>

            <!-- Recent Pooja Bookings -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: white; border: 1px solid rgba(184, 134, 58, 0.15);">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-calendar-check text-warning me-2"></i>My Recent Pooja Bookings</h5>
                    <a href="{{ route('devotee.dashboard') }}?tab=bookings" class="small text-warning fw-semibold text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0" style="font-size: 0.9rem;">
                        <thead>
                            <tr class="text-uppercase text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                <th class="border-0 ps-0">Pooja</th>
                                <th class="border-0">Date & Time</th>
                                <th class="border-0">Priest</th>
                                <th class="border-0 text-end pe-0">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentBookings->take(3) as $booking)
                            <tr>
                                <td class="border-0 ps-0 fw-semibold text-dark">{{ $booking->pooja_name }}</td>
                                <td class="border-0">
                                    <div class="fw-medium text-dark">{{ date('d M Y', strtotime($booking->booking_date)) }}</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">{{ date('h:i A', strtotime($booking->booking_time)) }}</div>
                                </td>
                                <td class="border-0 text-muted">{{ $booking->priest_name ?? 'Assigning...' }}</td>
                                <td class="border-0 text-end pe-0">
                                    <span class="badge bg-{{ $booking->booking_status === 'Completed' ? 'success' : ($booking->booking_status === 'Cancelled' ? 'danger' : 'warning') }} bg-opacity-10 text-{{ $booking->booking_status === 'Completed' ? 'success' : ($booking->booking_status === 'Cancelled' ? 'danger' : 'warning') }} rounded-pill px-3 py-1.5" style="font-size: 0.75rem;">
                                        {{ $booking->booking_status }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4 border-0 ps-0 pe-0">No recent pooja bookings found. <a href="{{ route('devotee.book-pooja') }}" class="text-warning fw-semibold text-decoration-none ms-1">Book Now</a></td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL (Membership, Upcoming Events, Recent Activity) -->
        <div class="col-lg-4">
            <!-- Membership Card -->
            @if($membership)
                @php
                    $tier = strtoupper($membership->membership_name);
                    $gradient = 'linear-gradient(135deg, #bdc3c7, #2c3e50)';
                    $icon = '🥈';
                    if ($tier === 'GOLD') {
                        $gradient = 'linear-gradient(135deg, #d4af37, #f39c12)';
                        $icon = '🥇';
                    } elseif ($tier === 'PLATINUM') {
                        $gradient = 'linear-gradient(135deg, #303952, #596275)';
                        $icon = '💎';
                    }
                @endphp
                <div class="card bg-custom border-0 shadow rounded-4 text-white position-relative overflow-hidden mb-4" style="background: {{ $gradient }} !important; min-height: 250px;">
                    <div class="position-absolute" style="right: -20px; top: -20px; font-size: 8rem; opacity: 0.15;">{{ $icon }}</div>
                    <div class="card-body p-4 d-flex flex-column justify-content-between position-relative z-1">
                        <div>
                            <span class="badge rounded-pill px-3 py-1 mb-2" style="background: rgba(255, 255, 255, 0.2) !important; color: #ffffff !important;">{{ $membership->membership_name }} Tier</span>
                            <h3 class="fw-bold mb-1">{{ $membership->membership_name }} Plan</h3>
                            <p class="small text-white-50 mb-3">
                                Expires: {{ $devotee->membership_end_date ? date('d M Y', strtotime($devotee->membership_end_date)) : 'N/A' }}
                                @if(isset($daysRemaining) && $daysRemaining !== null)
                                    <span class="badge bg-light text-dark ms-1" style="font-size: 0.7rem;">{{ $daysRemaining }} days remaining</span>
                                @endif
                            </p>
                            <ul class="list-unstyled mb-0 small opacity-90">
                                <li class="mb-1"><i class="bi bi-patch-check-fill me-1"></i> Exclusive discounts on Poojas</li>
                                <li class="mb-1"><i class="bi bi-patch-check-fill me-1"></i> Priority booking access</li>
                                <li class="mb-1"><i class="bi bi-patch-check-fill me-1"></i> Direct prasadam courier delivery</li>
                            </ul>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('devotee.dashboard') }}?tab=membership" class="btn btn-light btn-sm rounded-pill fw-semibold text-dark px-3">Upgrade Membership</a>
                        </div>
                    </div>
                </div>
            @else
                <div class="card bg-custom border-0 shadow-sm rounded-4 position-relative overflow-hidden mb-4" style="background: linear-gradient(135deg, #fffdf8, #ebdcc5); border: 1px solid #ebdcc5; min-height: 250px;">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 mb-2">Membership Portal</span>
                            <h3 class="fw-bold text-dark mb-1">Divine Membership</h3>
                            <p class="text-muted small mb-3">Subscribe to a tier and get VIP Darshana, free weekly prasadam delivery, and special pooja discounts.</p>
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-1"><i class="bi bi-star-fill text-warning me-1"></i> VIP Direct Entry</li>
                                <li class="mb-1"><i class="bi bi-star-fill text-warning me-1"></i> Up to 25% Off Rituals</li>
                            </ul>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('devotee.dashboard') }}?tab=membership" class="btn btn-warning btn-sm w-100 rounded-pill fw-semibold text-white px-3" style="background: linear-gradient(135deg, #b8863a, #f27a1a); border:none;">Explore Premium Plans</a>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Upcoming Events -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: white; border: 1px solid rgba(184, 134, 58, 0.15);">
                <h5 class="fw-bold mb-3"><i class="bi bi-stars text-warning me-2"></i>Upcoming Events</h5>
                <div class="list-group list-group-flush">
                    @forelse($events as $event)
                        <div class="list-group-item py-3 px-0 border-0 border-bottom">
                            <div class="fw-bold text-dark">{{ $event->event_name }}</div>
                            <div class="text-muted small mb-1"><i class="bi bi-calendar-event me-1"></i>{{ date('d M Y', strtotime($event->event_date)) }}</div>
                            <div class="text-muted small"><i class="bi bi-clock me-1"></i>{{ date('h:i A', strtotime($event->start_time)) }} - {{ date('h:i A', strtotime($event->end_time)) }}</div>
                            <span class="badge bg-warning text-dark mt-2" style="background-color: #b8863a !important; color: white !important;">{{ $event->location }}</span>
                        </div>
                    @empty
                        <div class="text-muted py-3 small text-center">No upcoming events scheduled.</div>
                    @endforelse
                </div>
                <div class="mt-3 text-center">
                    <a href="{{ route('devotee.dashboard') }}?tab=events" class="small text-warning fw-semibold text-decoration-none">View All Events <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white; border: 1px solid rgba(184, 134, 58, 0.15);">
                <h5 class="fw-bold mb-3"><i class="bi bi-bell text-warning me-2"></i>Recent Activity</h5>
                @php
                    $recentActivityLogs = [];
                    if ($devotee) {
                        $recentActivityLogs = DB::table('booking_status_logs')
                            ->join('pooja_bookings', 'booking_status_logs.booking_id', '=', 'pooja_bookings.booking_id')
                            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
                            ->where('pooja_bookings.devotee_id', $devotee->devotee_id)
                            ->select('booking_status_logs.*', 'poojas.pooja_name')
                            ->orderBy('booking_status_logs.created_at', 'desc')
                            ->limit(4)
                            ->get();
                    }
                @endphp
                <div class="list-group list-group-flush small">
                    @forelse($recentActivityLogs as $log)
                        <div class="list-group-item py-2 px-0 border-0 border-bottom">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold text-dark">{{ $log->pooja_name }} status</span>
                                <span class="text-muted" style="font-size:0.75rem;">{{ date('d M', strtotime($log->created_at)) }}</span>
                            </div>
                             <div class="text-muted mt-1">Changed to <span class="badge-status {{ strtolower(str_replace(' ', '-', $log->status_to)) }}">{{ $log->status_to }}</span></div>
                        </div>
                    @empty
                        <div class="text-muted py-3 text-center">No recent activities.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
  @endif
@endsection