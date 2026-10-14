{{-- Shared by the Event Console, Ticket Console, Event POS and Ticket POS pages (the last
     two are standalone HTML documents with their own styling, no shared layout) — so this
     partial is deliberately self-contained: only plain Bootstrap classes (always loaded on
     all four) plus its own small scoped <style> block, nothing that depends on a class any
     particular host page happens to define.
     This is the live, not-yet-settled period only — a settlement/method/status filterable
     view of EVERY transaction (not just the current period) lives on the Portal's own All
     Transactions pane instead (event-console.blade.php), not here.
     $context ('pos'|'console', default 'console') — on the POS page's Settle Till popup,
     nobody (not even admin/entry) sees settlement history, just the one-line "last settled/
     carried forward" fact plus the till-count form itself; the Console's own Settlements
     pane is where full history (and editing a past settlement's figures) actually lives.
     Expects: $scope ('event'|'tickets'), $eventId (nullable), $cashPreview (array from
     CashSettlementService::preview()), $cashBankings, $cashSettlements (collections —
     empty when $canViewCashHistory is false), $canViewCashHistory (bool — false for a
     pos-level event coordinator; see CashSettlementService::canViewHistory()),
     $currentTransactions, $lastSettlementDate (nullable Carbon), $temple. --}}
@php
    $currency = $temple['currency'] ?? '';
    $fmt = fn ($n) => $currency . ' ' . number_format((float) $n, 2);
    $expected = round($cashPreview['opening_balance'] + $cashPreview['cash_received'], 2);
    $formId = 'settleTillForm_' . $scope . '_' . ($eventId ?? 'tickets');
    $canEditCurrentTransactions = $canEditCurrentTransactions ?? false;
    $context = $context ?? 'console';
    $baseUrl = $scope === 'event' ? route('admin.events.pos', $eventId) : route('admin.tickets.pos');
@endphp

<div class="cash-settle-widget">
<style>
    .cash-settle-widget { font-family: inherit; color: #1e1e2a; }
    .cash-settle-widget .cs-tile { background: #faf8f5; border: 1px solid rgba(184,134,58,0.12); border-radius: 16px; padding: 14px 16px; height: 100%; }
    .cash-settle-widget .cs-tile .cs-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; margin-bottom: 8px; }
    .cash-settle-widget .cs-tile .cs-label { font-size: 0.74rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #8A6A1E; }
    .cash-settle-widget .cs-tile .cs-value { font-size: 1.3rem; font-weight: 800; margin-top: 2px; font-variant-numeric: tabular-nums; }
    .cash-settle-widget .cs-card { background: #fff; border: 1px solid rgba(184,134,58,0.1); border-radius: 18px; padding: 18px 20px; }
    .cash-settle-widget .btn-cs-save { background: #8A6A1E; color: #fff; border: none; }
    .cash-settle-widget .btn-cs-save:hover { color: #fff; background: #6f5518; }
    .cash-settle-widget .cs-table-wrap { background: #fff; border: 1px solid rgba(184,134,58,0.1); border-radius: 18px; overflow: hidden; }
    .cash-settle-widget .cs-table-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 16px 20px; border-bottom: 1px solid #f0ece6; }
    .cash-settle-widget .cs-search-wrap { position: relative; }
    .cash-settle-widget .cs-search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #a89a86; font-size: 0.85rem; }
    .cash-settle-widget .cs-search-input { border: 1px solid #e8e0d4; border-radius: 40px; padding: 7px 14px 7px 32px; font-size: 0.85rem; min-width: 220px; background: #faf8f5; }
    .cash-settle-widget .cs-search-input:focus { outline: none; border-color: #C89B3C; background: #fff; }
    .cash-settle-widget .cs-table { width: 100%; border-collapse: collapse; margin: 0; }
    .cash-settle-widget .cs-table thead th { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #8A6A1E; background: #faf8f5; padding: 10px 20px; text-align: left; border-bottom: 1px solid #f0ece6; }
    .cash-settle-widget .cs-table tbody td { padding: 10px 20px; border-bottom: 1px solid #f5f0ea; font-size: 0.88rem; vertical-align: middle; }
    .cash-settle-widget .cs-table tbody tr:last-child td { border-bottom: none; }
    .cash-settle-widget .cs-table tbody tr.cs-row:hover { background: #faf8f5; }
    .cash-settle-widget .cs-icon-btn { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #e8e0d4; background: #fff; display: inline-flex; align-items: center; justify-content: center; color: #7b6b5a; }
    .cash-settle-widget .cs-icon-btn:hover { background: #faf8f5; border-color: #C89B3C; color: #C89B3C; }
    .cash-settle-widget .cs-icon-btn.cs-save-btn { color: #1f9d6a; border-color: rgba(31,157,106,0.3); }
    .cash-settle-widget .cs-icon-btn.cs-save-btn:hover { background: #f0fbf6; }
    .cash-settle-widget .cs-icon-btn.cs-cancel-btn { color: #c94b4b; border-color: rgba(201,75,75,0.3); }
    .cash-settle-widget .cs-icon-btn.cs-cancel-btn:hover { background: #fdf3f3; }
    .cash-settle-widget .cs-edit-row input.form-control { font-size: 0.85rem; padding: 6px 10px; }
    .cash-settle-widget .cs-empty-state { padding: 40px 20px; text-align: center; color: #a0907e; }
    .cash-settle-widget .cs-badge { display: inline-block; border-radius: 40px; padding: 3px 10px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
    .cash-settle-widget .cs-badge.paid { background: #e7f6ee; color: #1f9d6a; }
    .cash-settle-widget .cs-badge.pending { background: #fdf0db; color: #b8863a; }
    .cash-settle-widget .cs-complete-link { font-size: 0.8rem; font-weight: 700; color: #b8863a; text-decoration: none; white-space: nowrap; }
    .cash-settle-widget .cs-complete-link:hover { color: #8A6A1E; text-decoration: underline; }

    {{-- Compact summary — one line per figure (label left, value right) instead of three
         separate icon tiles, so the whole thing reads in a glance rather than taking up a
         whole row of tall cards. --}}
    .cash-settle-widget .cs-summary-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 4px; border-bottom: 1px solid #f0ece6; }
    .cash-settle-widget .cs-summary-row:last-child { border-bottom: none; }
    .cash-settle-widget .cs-summary-row-label { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 600; color: #6b5c47; }
    .cash-settle-widget .cs-summary-row-label i { font-size: 0.95rem; width: 18px; text-align: center; }
    .cash-settle-widget .cs-summary-row-value { font-weight: 800; font-size: 1.02rem; font-variant-numeric: tabular-nums; }

    .cash-settle-widget .cs-row.cs-row-clickable { cursor: pointer; }

    {{-- Settle Till form — a compact, ledger-style layout (small bordered fields with a
         currency prefix, tabular figures) instead of full-size Bootstrap form-controls,
         which read as an ordinary web form rather than an audit/reconciliation tool. --}}
    .cash-settle-widget .cs-audit-field label { display: block; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #8A6A1E; margin-bottom: 5px; }
    .cash-settle-widget .cs-audit-input-wrap { display: flex; align-items: stretch; border: 1px solid #d8cdb8; border-radius: 4px; background: #fff; overflow: hidden; }
    .cash-settle-widget .cs-audit-input-wrap:focus-within { border-color: #C89B3C; box-shadow: 0 0 0 3px rgba(200,155,60,0.15); }
    .cash-settle-widget .cs-audit-currency { padding: 7px 9px; background: #faf8f5; color: #8A6A1E; font-size: 0.76rem; font-weight: 700; border-right: 1px solid #e8e0d4; display: flex; align-items: center; }
    .cash-settle-widget .cs-audit-input { border: none; padding: 7px 10px; font-size: 0.92rem; font-weight: 700; font-variant-numeric: tabular-nums; width: 100%; outline: none; background: transparent; }
    .cash-settle-widget .cs-audit-stats { display: flex; gap: 24px; padding-top: 2px; }
    .cash-settle-widget .cs-audit-stat-label { display: block; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #8A6A1E; margin-bottom: 3px; }
    .cash-settle-widget .cs-audit-stat-value { font-size: 1.05rem; font-weight: 800; font-variant-numeric: tabular-nums; color: #1e1e2a; }

    {{-- Solid, sharp-cornered — deliberately not the rounded-pill gradient buttons the
         rest of the app uses elsewhere, so a settlement (a formal, final action) reads as
         distinct from an ordinary "Save" button. --}}
    .cash-settle-widget .btn-cs-save { border-radius: 4px; padding: 12px 30px; font-weight: 800; font-size: 0.86rem; text-transform: uppercase; letter-spacing: 0.05em; }

    {{-- Order view popup — details on the left, the real receipt embedded as a tall,
         independently-scrolling list on the right. --}}
    .cs-order-detail-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f0ece6; }
    .cs-order-detail-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #8A6A1E; }
    .cs-order-detail-value { font-weight: 700; font-size: 0.95rem; text-align: right; }
    .cs-order-detail-total { font-size: 1.25rem; color: #1f9d6a; }
    .cs-order-receipt-col { background: #efe9dd; border-left: 1px solid #e8e0d4; height: 75vh; }
    .cs-order-receipt-col iframe { width: 100%; height: 100%; border: 0; display: block; }
    @media (max-width: 767px) { .cs-order-receipt-col { height: 50vh; border-left: none; border-top: 1px solid #e8e0d4; } }
</style>

<div class="cs-table-wrap mb-3">
    <div class="cs-table-head">
        <div class="fw-bold"><i class="bi bi-list-check me-1"></i>Current Period Transactions ({{ $currentTransactions->count() }})</div>
        @if($currentTransactions->isNotEmpty())
        <div class="cs-search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" class="cs-search-input" id="csTxnSearch_{{ $formId }}" placeholder="Search by name or purpose...">
        </div>
        @endif
    </div>

    @if($currentTransactions->isEmpty())
        <div class="cs-empty-state">Nothing recorded in this period yet.</div>
    @else
    <div class="table-responsive">
        <table class="cs-table" id="csTxnTable_{{ $formId }}">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>{{ $scope === 'event' ? 'Donor' : 'Customer' }}</th>
                    <th>{{ $scope === 'event' ? 'Purpose' : 'Order' }}</th>
                    @if($scope === 'event')<th>Method</th><th>Status</th>@else<th>Method</th>@endif
                    <th class="text-end">Amount</th>
                    @if($canEditCurrentTransactions || $scope !== 'event')<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @if($scope === 'event')
                    @foreach($currentTransactions as $row)
                    @php
                        $rowKey = $row->donation_type . '-' . $row->id;
                        $isPending = $row->payment_status === 'Pending';
                        $canComplete = $canEditCurrentTransactions && $isPending;
                    @endphp
                    <tr class="cs-row" data-search="{{ strtolower($row->display_name . ' ' . $row->purpose) }}" data-edit-id="csEdit_{{ $rowKey }}">
                        <td>{{ \Illuminate\Support\Carbon::parse($row->donation_date)->format('d M Y') }}</td>
                        <td>{{ $row->display_name }}</td>
                        <td>{{ $row->purpose ?: '—' }}</td>
                        <td>{{ $row->payment_method }}</td>
                        <td><span class="cs-badge {{ $isPending ? 'pending' : 'paid' }}">{{ $isPending ? 'Pending' : 'Completed' }}</span></td>
                        <td class="text-end fw-bold">{{ $fmt($row->amount) }}</td>
                        @if($canEditCurrentTransactions)
                        <td class="text-end">
                            @if($canComplete && $row->donation_type === 'guest')
                                <a class="cs-complete-link" href="{{ $baseUrl }}?edit_id={{ $row->id }}"><i class="bi bi-arrow-right-circle-fill me-1"></i>Complete in POS</a>
                            @elseif($canComplete && $row->donation_type === 'devotee')
                                <button type="button" class="cs-icon-btn cs-edit-toggle" data-target="csEdit_{{ $rowKey }}" title="Edit"><i class="bi bi-pencil-fill"></i></button>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @if($canComplete && $row->donation_type === 'devotee')
                    <tr class="cs-edit-row" id="csEdit_{{ $rowKey }}" style="display:none;">
                        <td colspan="7">
                            <form action="{{ route('admin.donations.updateDevotee', $row->id) }}" method="POST" class="d-flex gap-2 align-items-end flex-wrap">
                                @csrf
                                <input type="hidden" name="event_id" value="{{ $eventId }}">
                                <input type="hidden" name="payment_mode" value="{{ $row->payment_method }}">
                                <input type="hidden" name="payment_status" value="{{ $row->payment_status }}">
                                <input type="hidden" name="donation_date" value="{{ $row->donation_date }}">
                                <input type="hidden" name="transaction_id" value="{{ $row->transaction_id }}">
                                <input type="hidden" name="remarks" value="{{ $row->remarks }}">
                                <div class="text-muted small flex-shrink-0" style="min-width:140px;" title="Devotee name isn't editable here"><i class="bi bi-lock-fill me-1" style="font-size:0.7rem;"></i>{{ $row->display_name }}</div>
                                <div><input type="text" name="purpose" class="form-control form-control-sm" value="{{ $row->purpose }}" placeholder="Purpose" style="min-width:160px;"></div>
                                <div><input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" value="{{ $row->amount }}" placeholder="Amount" style="width:110px;" required></div>
                                <button type="submit" class="cs-icon-btn cs-save-btn" title="Save"><i class="bi bi-check-lg"></i></button>
                                <button type="button" class="cs-icon-btn cs-cancel-btn cs-edit-cancel" data-target="csEdit_{{ $rowKey }}" title="Cancel"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                @else
                    @foreach($currentTransactions as $row)
                    {{-- Clicking anywhere on the row (or the View icon) opens the order in
                         the popup below — a quick look without leaving the pane. The Print
                         icon is the one remaining direct link, for actually printing. --}}
                    <tr class="cs-row cs-row-clickable" data-search="{{ strtolower(($row->customer_name ?: 'walk-in') . ' order ' . $row->id) }}"
                        data-order-url="{{ route('admin.tickets.print', $row->id) }}" data-order-id="{{ $row->id }}"
                        data-order-date="{{ \Illuminate\Support\Carbon::parse($row->order_date)->format('d M Y') }}"
                        data-order-customer="{{ $row->customer_name ?: 'Walk-in' }}" data-order-method="{{ $row->payment_method }}"
                        data-order-total="{{ $fmt($row->total_amount) }}">
                        <td>{{ \Illuminate\Support\Carbon::parse($row->order_date)->format('d M Y') }}</td>
                        <td>{{ $row->customer_name ?: 'Walk-in' }}</td>
                        <td>Order #{{ $row->id }}</td>
                        <td>{{ $row->payment_method }}</td>
                        <td class="text-end fw-bold">{{ $fmt($row->total_amount) }}</td>
                        <td class="text-end">
                            <button type="button" class="cs-icon-btn me-1 cs-view-order-btn" title="View Order"><i class="bi bi-eye-fill"></i></button>
                            <a href="{{ route('admin.tickets.print', $row->id) }}" target="_blank" class="cs-icon-btn" title="Print Receipt"><i class="bi bi-printer-fill"></i></a>
                        </td>
                    </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- Settlement — the period's cash summary (left) and the till-count form that settles
     it (right) share one card, since they're two views of the same figures rather than
     separate concerns: the form's own "expected"/variance math is computed straight from
     the summary column beside it. --}}
<div class="cs-card mb-3">
    <div class="fw-bold mb-3"><i class="bi bi-clipboard-check-fill me-1"></i>Settlement</div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="cs-summary-row">
                <span class="cs-summary-row-label"><i class="bi bi-box-arrow-in-right" style="color:#C89B3C;"></i>Opening Balance</span>
                <span class="cs-summary-row-value">{{ $fmt($cashPreview['opening_balance']) }}</span>
            </div>
            <div class="cs-summary-row">
                <span class="cs-summary-row-label"><i class="bi bi-cash-coin" style="color:#10B981;"></i>Cash Received</span>
                <span class="cs-summary-row-value">{{ $fmt($cashPreview['cash_received']) }}</span>
            </div>
            <div class="cs-summary-row" style="border-bottom:none;">
                <span class="cs-summary-row-label"><i class="bi bi-calculator-fill" style="color:#B7791F;"></i>Expected in Till</span>
                <span class="cs-summary-row-value">{{ $fmt($expected) }}</span>
            </div>
            @if($context === 'pos' || !$canViewCashHistory)
            <div class="text-muted small mt-2 pt-2 border-top">
                <i class="bi bi-clock-history me-1"></i>
                <span class="fw-bold">Last settled:</span> {{ $lastSettlementDate ? $lastSettlementDate->format('d M Y, g:i A') : 'Never — this is the first period' }}
                &middot; <span class="fw-bold">Carried forward:</span> {{ $fmt($cashPreview['opening_balance']) }}
            </div>
            @endif
        </div>
        <div class="col-md-8">
            <form id="{{ $formId }}" action="{{ route('admin.cash-settlement.run') }}" method="POST">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}">
                @if($eventId)<input type="hidden" name="event_id" value="{{ $eventId }}">@endif
                <div class="row g-3">
                    <div class="col-md-6 cs-audit-field">
                        <label>Till Count — total cash counted</label>
                        <div class="cs-audit-input-wrap">
                            <span class="cs-audit-currency">{{ $currency }}</span>
                            <input type="number" step="0.01" min="0" name="till_count" class="cs-audit-input settle-till-count" data-expected="{{ $expected }}" required>
                        </div>
                    </div>
                    <div class="col-md-6 cs-audit-field">
                        <label>Amount Taken to Banking</label>
                        <div class="cs-audit-input-wrap">
                            <span class="cs-audit-currency">{{ $currency }}</span>
                            <input type="number" step="0.01" min="0" name="amount_banked" class="cs-audit-input settle-amount-banked" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="cs-audit-stats">
                            <div><span class="cs-audit-stat-label">Variance</span><span class="cs-audit-stat-value settle-variance-display">{{ $fmt(0) }}</span></div>
                            <div><span class="cs-audit-stat-label">Carried Forward</span><span class="cs-audit-stat-value settle-carry-display">{{ $fmt(0) }}</span></div>
                        </div>
                    </div>
                    <div class="col-12 settle-note-wrap" style="display:none;">
                        <label class="form-label small">Note — explain the mismatch <span class="text-danger">*</span></label>
                        <textarea name="variance_note" class="form-control settle-note-field" rows="2" maxlength="1000" placeholder="e.g. a donor's change was short, or an extra note was found"></textarea>
                    </div>
                </div>
                @error('variance_note')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                <button type="submit" class="btn-cs-save mt-3" onclick="return confirm('This locks in the till count as a permanent settlement record and starts the next period from what\'s carried forward. Continue?');">Settle Till Now</button>
            </form>
        </div>
    </div>
</div>

@if($canViewCashHistory && $context !== 'pos')
<div class="cs-card mb-3">
    <div class="fw-bold mb-2"><i class="bi bi-journal-check me-1"></i>Settlement History</div>
    @forelse($cashSettlements as $settlement)
        @php $canEditFigures = \App\Services\CashSettlementService::canEditFigures($settlement); @endphp
        <div class="border-bottom py-2">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-bold">{{ $settlement->period_start->format('d M Y') }} &ndash; {{ $settlement->period_end->format('d M Y') }}</div>
                    <div class="text-muted small">
                        Opening {{ $fmt($settlement->opening_balance) }} &rarr; Closing {{ $fmt($settlement->closing_balance) }}
                        @if($settlement->performedBy) &middot; by {{ $settlement->performedBy->name }} @endif
                        &middot; settled {{ $settlement->created_at->format('d M Y, g:i A') }}
                    </div>
                    @if($settlement->till_count !== null)
                    <div class="text-muted small">Till counted {{ $fmt($settlement->till_count) }} &middot; Banked {{ $fmt($settlement->amount_banked) }}</div>
                    @endif
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary settlement-edit-toggle" data-settlement-id="{{ $settlement->id }}">Edit</button>
            </div>
            @if($settlement->variance !== null && abs($settlement->variance) >= 0.01)
                <div class="small fw-bold {{ $settlement->variance > 0 ? 'text-success' : 'text-danger' }}">
                    Variance: {{ $settlement->variance > 0 ? '+' : '' }}{{ $fmt($settlement->variance) }}
                </div>
            @endif
            @if($settlement->variance_note)<div class="text-muted small fst-italic">"{{ $settlement->variance_note }}"</div>@endif

            <form action="{{ route('admin.cash-settlement.update', $settlement->id) }}" method="POST" class="settlement-edit-form mt-2" id="settlementEdit{{ $settlement->id }}" style="display:none;">
                @csrf
                <div class="row g-2">
                    @if($canEditFigures)
                    <div class="col-6">
                        <label class="form-label small mb-1">Till Count</label>
                        <input type="number" step="0.01" min="0" name="till_count" class="form-control form-control-sm" value="{{ $settlement->till_count }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">Amount Banked</label>
                        <input type="number" step="0.01" min="0" name="amount_banked" class="form-control form-control-sm" value="{{ $settlement->amount_banked }}" required>
                    </div>
                    @else
                    <div class="col-12"><p class="text-muted small mb-1">A newer settlement already exists, so only the note can be changed here — editing the figures would desync everything settled after it.</p></div>
                    @endif
                    <div class="col-12">
                        <label class="form-label small mb-1">Note</label>
                        <textarea name="variance_note" class="form-control form-control-sm" rows="2" maxlength="1000">{{ $settlement->variance_note }}</textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-sm btn-cs-save mt-2">Save Changes</button>
            </form>
        </div>
    @empty
        <p class="text-muted mb-0">No settlements run yet.</p>
    @endforelse
</div>
@endif

{{-- Order view popup (tickets scope) — a quick look at an order without leaving the
     pane: its own details on the left (populated from the clicked row's own data-*
     attributes — all already server-rendered, so no extra request needed) and the real
     receipt/stub view on the right, embedded in a scrolling iframe exactly as it prints,
     rather than just linking straight to that page in a new tab. --}}
@if($scope !== 'event')
<div class="modal fade" id="csOrderModal_{{ $formId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none;">
            <div class="modal-header" style="background: #faf8f5; border-bottom: 1px solid #f0ece6;">
                <h5 class="modal-title fw-bold" id="csOrderModalTitle_{{ $formId }}">Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-md-5 p-4">
                        <div class="cs-order-detail-row">
                            <span class="cs-order-detail-label">Date</span>
                            <span class="cs-order-detail-value" id="csOrderModalDate_{{ $formId }}"></span>
                        </div>
                        <div class="cs-order-detail-row">
                            <span class="cs-order-detail-label">Customer</span>
                            <span class="cs-order-detail-value" id="csOrderModalCustomer_{{ $formId }}"></span>
                        </div>
                        <div class="cs-order-detail-row">
                            <span class="cs-order-detail-label">Payment Method</span>
                            <span class="cs-order-detail-value" id="csOrderModalMethod_{{ $formId }}"></span>
                        </div>
                        <div class="cs-order-detail-row" style="border-bottom: none;">
                            <span class="cs-order-detail-label">Total</span>
                            <span class="cs-order-detail-value cs-order-detail-total" id="csOrderModalTotal_{{ $formId }}"></span>
                        </div>
                        <a href="#" target="_blank" class="btn-cs-save mt-4 d-inline-flex align-items-center text-decoration-none" id="csOrderModalPrintLink_{{ $formId }}"><i class="bi bi-printer-fill me-2"></i>Print Receipt</a>
                    </div>
                    <div class="col-md-7 cs-order-receipt-col">
                        <iframe id="csOrderModalFrame_{{ $formId }}" src="about:blank" title="Receipt"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

</div>

<script>
(function () {
    const form = document.getElementById('{{ $formId }}');
    if (form) {
        const tillInput = form.querySelector('.settle-till-count');
        const bankedInput = form.querySelector('.settle-amount-banked');
        const varianceDisplay = form.querySelector('.settle-variance-display');
        const carryDisplay = form.querySelector('.settle-carry-display');
        const noteWrap = form.querySelector('.settle-note-wrap');
        const noteField = form.querySelector('.settle-note-field');
        const expected = parseFloat(tillInput.dataset.expected || '0');
        const currency = @json($currency);

        function fmt(n) { return currency + ' ' + n.toFixed(2); }

        // Real figures (0.00) rather than a plain dash before anything's been typed —
        // an audit view reads as more trustworthy showing actual zeroed-out numbers than
        // a placeholder that looks like "nothing computed yet".
        function recompute() {
            const till = parseFloat(tillInput.value);
            const banked = parseFloat(bankedInput.value);
            if (isNaN(till)) {
                varianceDisplay.textContent = fmt(0);
                varianceDisplay.className = 'cs-audit-stat-value settle-variance-display';
                carryDisplay.textContent = fmt(0);
                noteWrap.style.display = 'none';
                return;
            }
            const variance = Math.round((till - expected) * 100) / 100;
            varianceDisplay.textContent = (variance > 0 ? '+' : '') + fmt(variance);
            varianceDisplay.className = 'cs-audit-stat-value settle-variance-display ' + (Math.abs(variance) < 0.01 ? '' : (variance > 0 ? 'text-success' : 'text-danger'));
            carryDisplay.textContent = fmt(Math.round((till - (isNaN(banked) ? 0 : banked)) * 100) / 100);

            const mismatched = Math.abs(variance) >= 0.01;
            noteWrap.style.display = mismatched ? '' : 'none';
            noteField.required = mismatched;
        }

        tillInput.addEventListener('input', recompute);
        bankedInput.addEventListener('input', recompute);
        recompute();
    }

    document.querySelectorAll('.settlement-edit-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById('settlementEdit' + btn.dataset.settlementId);
            if (target) target.style.display = target.style.display === 'none' ? '' : 'none';
        });
    });

    // Current Period Transactions: inline edit toggle (devotee rows only — guest rows link
    // straight to the POS page instead) — swaps a row for its matching
    // <tr class="cs-edit-row"> (a plain classic form; submitting it reloads the page, same
    // as every other form in this widget) rather than opening a separate modal.
    document.querySelectorAll('.cs-edit-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.dataset.target);
            if (target) target.style.display = '';
        });
    });
    document.querySelectorAll('.cs-edit-cancel').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.dataset.target);
            if (target) target.style.display = 'none';
        });
    });

    // Current Period Transactions (tickets scope): click anywhere on the row (or its own
    // View icon) to open the order popup — details on the left (straight from the row's
    // own data-* attributes, already server-rendered) and the real receipt embedded on
    // the right. The Print icon keeps its own direct link/target, so it's excluded here.
    (function () {
        const orderModalEl = document.getElementById('csOrderModal_{{ $formId }}');
        if (!orderModalEl || typeof bootstrap === 'undefined') { return; }
        const orderModal = bootstrap.Modal.getOrCreateInstance(orderModalEl);
        const frame = document.getElementById('csOrderModalFrame_{{ $formId }}');

        function openOrderModal(row) {
            document.getElementById('csOrderModalTitle_{{ $formId }}').textContent = 'Order #' + row.dataset.orderId;
            document.getElementById('csOrderModalDate_{{ $formId }}').textContent = row.dataset.orderDate;
            document.getElementById('csOrderModalCustomer_{{ $formId }}').textContent = row.dataset.orderCustomer;
            document.getElementById('csOrderModalMethod_{{ $formId }}').textContent = row.dataset.orderMethod;
            document.getElementById('csOrderModalTotal_{{ $formId }}').textContent = row.dataset.orderTotal;
            document.getElementById('csOrderModalPrintLink_{{ $formId }}').href = row.dataset.orderUrl;
            frame.src = row.dataset.orderUrl;
            orderModal.show();
        }

        document.querySelectorAll('.cs-row-clickable').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button')) { return; }
                openOrderModal(row);
            });
            const viewBtn = row.querySelector('.cs-view-order-btn');
            if (viewBtn) {
                viewBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openOrderModal(row);
                });
            }
        });

        // Stop the receipt iframe holding/reloading stale content once the popup closes.
        orderModalEl.addEventListener('hidden.bs.modal', function () { frame.src = 'about:blank'; });
    })();

    // Current Period Transactions: client-side search by name/purpose.
    const txnSearch = document.getElementById('csTxnSearch_{{ $formId }}');
    const txnTable = document.getElementById('csTxnTable_{{ $formId }}');
    if (txnSearch && txnTable) {
        txnSearch.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            txnTable.querySelectorAll('tbody tr.cs-row').forEach(function (row) {
                const match = !q || (row.dataset.search || '').includes(q);
                row.style.display = match ? '' : 'none';
                if (!match && row.dataset.editId) {
                    const editRow = document.getElementById(row.dataset.editId);
                    if (editRow) { editRow.style.display = 'none'; }
                }
            });
        });
    }
})();
</script>
