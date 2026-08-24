@extends('accountant.layouts.app')

@section('title', 'Accountant Dashboard')

@section('header-title')
  @if(request()->get('tab') == 'income')
    <i class="bi bi-graph-up text-warning"></i> Temple Income Ledger
  @elseif(request()->get('tab') == 'expenses')
    <i class="bi bi-graph-down text-warning"></i> Operating Expenses
  @elseif(request()->get('tab') == 'payroll')
    <i class="bi bi-person-workspace text-warning"></i> Payroll Management
  @elseif(request()->get('tab') == 'donations')
    <i class="bi bi-wallet2 text-warning"></i> Devotee Donations Ledger
  @elseif(request()->get('tab') == 'reports')
    <i class="bi bi-file-earmark-bar-graph text-warning"></i> Financial Statements
  @elseif(request()->get('tab') == 'salary')
    <i class="bi bi-cash-stack text-warning"></i> My Salary
  @elseif(request()->get('tab') == 'invoices')
    <i class="bi bi-receipt text-warning"></i> Invoices Log
  @elseif(request()->get('tab') == 'transactions')
    <i class="bi bi-arrow-left-right text-warning"></i> Transaction Logbook
  @elseif(request()->get('tab') == 'profile')
    <i class="bi bi-person-circle text-warning"></i> Accountant Profile
  @else
    <i class="bi bi-speedometer2 text-warning"></i> Accountant Dashboard
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
  @if(request()->get('tab') == 'income')
    <!-- INCOME TAB -->
    <div class="row g-4">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center" style="background: white;">
          <h5 class="fw-bold mb-2">Total Collections</h5>
          <h2 class="fw-bold text-success">₹{{ number_format($totalIncome, 2) }}</h2>
          <p class="text-muted small">Includes pooja bookings and direct donation funds.</p>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center" style="background: white;">
          <h5 class="fw-bold mb-2">Donation Collections</h5>
          <h2 class="fw-bold text-warning">₹{{ number_format($totalDonations, 2) }}</h2>
          <p class="text-muted small">Represents the direct donation inflow.</p>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'expenses')
    <!-- EXPENSES -->
    <div class="table-wrap">
      <div class="card-header"><i class="bi bi-graph-down text-warning me-2"></i> Payout Expenses Ledger</div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Payout ID</th>
              <th>Recipient</th>
              <th>Role</th>
              <th>Month</th>
              <th>Paid Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($salaryPayouts as $p)
            <tr>
              <td><strong>#PAY{{ $p->payout_id }}</strong></td>
              <td>{{ $p->name }}</td>
              <td>{{ $p->role }}</td>
              <td>{{ $p->salary_month }}</td>
              <td class="fw-bold text-danger">- ₹{{ number_format($p->total_paid) }}</td>
              <td><span class="badge bg-success">{{ $p->payment_status }}</span></td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No payout expenses found.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  @elseif(request()->get('tab') == 'payroll')
    <!-- PAYROLL -->
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-person-workspace text-warning me-2"></i> Salary Payouts</div>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Recipient</th>
                  <th>Salary Month</th>
                  <th>Base Salary</th>
                  <th>Commission</th>
                  <th>Total Paid</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                @forelse($salaryPayouts as $p)
                <tr>
                  <td>{{ $p->name }} <span class="badge bg-light text-dark small">{{ $p->role }}</span></td>
                  <td>{{ $p->salary_month }}</td>
                  <td>₹{{ number_format($p->base_salary) }}</td>
                  <td>₹{{ number_format($p->wallet_amount) }}</td>
                  <td class="fw-bold">₹{{ number_format($p->total_paid) }}</td>
                  <td>{{ date('d M Y', strtotime($p->payment_date)) }}</td>
                </tr>
                @empty
                <tr>
                  <td colspan="6" class="text-center text-muted py-4">No payroll payouts logged.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-warning border-3" style="background: white;">
          <h5 class="fw-bold mb-3"><i class="bi bi-coin text-warning me-2"></i>Record Salary Payout</h5>
          <p class="text-muted small">Record dynamic salary payments directly into the payroll database.</p>
          <form onsubmit="alert('Salary payout recorded in system!'); return false;">
            <div class="mb-3">
              <label class="form-label fw-semibold">Staff/Priest User ID</label>
              <input type="number" class="form-control rounded-3" required placeholder="User ID">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Base Salary (₹)</label>
              <input type="number" class="form-control rounded-3" required value="20000">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Salary Month</label>
              <input type="text" class="form-control rounded-3" required value="June 2026">
            </div>
            <button class="btn btn-warning w-100 rounded-pill fw-semibold">Save Payout Entry</button>
          </form>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'donations')
    <!-- DONATIONS -->
    <div class="table-wrap">
      <div class="card-header"><i class="bi bi-wallet2 text-warning me-2"></i> All Devotee Donations</div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Donor</th>
              <th>Amount</th>
              <th>Payment Mode</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            @forelse($donations as $don)
            <tr>
              <td><strong>{{ $don->source ?? 'Anonymous' }}</strong></td>
              <td class="text-success fw-bold">₹{{ number_format($don->amount) }}</td>
              <td>{{ $don->payment_mode ?? 'UPI' }}</td>
              <td>{{ date('d M Y', strtotime($don->txn_date)) }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-4">No donation entries logged.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  @elseif(request()->get('tab') == 'reports')
    <!-- FINANCIAL REPORTS -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-file-earmark-pdf text-warning me-2"></i>Generate Accountant Reports</h5>
      <div class="row g-3">
        <div class="col-md-6">
          <button class="btn btn-warning w-100 rounded-pill py-3 fw-semibold" onclick="alert('Exporting General Ledger Sheet PDF...')">
            <i class="bi bi-file-pdf me-2"></i> Export Ledger Statement (PDF)
          </button>
        </div>
        <div class="col-md-6">
          <button class="btn btn-outline-warning w-100 rounded-pill py-3 fw-semibold" onclick="alert('Exporting CSV balance sheet...')">
            <i class="bi bi-file-spreadsheet me-2"></i> Export Income-Expense (CSV)
          </button>
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
                  <th>Net Paid</th>
                  <th>Payment Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($myPayouts as $payout)
                <tr>
                  <td><strong>{{ date('F Y', strtotime($payout->salary_month . '-01')) }}</strong></td>
                  <td>₹{{ number_format($payout->base_salary, 2) }}</td>
                  <td class="fw-bold">₹{{ number_format($payout->total_paid, 2) }}</td>
                  <td>{{ $payout->payment_date ? date('d M Y', strtotime($payout->payment_date)) : 'N/A' }}</td>
                  <td><span class="badge bg-success">{{ $payout->payment_status }}</span></td>
                </tr>
                @empty
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">No payout history found.</td>
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
            <span class="fw-semibold">₹{{ number_format($accountant->salary, 2) }}</span>
          </div>
          <hr>
          <div class="d-flex justify-content-between mb-3">
            <span class="fw-bold">Net Payout (Est.):</span>
            <span class="fw-bold text-warning h4 mb-0">₹{{ number_format($accountant->salary, 2) }}</span>
          </div>
          <p class="text-muted small mb-0">Monthly payouts are approved at the end of the month by the Administrator.</p>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-warning border-3 mb-4" style="background: white;">
          <h5 class="fw-bold mb-3"><i class="bi bi-bank text-warning me-2"></i>Bank Details</h5>
          <div class="mb-2">
            <span class="text-muted small d-block">Account Holder Name:</span>
            <strong class="text-dark">{{ $accountant->account_holder_name ?? 'N/A' }}</strong>
          </div>
          <div class="mb-2">
            <span class="text-muted small d-block">Account Number:</span>
            <strong class="text-dark">{{ $accountant->account_number ?? 'N/A' }}</strong>
          </div>
          <div class="mb-2">
            <span class="text-muted small d-block">Bank Name:</span>
            <strong class="text-dark">{{ $accountant->bank_name ?? 'N/A' }}</strong>
          </div>
          <div class="mb-2">
            <span class="text-muted small d-block">IFSC Code:</span>
            <strong class="text-dark">{{ $accountant->ifsc_code ?? 'N/A' }}</strong>
          </div>
          <div class="mb-0">
            <span class="text-muted small d-block">Branch:</span>
            <strong class="text-dark">{{ $accountant->branch_name ?? 'N/A' }}</strong>
          </div>
        </div>
      </div>
    </div>

  @elseif(request()->get('tab') == 'invoices')
    <!-- INVOICES -->
    <div class="table-wrap">
      <div class="card-header"><i class="bi bi-receipt text-warning me-2"></i> Pooja Invoices Ledgers</div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Invoice ID</th>
              <th>Devotee Name</th>
              <th>Pooja Name</th>
              <th>Amount</th>
              <th>Shipping</th>
              <th>Discount</th>
              <th>Total Amount</th>
              <th>Method</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($invoices as $inv)
            <tr>
              <td><strong>BK{{ str_pad($inv->booking_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
              <td>{{ $inv->devotee_name }}</td>
              <td>{{ $inv->pooja_name }}</td>
              <td>₹{{ number_format($inv->amount) }}</td>
              <td>₹{{ number_format($inv->shipping_charge) }}</td>
              <td>₹{{ number_format($inv->discount_amount) }}</td>
              <td class="fw-bold">₹{{ number_format($inv->total_amount) }}</td>
              <td><span class="badge bg-light text-dark">{{ $inv->payment_method ?? 'Cash' }}</span></td>
              <td><span class="badge bg-{{ $inv->payment_status === 'Paid' ? 'success' : 'warning' }}">{{ $inv->payment_status }}</span></td>
            </tr>
            @empty
            <tr>
              <td colspan="9" class="text-center text-muted py-4">No booking invoices logged.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  @elseif(request()->get('tab') == 'transactions')
    <!-- TRANSACTIONS -->
    <div class="table-wrap">
      <div class="card-header"><i class="bi bi-arrow-left-right text-warning me-2"></i> Ledger Transaction Entries</div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Transaction Source/Recipient</th>
              <th>Amount</th>
              <th>Transaction Type</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            @foreach($donations as $don)
            <tr>
              <td><strong>{{ $don->source ?? 'Anonymous' }}</strong></td>
              <td class="text-success fw-bold">+ ₹{{ number_format($don->amount) }}</td>
              <td><span class="badge bg-success bg-opacity-10 text-success">Donation</span></td>
              <td>{{ date('d M Y', strtotime($don->txn_date)) }}</td>
            </tr>
            @endforeach
            @foreach($payouts as $pay)
            <tr>
              <td><strong>{{ $pay->source }}</strong></td>
              <td class="text-danger fw-bold">- ₹{{ number_format($pay->amount) }}</td>
              <td><span class="badge bg-danger bg-opacity-10 text-danger">Salary Payout</span></td>
              <td>{{ date('d M Y', strtotime($pay->txn_date)) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

  @elseif(request()->get('tab') == 'profile')
    <!-- PROFILE -->
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
      <h5 class="fw-bold mb-4"><i class="bi bi-person-circle text-warning me-2"></i>Accountant Profile Information</h5>

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
              <option value="Male" {{ ($accountant && $accountant->gender == 'Male') ? 'selected' : '' }}>Male</option>
              <option value="Female" {{ ($accountant && $accountant->gender == 'Female') ? 'selected' : '' }}>Female</option>
              <option value="Other" {{ ($accountant && $accountant->gender == 'Other') ? 'selected' : '' }}>Other</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Date of Birth</label>
            <input type="date" name="dob" class="form-control rounded-3" value="{{ $accountant->dob ?? '' }}">
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Address</label>
            <textarea name="address" class="form-control rounded-3" rows="3">{{ $accountant->address ?? '' }}</textarea>
          </div>

          <h5 class="fw-bold mt-4 mb-2"><i class="bi bi-bank text-warning me-2"></i>Bank Account Information</h5>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Holder Name</label>
            <input type="text" name="account_holder_name" class="form-control rounded-3" value="{{ $accountant->account_holder_name ?? '' }}">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Number</label>
            <input type="text" name="account_number" class="form-control rounded-3" value="{{ $accountant->account_number ?? '' }}">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Bank Name</label>
            <input type="text" name="bank_name" class="form-control rounded-3" value="{{ $accountant->bank_name ?? '' }}">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">IFSC Code</label>
            <input type="text" name="ifsc_code" class="form-control rounded-3" value="{{ $accountant->ifsc_code ?? '' }}">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Branch Name</label>
            <input type="text" name="branch_name" class="form-control rounded-3" value="{{ $accountant->branch_name ?? '' }}">
          </div>
        </div>
        <button type="submit" class="btn btn-warning rounded-pill px-5 fw-semibold mt-4">Save Changes</button>
      </form>
    </div>

  @else
    <!-- DEFAULT DASHBOARD OVERVIEW -->
    <div class="row g-4 mb-4">
      <div class="col-md-4 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Total Treasury Income</div>
            <div class="stat-number">₹{{ number_format($totalIncome / 1000, 1) }}K</div>
          </div>
          <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
        </div>
      </div>
      <div class="col-md-4 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Total Expenses</div>
            <div class="stat-number">₹{{ number_format($totalExpenses / 1000, 1) }}K</div>
          </div>
          <div class="stat-icon"><i class="bi bi-cart3"></i></div>
        </div>
      </div>
      <div class="col-md-4 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Net Balance</div>
            <div class="stat-number">₹{{ number_format(($totalIncome - $totalExpenses) / 1000, 1) }}K</div>
          </div>
          <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
        </div>
      </div>
    </div>

    <!-- QUICK LOGS -->
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-receipt text-warning me-2"></i> Recent Invoices</div>
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Invoice</th>
                <th>Devotee</th>
                <th>Total Amount</th>
              </tr>
            </thead>
            <tbody>
              @forelse($invoices->take(4) as $inv)
              <tr>
                <td><strong>BK{{ str_pad($inv->booking_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                <td>{{ $inv->devotee_name }}</td>
                <td class="fw-bold">₹{{ number_format($inv->total_amount) }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="3" class="text-center py-3 text-muted">No invoices recorded.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="table-wrap">
          <div class="card-header"><i class="bi bi-wallet2 text-warning me-2"></i> Recent Donations Ledger</div>
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Donor</th>
                <th>Amount</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              @forelse($donations->take(4) as $don)
              <tr>
                <td><strong>{{ $don->source ?? 'Anonymous' }}</strong></td>
                <td class="text-success fw-bold">+ ₹{{ number_format($don->amount) }}</td>
                <td>{{ date('d M Y', strtotime($don->txn_date)) }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="3" class="text-center py-3 text-muted">No donations found.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif
@endsection