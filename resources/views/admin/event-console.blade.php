<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Console · {{ $event->event_name }}</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <style>
        :root {
            --gold: #b8863a;
            --gold-light: #e0ac4f;
            --teal: #0f9d6a;
            --ink: #22201c;
            --muted: #857a6b;
            --cream: #faf6ef;
            --card-line: rgba(184, 134, 58, 0.1);
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--cream); color: var(--ink); }

        .console-topbar {
            background: linear-gradient(135deg, #241a0d, #3d2c17);
            color: white;
            padding: 18px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 40;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .console-topbar .back-link { color: rgba(255,255,255,0.6); font-size: 0.82rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .console-topbar .back-link:hover { color: white; }
        .console-topbar h1 { font-size: 1.4rem; font-weight: 800; margin: 2px 0 0; letter-spacing: -0.01em; }
        .console-tabs { display: flex; gap: 8px; flex-wrap: wrap; background: rgba(255,255,255,0.06); padding: 6px; border-radius: 18px; }
        .console-tab-btn {
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.75);
            padding: 12px 22px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .console-tab-btn.active { background: linear-gradient(135deg, var(--gold), var(--gold-light)); color: white; box-shadow: 0 6px 16px rgba(184,134,58,0.35); }
        .console-tab-btn:not(.active):hover { background: rgba(255,255,255,0.08); color: white; }
        .btn-fullscreen { background: rgba(255,255,255,0.12); border: none; color: white; padding: 12px 20px; border-radius: 40px; font-weight: 700; font-size: 0.85rem; }
        .btn-fullscreen:hover { background: rgba(255,255,255,0.2); color: white; }

        .console-body { padding: 28px; max-width: 1500px; margin: 0 auto 100px; }
        .console-pane { display: none; }
        .console-pane.active { display: block; animation: fadeIn 0.25s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        .console-card { background: white; border-radius: 26px; padding: 26px; box-shadow: 0 10px 34px rgba(34,32,28,0.06); margin-bottom: 22px; border: 1px solid var(--card-line); }

        .stat-tile { background: white; border-radius: 22px; padding: 22px; box-shadow: 0 10px 30px rgba(34,32,28,0.05); border: 1px solid var(--card-line); display: flex; align-items: center; gap: 16px; }
        .stat-tile .stat-icon { width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; flex-shrink: 0; }
        .stat-tile .label { color: var(--muted); font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
        .stat-tile .value { font-size: 1.5rem; font-weight: 800; color: var(--ink); margin-top: 2px; }

        .quick-entry-toggle { display: flex; gap: 12px; margin-bottom: 22px; }
        .quick-entry-toggle button { flex: 1; padding: 16px; border-radius: 18px; border: 2px solid #f0ece6; background: white; font-weight: 700; font-size: 1.05rem; color: var(--muted); transition: 0.2s; }
        .quick-entry-toggle button.active { border-color: var(--gold); background: linear-gradient(135deg, #fdf6ea, #fbeed6); color: var(--gold); }

        .qe-field label { font-weight: 700; font-size: 0.9rem; margin-bottom: 6px; display: block; color: var(--ink); }
        .qe-field input, .qe-field select, .qe-field textarea { padding: 16px; font-size: 1.1rem; border-radius: 16px; border: 2px solid #f0ece6; width: 100%; }
        .qe-field input:focus, .qe-field select:focus, .qe-field textarea:focus { border-color: var(--gold); outline: none; box-shadow: 0 0 0 4px rgba(184,134,58,0.1); }

        .quick-amount-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .quick-amount-btn { background: #f5f0e6; border: 2px solid transparent; color: var(--gold); font-weight: 700; padding: 10px 18px; border-radius: 14px; font-size: 0.95rem; transition: 0.15s; }
        .quick-amount-btn:hover, .quick-amount-btn.active { background: var(--gold); color: white; }

        .devotee-combobox-wrap { position: relative; }
        .devotee-combobox-results { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e5ddd0; border-radius: 16px; max-height: 280px; overflow-y: auto; z-index: 20; box-shadow: 0 16px 40px rgba(0,0,0,0.12); display: none; margin-top: 6px; }
        .devotee-combobox-results.show { display: block; }
        .devotee-combobox-item { padding: 14px 18px; cursor: pointer; border-bottom: 1px solid #f5f0e6; }
        .devotee-combobox-item:hover, .devotee-combobox-item.highlighted { background: #fdf6ea; }

        .donation-tier-option { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px; border: 2px solid #f0ece6; border-radius: 18px; margin-bottom: 12px; cursor: pointer; transition: 0.15s; flex-wrap: wrap; }
        .donation-tier-option.selected { border-color: var(--gold); background: linear-gradient(135deg, #fdf9f2, #fbf3e2); }
        .donation-tier-option label { font-size: 1.05rem; margin: 0; cursor: pointer; }
        .donation-tier-option input[type="checkbox"] { width: 22px; height: 22px; }

        .btn-save-next { position: fixed; bottom: 0; left: 0; right: 0; padding: 20px 28px; background: white; border-top: 1px solid var(--card-line); box-shadow: 0 -10px 30px rgba(0,0,0,0.06); z-index: 30; }
        .btn-save-next button { width: 100%; max-width: 1444px; margin: 0 auto; display: block; padding: 20px; font-size: 1.25rem; font-weight: 800; background: linear-gradient(135deg, var(--gold), var(--gold-light)); color: white; border: none; border-radius: 20px; box-shadow: 0 10px 24px rgba(184,134,58,0.3); }
        .btn-save-next button:disabled { opacity: 0.6; }

        .qe-toast { position: fixed; bottom: 100px; right: 24px; background: var(--teal); color: white; padding: 18px 26px; border-radius: 16px; font-weight: 700; box-shadow: 0 14px 34px rgba(0,0,0,0.18); z-index: 999; display: none; font-size: 1.05rem; }
        .qe-toast.error { background: #dc3545; }

        table.console-table { width: 100%; border-collapse: collapse; }
        table.console-table th, table.console-table td { padding: 14px 14px; text-align: left; font-size: 0.87rem; border-bottom: 1px solid #f5f0e6; }
        table.console-table th { background: #faf5eb; font-weight: 800; color: var(--muted); text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.04em; position: sticky; top: 0; }
        table.console-table tbody tr:hover { background: #fefcf8; }
        .btn-refresh { background: white; border: 1px solid var(--card-line); padding: 10px 22px; border-radius: 40px; font-weight: 700; font-size: 0.88rem; color: var(--ink); }
        .btn-refresh:hover { background: #f5f0e6; }

        .btn-action-edit, .btn-action-delete, .btn-action-resend, .btn-action-approve, .btn-action-checkstatus {
            border: none; padding: 7px 14px; border-radius: 40px; font-weight: 700; font-size: 0.72rem; transition: 0.2s;
            display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; margin: 2px;
        }
        .btn-action-edit { background: rgba(184,134,58,0.1); color: var(--gold); }
        .btn-action-edit:hover { background: var(--gold); color: white; }
        .btn-action-delete { background: rgba(220,53,69,0.1); color: #dc3545; }
        .btn-action-delete:hover { background: #dc3545; color: white; }
        .btn-action-resend { background: rgba(42,111,219,0.1); color: #2a6fdb; }
        .btn-action-resend:hover { background: #2a6fdb; color: white; }
        .btn-action-approve { background: rgba(15,157,106,0.1); color: var(--teal); }
        .btn-action-approve:hover { background: var(--teal); color: white; }
        .btn-action-checkstatus { background: rgba(139,92,246,0.1); color: #8b5cf6; }
        .btn-action-checkstatus:hover { background: #8b5cf6; color: white; }

        .fullscreen-hint { position: fixed; top: 90px; left: 50%; transform: translateX(-50%); background: rgba(34,32,28,0.9); color: white; padding: 10px 22px; border-radius: 40px; font-size: 0.85rem; font-weight: 600; z-index: 200; box-shadow: 0 10px 24px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="console-topbar">
        <div>
            @php $backRoute = session('active_role', auth()->user()->role ?? null) === 'Event Coordinator' ? 'event-coordinator.my-events' : 'admin.events.index'; @endphp
            <a href="{{ route($backRoute) }}" class="back-link"><i class="bi bi-arrow-left"></i>Back to {{ $backRoute === 'event-coordinator.my-events' ? 'My Events' : 'Events' }}</a>
            <h1>{{ $event->event_name }}</h1>
        </div>
        <div class="console-tabs">
            <button type="button" class="console-tab-btn active" data-pane="pane-table"><i class="bi bi-table"></i> Donations</button>
            @if($canAddDonation)
            <button type="button" class="console-tab-btn" data-pane="pane-entry"><i class="bi bi-lightning-charge-fill"></i> Quick Entry</button>
            @endif
            <button type="button" class="console-tab-btn" data-pane="pane-dashboard"><i class="bi bi-pie-chart-fill"></i> Dashboard</button>
        </div>
        <button type="button" class="btn-fullscreen" id="fullscreenBtn"><i class="bi bi-arrows-fullscreen me-1"></i>Fullscreen</button>
    </div>

    <div class="console-body">
        <!-- DONATIONS TABLE -->
        <div class="console-pane active" id="pane-table">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:var(--teal);"><i class="bi bi-cash-coin"></i></div><div><div class="label">Paid Total</div><div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['paid_total'], 2) }}</div></div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:#e0a638;"><i class="bi bi-hourglass-split"></i></div><div><div class="label">Pending Total</div><div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['pending_total'], 2) }}</div></div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:var(--gold);"><i class="bi bi-check2-circle"></i></div><div><div class="label">Paid Donations</div><div class="value">{{ $summary['paid_count'] }}</div></div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:#8b5cf6;"><i class="bi bi-people-fill"></i></div><div><div class="label">Total Donations</div><div class="value">{{ $summary['donation_count'] }}</div></div></div>
                </div>
            </div>
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn-refresh" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
            </div>
            <div class="console-card" style="overflow-x:auto; padding:0;">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>Type</th><th>ID</th><th>Name</th><th>Contact</th>
                            @foreach($options as $opt)<th>{{ $opt->label }}</th>@endforeach
                            <th>Other</th><th>Total</th><th>Payment</th><th>Txn ID</th><th>Date</th><th>Status</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->donation_type === 'devotee' ? 'Devotee' : 'Guest' }}</td>
                            <td><strong>{{ $row->display_id }}</strong></td>
                            <td>{{ $row->display_name }}</td>
                            <td>{{ $row->mobile ?? $row->email ?? '—' }}</td>
                            @foreach($options as $opt)
                            <td>@if(($row->option_amounts[$opt->id] ?? 0) > 0){{ number_format($row->option_amounts[$opt->id], 2) }}@else — @endif</td>
                            @endforeach
                            <td>@if($row->other_amount > 0){{ number_format($row->other_amount, 2) }}@else — @endif</td>
                            <td><strong>{{ number_format($row->amount, 2) }}</strong></td>
                            <td>{{ $row->payment_method }}</td>
                            <td>{{ $row->transaction_id }}</td>
                            <td>{{ date('d M Y', strtotime($row->donation_date)) }}</td>
                            <td>{{ $row->payment_status }}</td>
                            <td class="text-end">
                                @include('admin.partials.donation-actions', ['row' => $row])
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ 8 + $options->count() }}" class="text-center text-muted py-5">No donations recorded for this event yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($canAddDonation)
        <!-- QUICK ENTRY -->
        <div class="console-pane" id="pane-entry">
            <div class="console-card">
                <div class="quick-entry-toggle">
                    <button type="button" class="active" id="qeToggleDevotee"><i class="bi bi-person-check-fill me-1"></i>Existing Devotee</button>
                    <button type="button" id="qeToggleGuest"><i class="bi bi-person-heart me-1"></i>Guest</button>
                </div>

                <div id="qeDevoteeFields">
                    <div class="qe-field mb-3 devotee-combobox-wrap">
                        <label>Search Devotee (name, email, or mobile)</label>
                        <input type="text" class="form-control" id="qeDevoteeSearch" placeholder="Start typing...">
                        <input type="hidden" id="qeDevoteeId">
                        <div class="devotee-combobox-results" id="qeDevoteeResults"></div>
                    </div>
                </div>

                <div id="qeGuestFields" style="display:none;">
                    <div class="qe-field mb-3">
                        <label>Donor Name</label>
                        <input type="text" class="form-control" id="qeGuestName">
                    </div>
                    <div class="row">
                        <div class="col-md-6 qe-field mb-3">
                            <label>Email</label>
                            <input type="email" class="form-control" id="qeGuestEmail">
                        </div>
                        <div class="col-md-6 qe-field mb-3">
                            <label>Mobile</label>
                            <input type="text" class="form-control" id="qeGuestMobile">
                        </div>
                    </div>
                </div>

                <div id="qeTiers" class="mb-3"></div>
                <div class="qe-field mb-3">
                    <label>Amount</label>
                    <input type="number" step="0.01" class="form-control" id="qeAmount" placeholder="0.00">
                    <div class="quick-amount-row" id="qeQuickAmounts"></div>
                </div>
                <div class="qe-field mb-3">
                    <label>Details (optional)</label>
                    <textarea id="qeDetails" rows="2" placeholder="Any extra detail about this donation..."></textarea>
                </div>
                <div class="qe-field mb-3">
                    <label>Payment Method</label>
                    <select class="form-select" id="qePaymentMethod"></select>
                </div>
                <div class="qe-field mb-4">
                    <label>Transaction ID (optional)</label>
                    <input type="text" class="form-control" id="qeTransactionId">
                </div>
            </div>
        </div>
        @endif

        <!-- DASHBOARD -->
        <div class="console-pane" id="pane-dashboard">
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn-refresh" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:var(--teal);"><i class="bi bi-cash-coin"></i></div><div><div class="label">Paid Total</div><div class="value">{{ number_format($summary['paid_total'], 2) }}</div></div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:#e0a638;"><i class="bi bi-hourglass-split"></i></div><div><div class="label">Pending Total</div><div class="value">{{ number_format($summary['pending_total'], 2) }}</div></div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:var(--gold);"><i class="bi bi-check2-circle"></i></div><div><div class="label">Paid Donations</div><div class="value">{{ $summary['paid_count'] }}</div></div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile"><div class="stat-icon" style="background:#8b5cf6;"><i class="bi bi-people-fill"></i></div><div><div class="label">Total Donations</div><div class="value">{{ $summary['donation_count'] }}</div></div></div>
                </div>
            </div>
            <div class="console-card">
                <h6 class="fw-bold mb-3">By Option (Paid)</h6>
                @forelse($options as $opt)
                <div class="d-flex justify-content-between border-bottom py-3">
                    <span>{{ $opt->label }}</span>
                    <strong>{{ number_format($summary['option_totals'][$opt->id] ?? 0, 2) }}</strong>
                </div>
                @empty
                <p class="text-muted mb-0">No donation options configured for this event.</p>
                @endforelse
            </div>
        </div>
    </div>

    @if($canAddDonation)
    <div class="btn-save-next" id="paneEntryFooter" style="display:none;">
        <button type="button" id="qeSaveBtn"><i class="bi bi-lightning-charge-fill me-2"></i>Save &amp; Next</button>
    </div>
    @endif

    <div class="qe-toast" id="qeToast"></div>

    <!-- EDIT MODALS (devotee + guest) — same fields as the main Manage Donations page -->
    @if($canEditDonation)
        @foreach($rows->where('donation_type', 'devotee') as $row)
        <div class="modal fade" id="editDevoteeDonationModal{{ $row->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form action="{{ route('admin.donations.updateDevotee', $row->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Devotee Donation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Devotee</label>
                                <input type="text" class="form-control rounded-3" value="{{ $row->display_name }}" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donation Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control rounded-3" value="{{ $row->amount }}" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Mode</label>
                                    <select name="payment_mode" class="form-select rounded-3" required>
                                        @foreach(['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'Stripe'] as $mode)
                                            <option value="{{ $mode }}" {{ $row->payment_method === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Status</label>
                                    <select name="payment_status" class="form-select rounded-3" required>
                                        @foreach(['Paid', 'Pending', 'Cancelled', 'Failed'] as $status)
                                            <option value="{{ $status }}" {{ $row->payment_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="event_id" value="{{ $event->event_id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control rounded-3" value="{{ $row->transaction_id }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donation Date</label>
                                <input type="date" name="donation_date" class="form-control rounded-3" value="{{ $row->donation_date }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donation Option / Purpose</label>
                                <input type="text" name="purpose" class="form-control rounded-3" value="{{ $row->purpose }}">
                                @if($options->count())
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($options as $opt)
                                    <span class="option-chip" style="background:#faf5eb;border:1px solid #f0ece6;color:#7b6b5a;font-size:.75rem;padding:4px 12px;border-radius:40px;cursor:pointer;" onclick="appendOptionChipEc(this, 'input[name=purpose]')">{{ $opt->label }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Details / Remarks</label>
                                <textarea name="remarks" rows="2" class="form-control rounded-3">{{ $row->remarks }}</textarea>
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

        @foreach($rows->where('donation_type', 'guest') as $row)
        <div class="modal fade" id="editGuestDonationModal{{ $row->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form action="{{ route('admin.donations.updateGuest', $row->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Guest Donation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donor Full Name</label>
                                <input type="text" name="donor_name" class="form-control rounded-3" value="{{ $row->donor_name }}" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input type="email" name="email" class="form-control rounded-3" value="{{ $row->email }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Mobile</label>
                                    <input type="text" name="mobile" class="form-control rounded-3" value="{{ $row->mobile }}">
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Donation Amount</label>
                                    <input type="number" step="0.01" name="amount" class="form-control rounded-3" value="{{ $row->amount }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Donation Date</label>
                                    <input type="date" name="donation_date" class="form-control rounded-3" value="{{ $row->donation_date }}" required>
                                </div>
                            </div>
                            <input type="hidden" name="event_id" value="{{ $event->event_id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Purpose</label>
                                <input type="text" name="purpose" class="form-control rounded-3" value="{{ $row->purpose }}" required>
                                @if($options->count())
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($options as $opt)
                                    <span class="option-chip" style="background:#faf5eb;border:1px solid #f0ece6;color:#7b6b5a;font-size:.75rem;padding:4px 12px;border-radius:40px;cursor:pointer;" onclick="appendOptionChipEc(this, 'input[name=purpose]')">{{ $opt->label }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Details / Dedication</label>
                                <textarea name="purpose_details" rows="2" class="form-control rounded-3">{{ $row->purpose_details }}</textarea>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Method</label>
                                    <select name="payment_method" class="form-select rounded-3" required>
                                        @foreach(['Cash', 'UPI', 'Bank', 'Stripe'] as $method)
                                            <option value="{{ $method }}" {{ $row->payment_method === $method ? 'selected' : '' }}>{{ $method }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Status</label>
                                    <select name="payment_status" class="form-select rounded-3" required>
                                        @foreach(['Paid', 'Pending', 'Cancelled', 'Failed'] as $status)
                                            <option value="{{ $status }}" {{ $row->payment_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control rounded-3" value="{{ $row->transaction_id }}">
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

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        function appendOptionChipEc(chipEl, selector) {
            const scope = chipEl.closest('.modal-body') || document;
            const input = scope.querySelector(selector);
            if (!input) { return; }
            const label = chipEl.textContent.trim();
            const existing = input.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            if (!existing.includes(label)) { existing.push(label); }
            input.value = existing.join(', ');
        }

        // Tab switching
        const entryFooter = document.getElementById('paneEntryFooter');
        document.querySelectorAll('.console-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.console-tab-btn').forEach(function (b) { b.classList.remove('active'); });
                document.querySelectorAll('.console-pane').forEach(function (p) { p.classList.remove('active'); });
                this.classList.add('active');
                document.getElementById(this.dataset.pane).classList.add('active');
                if (entryFooter) { entryFooter.style.display = (this.dataset.pane === 'pane-entry') ? 'block' : 'none'; }
            });
        });

        // Fullscreen: explicit button, plus a one-tap-anywhere fallback so the console
        // effectively "opens in fullscreen" as soon as the admin starts using it — browsers
        // block requestFullscreen() from firing automatically on page load with no user
        // gesture, so the very first tap on the page is used to satisfy that requirement.
        function goFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(function () {});
            }
        }
        document.getElementById('fullscreenBtn').addEventListener('click', function () {
            if (!document.fullscreenElement) { goFullscreen(); } else { document.exitFullscreen(); }
        });
        if (!document.fullscreenElement) {
            const hint = document.createElement('div');
            hint.className = 'fullscreen-hint';
            hint.textContent = 'Tap anywhere to enter fullscreen';
            document.body.appendChild(hint);
            document.body.addEventListener('click', function onceHandler() {
                goFullscreen();
                hint.remove();
                document.body.removeEventListener('click', onceHandler);
            }, { once: true });
            setTimeout(function () { hint.remove(); }, 4000);
        }

        @if($canAddDonation)
        const DEVOTEES = @json($devotees);
        const EVENT_OPTIONS = @json($eventOptionsForJs);
        const ENABLED_PAYMENT_METHODS = @json($enabledPaymentMethods);
        const CSRF_TOKEN = @json(csrf_token());
        const STORE_DEVOTEE_URL = @json(route('admin.events.console.storeDevotee', $event->event_id));
        const STORE_GUEST_URL = @json(route('admin.events.console.storeGuest', $event->event_id));
        const EVENT_ID = {{ $event->event_id }};
        const QUICK_AMOUNTS = [101, 501, 1001, 2001];

        let qeMode = 'devotee';
        const toggleDevoteeBtn = document.getElementById('qeToggleDevotee');
        const toggleGuestBtn = document.getElementById('qeToggleGuest');
        const devoteeFields = document.getElementById('qeDevoteeFields');
        const guestFields = document.getElementById('qeGuestFields');

        toggleDevoteeBtn.addEventListener('click', function () {
            qeMode = 'devotee';
            toggleDevoteeBtn.classList.add('active');
            toggleGuestBtn.classList.remove('active');
            devoteeFields.style.display = '';
            guestFields.style.display = 'none';
        });
        toggleGuestBtn.addEventListener('click', function () {
            qeMode = 'guest';
            toggleGuestBtn.classList.add('active');
            toggleDevoteeBtn.classList.remove('active');
            guestFields.style.display = '';
            devoteeFields.style.display = 'none';
        });

        // Payment method select, respecting the configured enabled list.
        const paymentSelect = document.getElementById('qePaymentMethod');
        (ENABLED_PAYMENT_METHODS.length ? ENABLED_PAYMENT_METHODS : ['Cash']).forEach(function (m) {
            const opt = document.createElement('option');
            opt.value = m === 'Bank Transfer' ? 'Bank Transfer' : m;
            opt.textContent = m;
            paymentSelect.appendChild(opt);
        });

        // Devotee search combobox — client-side filter over a pre-loaded array.
        const devoteeSearch = document.getElementById('qeDevoteeSearch');
        const devoteeResults = document.getElementById('qeDevoteeResults');
        const devoteeIdInput = document.getElementById('qeDevoteeId');

        devoteeSearch.addEventListener('input', function () {
            devoteeIdInput.value = '';
            const q = this.value.trim().toLowerCase();
            if (q.length < 2) { devoteeResults.classList.remove('show'); return; }
            const matches = DEVOTEES.filter(function (d) {
                return (d.name && d.name.toLowerCase().includes(q))
                    || (d.email && d.email.toLowerCase().includes(q))
                    || (d.mobile && d.mobile.toLowerCase().includes(q));
            }).slice(0, 15);
            if (!matches.length) { devoteeResults.classList.remove('show'); return; }
            devoteeResults.innerHTML = matches.map(function (d) {
                return '<div class="devotee-combobox-item" data-id="' + d.devotee_id + '" data-name="' + escapeHtmlQe(d.name) + '">'
                    + '<div class="fw-semibold">' + escapeHtmlQe(d.name) + '</div>'
                    + '<div class="text-muted small">' + escapeHtmlQe(d.email || '') + (d.mobile ? ' · ' + escapeHtmlQe(d.mobile) : '') + '</div></div>';
            }).join('');
            devoteeResults.classList.add('show');
        });
        devoteeResults.addEventListener('click', function (e) {
            const item = e.target.closest('.devotee-combobox-item');
            if (!item) { return; }
            devoteeIdInput.value = item.dataset.id;
            devoteeSearch.value = item.dataset.name;
            devoteeResults.classList.remove('show');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.devotee-combobox-wrap')) { devoteeResults.classList.remove('show'); }
        });

        function escapeHtmlQe(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        // Quick-amount preset buttons for the manual Amount field.
        const amountInput = document.getElementById('qeAmount');
        const quickAmountsRow = document.getElementById('qeQuickAmounts');
        QUICK_AMOUNTS.forEach(function (amt) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'quick-amount-btn';
            btn.textContent = amt;
            btn.addEventListener('click', function () {
                amountInput.value = amt.toFixed(2);
                amountInput.dispatchEvent(new Event('input'));
                quickAmountsRow.querySelectorAll('.quick-amount-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
            });
            quickAmountsRow.appendChild(btn);
        });
        amountInput.addEventListener('input', function () {
            quickAmountsRow.querySelectorAll('.quick-amount-btn').forEach(function (b) {
                b.classList.toggle('active', parseFloat(b.textContent) === parseFloat(amountInput.value));
            });
        });

        // Tier picker — same computation pattern as the admin Add Donation modals, laid out
        // full-width/touch-friendly here. Each free-amount tier also gets its own
        // quick-amount row.
        const tiersContainer = document.getElementById('qeTiers');
        let selections = [];
        // Default so a manually-typed amount (no tier checked) still submits a valid
        // purpose — matches the public donate-form's own "no tier selected" fallback.
        let purposeValue = 'Event Donation';

        if (EVENT_OPTIONS.length) {
            let html = '';
            EVENT_OPTIONS.forEach(function (opt, idx) {
                const hasAmount = opt.amount !== null;
                html += '<div class="donation-tier-option" data-idx="' + idx + '">'
                    + '<label class="d-flex align-items-center gap-2 mb-0" style="flex:1; min-width:200px;">'
                    + '<input type="checkbox" class="tier-cb" data-idx="' + idx + '">'
                    + '<span><strong>' + escapeHtmlQe(opt.label) + '</strong><br><span class="text-muted small">'
                    + (hasAmount ? (opt.amount.toFixed(2) + (opt.allow_quantity ? ' each' : '')) : 'Any amount')
                    + '</span></span></label>'
                    + (opt.allow_quantity ? '<input type="number" min="1" value="1" class="form-control form-control-sm tier-qty" style="width:80px; display:none;">' : '')
                    + (!hasAmount ? '<div class="d-flex flex-column gap-1"><input type="number" min="0" step="0.01" placeholder="Amount" class="form-control form-control-sm tier-free" style="width:140px;">'
                        + '<div class="d-flex gap-1 tier-quick-amounts"></div></div>' : '')
                    + '</div>';
            });
            tiersContainer.innerHTML = html;

            // Wire up quick-amount mini-buttons for each free-amount tier row.
            tiersContainer.querySelectorAll('.donation-tier-option').forEach(function (row) {
                const quickWrap = row.querySelector('.tier-quick-amounts');
                if (!quickWrap) { return; }
                const freeInput = row.querySelector('.tier-free');
                const cb = row.querySelector('.tier-cb');
                QUICK_AMOUNTS.forEach(function (amt) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'quick-amount-btn';
                    b.style.padding = '4px 10px';
                    b.style.fontSize = '0.78rem';
                    b.textContent = amt;
                    b.addEventListener('click', function () {
                        cb.checked = true;
                        freeInput.value = amt.toFixed(2);
                        freeInput.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    quickWrap.appendChild(b);
                });
            });

            function recalcTiers() {
                let total = 0;
                const labels = [];
                selections = [];
                tiersContainer.querySelectorAll('.donation-tier-option').forEach(function (row) {
                    const idx = row.dataset.idx;
                    const cb = row.querySelector('.tier-cb');
                    const qtyInput = row.querySelector('.tier-qty');
                    const freeInput = row.querySelector('.tier-free');
                    if (qtyInput) { qtyInput.style.display = cb.checked ? 'inline-block' : 'none'; }
                    row.classList.toggle('selected', cb.checked);
                    if (!cb.checked) { return; }
                    const opt = EVENT_OPTIONS[idx];
                    let label = opt.label;
                    let qty = null;
                    let amount = 0;
                    if (opt.amount !== null) {
                        qty = (qtyInput && opt.allow_quantity) ? (parseInt(qtyInput.value, 10) || 1) : 1;
                        amount = opt.amount * qty;
                        if (opt.allow_quantity && qty > 1) { label += ' (x' + qty + ')'; }
                    } else {
                        amount = freeInput ? (parseFloat(freeInput.value) || 0) : 0;
                    }
                    if (amount > 0) {
                        total += amount;
                        labels.push(label);
                        selections.push({ option_id: opt.id, label: label, quantity: qty, amount: amount });
                    }
                });
                amountInput.value = total > 0 ? total.toFixed(2) : '';
                purposeValue = labels.length ? labels.join(', ').substring(0, 250) : 'Event Donation';
            }

            tiersContainer.addEventListener('change', recalcTiers);
            tiersContainer.addEventListener('input', recalcTiers);
        } else {
            purposeValue = 'Event Donation';
        }

        function showToast(message, isError) {
            const toast = document.getElementById('qeToast');
            toast.textContent = message;
            toast.classList.toggle('error', !!isError);
            toast.style.display = 'block';
            setTimeout(function () { toast.style.display = 'none'; }, 2500);
        }

        function resetQuickEntry() {
            devoteeSearch.value = '';
            devoteeIdInput.value = '';
            document.getElementById('qeGuestName').value = '';
            document.getElementById('qeGuestEmail').value = '';
            document.getElementById('qeGuestMobile').value = '';
            document.getElementById('qeTransactionId').value = '';
            document.getElementById('qeDetails').value = '';
            amountInput.value = '';
            quickAmountsRow.querySelectorAll('.quick-amount-btn').forEach(function (b) { b.classList.remove('active'); });
            tiersContainer.querySelectorAll('.tier-cb').forEach(function (cb) { cb.checked = false; });
            tiersContainer.querySelectorAll('.tier-free').forEach(function (i) { i.value = ''; });
            tiersContainer.querySelectorAll('.donation-tier-option').forEach(function (row) { row.classList.remove('selected'); });
            selections = [];
            purposeValue = 'Event Donation';
        }

        document.getElementById('qeSaveBtn').addEventListener('click', function () {
            const amount = parseFloat(amountInput.value);
            if (!amount || amount <= 0) { showToast('Enter a valid amount.', true); return; }

            const btn = this;
            btn.disabled = true;

            const today = new Date().toISOString().slice(0, 10);
            const body = new URLSearchParams();
            body.set('event_id', EVENT_ID);
            body.set('amount', amount.toFixed(2));
            body.set('payment_method', paymentSelect.value);
            body.set('transaction_id', document.getElementById('qeTransactionId').value);
            body.set('selections_json', JSON.stringify(selections));
            body.set('donation_date', today);

            let url;
            if (qeMode === 'devotee') {
                if (!devoteeIdInput.value) { showToast('Search and select a devotee first.', true); btn.disabled = false; return; }
                url = STORE_DEVOTEE_URL;
                body.set('devotee_id', devoteeIdInput.value);
                body.set('payment_mode', paymentSelect.value);
                body.set('purpose', purposeValue);
                body.set('remarks', document.getElementById('qeDetails').value);
            } else {
                const name = document.getElementById('qeGuestName').value.trim();
                if (!name) { showToast('Enter the donor name.', true); btn.disabled = false; return; }
                url = STORE_GUEST_URL;
                body.set('donor_name', name);
                body.set('email', document.getElementById('qeGuestEmail').value);
                body.set('mobile', document.getElementById('qeGuestMobile').value);
                body.set('purpose', purposeValue);
                body.set('purpose_details', document.getElementById('qeDetails').value);
            }

            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    btn.disabled = false;
                    if (result.status >= 200 && result.status < 300 && result.data.success) {
                        showToast(result.data.message || 'Saved.');
                        resetQuickEntry();
                    } else {
                        showToast(result.data.message || 'Failed to save.', true);
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    showToast('Network error — please try again.', true);
                });
        });
        @endif
    </script>
</body>
</html>
