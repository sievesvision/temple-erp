@extends('admin.layouts.app')

@section('title', 'System Reports')

@section('page-css')
<style>
    .report-card {
        background: white;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(184, 134, 58, 0.06);
        margin-bottom: 24px;
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
    .table-responsive {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #f0ece6;
    }
    .table th {
        background-color: #faf6f0;
        color: #7b6b5a;
        font-size: 0.85rem;
        text-transform: uppercase;
        font-weight: 600;
        padding: 14px 20px;
    }
    .table td {
        padding: 14px 20px;
        vertical-align: middle;
    }
    .badge-status-upcoming { background: #e0f2fe; color: #0369a1; }
    .badge-status-ongoing { background: #fef3c7; color: #b45309; }
    .badge-status-completed { background: #d1fae5; color: #047857; }
    .badge-status-cancelled { background: #fee2e2; color: #b91c1c; }

    @media print {
        .sidebar, .topbar, .nav-tabs, .btn-print-report {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        .container-fluid {
            padding: 0 !important;
        }
        .tab-content > .tab-pane {
            display: block !important;
            opacity: 1 !important;
            page-break-after: always;
            margin-bottom: 40px;
        }
        .report-card {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
    }
</style>

@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-file-earmark-bar-graph text-warning me-2"></i>System Reports</h3>
        <button onclick="window.print()" class="btn btn-outline-warning rounded-pill px-4 fw-semibold btn-print-report" style="border-color: #b8863a; color: #b8863a;">
            <i class="bi bi-printer me-2"></i>Print / Export PDF
        </button>
    </div>


    <!-- Tabs Content -->
    <div class="report-card p-0">
        <div class="px-4 pt-3 border-bottom">
            <ul class="nav nav-tabs border-0" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance-pane" type="button" role="tab"><i class="bi bi-person-check text-warning me-1"></i>Attendance</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="salary-tab" data-bs-toggle="tab" data-bs-target="#salary-pane" type="button" role="tab"><i class="bi bi-cash-stack text-warning me-1"></i>Payroll & Salaries</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="wallet-tab" data-bs-toggle="tab" data-bs-target="#wallet-pane" type="button" role="tab"><i class="bi bi-wallet2 text-warning me-1"></i>Wallet Transactions</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="poojas-tab" data-bs-toggle="tab" data-bs-target="#poojas-pane" type="button" role="tab"><i class="bi bi-calendar-event text-warning me-1"></i>Pooja Completion</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="earnings-tab" data-bs-toggle="tab" data-bs-target="#earnings-pane" type="button" role="tab"><i class="bi bi-currency-rupee text-warning me-1"></i>Monthly Earnings</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events-pane" type="button" role="tab"><i class="bi bi-stars text-warning me-1"></i>Events Report</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory-pane" type="button" role="tab"><i class="bi bi-box-seam text-warning me-1"></i>Inventory & Stock</button>
                </li>
            </ul>
        </div>

        <div class="tab-content p-4" id="reportTabsContent">
            <!-- Attendance Pane -->
            <div class="tab-pane fade show active" id="attendance-pane" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3 text-dark">Priests Daily Attendance Log (Last 30 days)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Present Count</th>
                                        <th>Total Hours Logged</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($priestAttendance as $att)
                                    <tr>
                                        <td>{{ date('d M Y', strtotime($att->attendance_date)) }}</td>
                                        <td><strong>{{ $att->present_count }}</strong> Present</td>
                                        <td>{{ number_format($att->total_hours, 2) }} hrs</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No attendance logs.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3 text-dark">Staff Daily Attendance Log (Last 30 days)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Present Count</th>
                                        <th>Total Hours Logged</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($staffAttendance as $att)
                                    <tr>
                                        <td>{{ date('d M Y', strtotime($att->attendance_date)) }}</td>
                                        <td><strong>{{ $att->present_count }}</strong> Present</td>
                                        <td>{{ number_format($att->total_hours, 2) }} hrs</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No attendance logs.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Salary Pane -->
            <div class="tab-pane fade" id="salary-pane" role="tabpanel">
                <h5 class="fw-bold mb-3 text-dark">Monthly Salaries Sanctioned Summary</h5>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Salary Month</th>
                                <th>Total Base Salaries</th>
                                <th>Total Wallet Adjustments</th>
                                <th>Total Net Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($salaryPayoutsSummary as $s)
                            <tr>
                                <td><strong>{{ date('F Y', strtotime($s->salary_month . '-01')) }}</strong></td>
                                <td>₹{{ number_format($s->total_base, 2) }}</td>
                                <td class="text-{{ $s->total_wallet >= 0 ? 'success' : 'danger' }}">
                                    {{ $s->total_wallet >= 0 ? '+' : '' }}₹{{ number_format($s->total_wallet, 2) }}
                                </td>
                                <td class="fw-bold">₹{{ number_format($s->total_paid, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">No salary payouts reported yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Wallet Pane -->
            <div class="tab-pane fade" id="wallet-pane" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3 text-dark">Priests Wallets Credits & Debits Timeline</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Total Credits (Commissions)</th>
                                        <th>Total Debits (Penalties)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($priestWalletTx as $tx)
                                    <tr>
                                        <td>{{ date('d M Y', strtotime($tx->date)) }}</td>
                                        <td class="text-success fw-semibold">+₹{{ number_format($tx->credits, 2) }}</td>
                                        <td class="text-danger fw-semibold">-₹{{ number_format($tx->debits, 2) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No wallet transactions logged.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3 text-dark">Staff Wallets Credits & Debits Timeline</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Total Credits</th>
                                        <th>Total Debits (Penalties)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($staffWalletTx as $tx)
                                    <tr>
                                        <td>{{ date('d M Y', strtotime($tx->date)) }}</td>
                                        <td class="text-success fw-semibold">+₹{{ number_format($tx->credits, 2) }}</td>
                                        <td class="text-danger fw-semibold">-₹{{ number_format($tx->debits, 2) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No wallet transactions logged.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pooja Completion Pane -->
            <div class="tab-pane fade" id="poojas-pane" role="tabpanel">
                <h5 class="fw-bold mb-3 text-dark">Pooja Bookings Completion Summary</h5>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Pooja Name</th>
                                <th>Completed Bookings Count</th>
                                <th>Total Invoiced Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($poojaCompletionSummary as $p)
                            <tr>
                                <td><strong>{{ $p->pooja_name }}</strong></td>
                                <td>{{ $p->completed_count }} Completed</td>
                                <td class="fw-bold text-success">₹{{ number_format($p->total_amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-3 text-muted">No completed poojas logged.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Monthly Earnings Pane -->
            <div class="tab-pane fade" id="earnings-pane" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3 text-dark">Monthly Bookings Revenue</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Total Earnings</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($bookingsEarnings as $b)
                                    <tr>
                                        <td><strong>{{ date('F Y', strtotime($b->month . '-01')) }}</strong></td>
                                        <td class="fw-bold text-success">₹{{ number_format($b->total_earnings, 2) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="2" class="text-center py-3 text-muted">No bookings earnings reported.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3 text-dark">Monthly Direct Donations Revenue</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Total Donations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($donationsEarnings as $d)
                                    <tr>
                                        <td><strong>{{ date('F Y', strtotime($d->month . '-01')) }}</strong></td>
                                        <td class="fw-bold text-success">₹{{ number_format($d->total_earnings, 2) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="2" class="text-center py-3 text-muted">No donations earnings reported.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Events Report Pane -->
            <div class="tab-pane fade" id="events-pane" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-4">
                        <h5 class="fw-bold mb-3 text-dark">Events Breakdown by Status</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Total Events Scheduled</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($eventsCountSummary as $summary)
                                    <tr>
                                        <td>
                                            <span class="badge px-3 py-2 rounded-pill fw-semibold badge-status-{{ strtolower($summary->status) }}">
                                                {{ $summary->status }}
                                            </span>
                                        </td>
                                        <td class="fw-bold fs-5 text-dark">{{ $summary->count }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="2" class="text-center py-3 text-muted">No events data.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h5 class="fw-bold mb-3 text-dark">Recent Scheduled Events (Last 20)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Event Name</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentEventsList as $e)
                                    <tr>
                                        <td><strong>{{ $e->event_name }}</strong></td>
                                        <td>
                                            <div class="fw-semibold">{{ date('d M Y', strtotime($e->event_date)) }}</div>
                                            <div class="small text-muted">{{ date('g:i A', strtotime($e->start_time)) }} - {{ date('g:i A', strtotime($e->end_time)) }}</div>
                                        </td>
                                        <td>{{ $e->location }}</td>
                                        <td>
                                            <span class="badge px-3 py-2 rounded-pill fw-semibold badge-status-{{ strtolower($e->status) }}">
                                                {{ $e->status }}
                                            </span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No recent events logged.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory & Stock Pane -->
            <div class="tab-pane fade" id="inventory-pane" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-5">
                        <h5 class="fw-bold mb-3 text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Low Stock Warnings</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Qty in Hand</th>
                                        <th>Min Threshold</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($inventoryLowStockList as $i)
                                    <tr class="table-warning">
                                        <td><strong>{{ $i->item_name }}</strong></td>
                                        <td class="text-danger fw-bold">{{ number_format($i->quantity, 2) }} {{ $i->unit }}</td>
                                        <td class="text-muted">{{ number_format($i->minimum_threshold, 2) }} {{ $i->unit }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-success fw-semibold">
                                            <i class="bi bi-check-circle me-1"></i> All stock levels are above warning thresholds.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h5 class="fw-bold mb-3 text-dark">Recent Stock Ledger Activity (Last 30 Transactions)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Item</th>
                                        <th>Action</th>
                                        <th>Qty</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($inventoryTransactionsList as $tx)
                                    <tr>
                                        <td>{{ date('d M H:i', strtotime($tx->created_at)) }}</td>
                                        <td><strong>{{ $tx->item_name }}</strong></td>
                                        <td>
                                            @if($tx->transaction_type === 'Restock')
                                            <span class="badge bg-success rounded-pill px-2 py-1">Restock</span>
                                            @elseif($tx->transaction_type === 'Consume')
                                            <span class="badge bg-danger rounded-pill px-2 py-1">Consume</span>
                                            @else
                                            <span class="badge bg-secondary rounded-pill px-2 py-1">Adjust</span>
                                            @endif
                                        </td>
                                        <td class="fw-bold text-{{ $tx->transaction_type === 'Restock' ? 'success' : 'danger' }}">
                                            {{ $tx->transaction_type === 'Restock' ? '+' : '-' }}{{ number_format($tx->quantity, 2) }}
                                        </td>
                                        <td class="small text-muted">{{ $tx->remarks }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">No stock movements recorded.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
