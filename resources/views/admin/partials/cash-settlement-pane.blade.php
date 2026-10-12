{{-- Shared by the Event Console, Ticket Console, Event POS and Ticket POS pages (the last
     two are standalone HTML documents with their own styling, no shared layout) — so this
     partial is deliberately self-contained: only plain Bootstrap classes (always loaded on
     all four) plus its own small scoped <style> block, nothing that depends on a class any
     particular host page happens to define.
     Expects: $scope ('event'|'tickets'), $eventId (nullable), $cashPreview (array from
     CashSettlementService::preview()), $cashBankings, $cashSettlements (collections —
     empty when $canViewCashHistory is false), $canViewCashHistory (bool — false for a
     pos-level event coordinator; see CashSettlementService::canViewHistory()), $temple. --}}
@php
    $currency = $temple['currency'] ?? '';
    $fmt = fn ($n) => $currency . ' ' . number_format((float) $n, 2);
    $expected = round($cashPreview['opening_balance'] + $cashPreview['cash_received'], 2);
    $formId = 'settleTillForm_' . $scope . '_' . ($eventId ?? 'tickets');
@endphp

<div class="cash-settle-widget">
<style>
    .cash-settle-widget { font-family: inherit; color: #1e1e2a; }
    .cash-settle-widget .cs-tile { background: #faf8f5; border: 1px solid rgba(184,134,58,0.12); border-radius: 16px; padding: 14px 16px; height: 100%; }
    .cash-settle-widget .cs-tile .cs-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; margin-bottom: 8px; }
    .cash-settle-widget .cs-tile .cs-label { font-size: 0.74rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #8A6A1E; }
    .cash-settle-widget .cs-tile .cs-value { font-size: 1.3rem; font-weight: 800; margin-top: 2px; font-variant-numeric: tabular-nums; }
    .cash-settle-widget .cs-card { background: #fff; border: 1px solid rgba(184,134,58,0.1); border-radius: 18px; padding: 18px 20px; }
    .cash-settle-widget .btn-cs-save { background: linear-gradient(135deg, #b8863a, #d4a05a); color: #fff; border: none; border-radius: 40px; padding: 10px 28px; font-weight: 700; }
    .cash-settle-widget .btn-cs-save:hover { color: #fff; filter: brightness(1.05); }
</style>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="cs-tile"><div class="cs-icon" style="background:#C89B3C;"><i class="bi bi-box-arrow-in-right"></i></div><div class="cs-label">Opening Balance</div><div class="cs-value">{{ $fmt($cashPreview['opening_balance']) }}</div></div>
    </div>
    <div class="col-6 col-md-4">
        <div class="cs-tile"><div class="cs-icon" style="background:#10B981;"><i class="bi bi-cash-coin"></i></div><div class="cs-label">Cash Received (this period)</div><div class="cs-value">{{ $fmt($cashPreview['cash_received']) }}</div></div>
    </div>
    <div class="col-6 col-md-4">
        <div class="cs-tile"><div class="cs-icon" style="background:#B7791F;"><i class="bi bi-calculator-fill"></i></div><div class="cs-label">Expected in Till</div><div class="cs-value">{{ $fmt($expected) }}</div></div>
    </div>
</div>

<p class="text-muted small">
    Period: {{ $cashPreview['period_start']->format('d M Y') }} &ndash; {{ $cashPreview['period_end']->format('d M Y') }} (since the last settlement, or the beginning if none has been run yet).
    Count the till now and settle below — the per-{{ $scope === 'event' ? 'donation-type' : 'ticket-type' }} figures are for visibility only, since a till count is always one lump sum, never split by category.
</p>

@if(count($cashPreview['breakdown']))
<div class="cs-card mb-3">
    <div class="fw-bold mb-2"><i class="bi bi-tags-fill me-1"></i>Cash Received by {{ $scope === 'event' ? 'Donation Type' : 'Ticket Type' }} (this period)</div>
    @foreach($cashPreview['breakdown'] as $row)
        <div class="d-flex justify-content-between border-bottom py-2">
            <span>{{ $row['label'] }}</span>
            <span class="fw-bold">{{ $fmt($row['cash_received']) }}</span>
        </div>
    @endforeach
</div>
@endif

@php $canEditCurrentTransactions = $canEditCurrentTransactions ?? false; @endphp
<div class="cs-card mb-3">
    <div class="fw-bold mb-2"><i class="bi bi-list-check me-1"></i>Current Period Transactions ({{ $currentTransactions->count() }})</div>
    <p class="text-muted small mb-2">Every Cash, Paid {{ $scope === 'event' ? 'donation' : 'ticket order' }} counted in "Cash Received" above — review these before you settle@if($canEditCurrentTransactions), and fix anything that's wrong@endif.</p>
    @if($currentTransactions->isEmpty())
        <p class="text-muted mb-0">Nothing recorded in this period yet.</p>
    @else
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr class="text-muted small text-uppercase">
                    <th>Date</th>
                    <th>{{ $scope === 'event' ? 'Donor' : 'Customer' }}</th>
                    <th>{{ $scope === 'event' ? 'Purpose' : 'Order' }}</th>
                    <th class="text-end">Amount</th>
                    @if($canEditCurrentTransactions)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @if($scope === 'event')
                    @foreach($currentTransactions as $row)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($row->donation_date)->format('d M Y') }}</td>
                        <td>{{ $row->display_name }}</td>
                        <td>{{ $row->purpose ?: '—' }}</td>
                        <td class="text-end fw-bold">{{ $fmt($row->amount) }}</td>
                        @if($canEditCurrentTransactions)
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#txnEdit{{ $row->donation_type }}{{ $row->id }}">Edit</button></td>
                        @endif
                    </tr>
                    @endforeach
                @else
                    @foreach($currentTransactions as $row)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($row->order_date)->format('d M Y') }}</td>
                        <td>{{ $row->customer_name ?: 'Walk-in' }}</td>
                        <td>Order #{{ $row->id }}</td>
                        <td class="text-end fw-bold">{{ $fmt($row->total_amount) }}</td>
                    </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
    @if($scope === 'tickets')
    <p class="text-muted small fst-italic mt-2 mb-0">Ticket orders can't be edited here yet — refund from the Pooja Portal's All Orders list if one needs correcting.</p>
    @endif
    @endif
</div>

@if($canEditCurrentTransactions && $scope === 'event')
    @foreach($currentTransactions->where('donation_type', 'devotee') as $row)
    <div class="modal fade" id="txnEditdevotee{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:20px;">
                <form action="{{ route('admin.donations.updateDevotee', $row->id) }}" method="POST">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Devotee Donation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="event_id" value="{{ $eventId }}">
                        <div class="mb-3"><label class="form-label small">Devotee</label><input type="text" class="form-control" value="{{ $row->display_name }}" disabled></div>
                        <div class="row g-3 mb-3">
                            <div class="col-6"><label class="form-label small">Amount ({{ $currency }})</label><input type="number" step="0.01" name="amount" class="form-control" value="{{ $row->amount }}" required></div>
                            <div class="col-6"><label class="form-label small">Date</label><input type="date" name="donation_date" class="form-control" value="{{ $row->donation_date }}" required></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small">Payment Mode</label>
                                <select name="payment_mode" class="form-select" required>
                                    @foreach(['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'EFT Terminal', 'Stripe'] as $mode)
                                    <option value="{{ $mode }}" {{ $row->payment_method === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Status</label>
                                <select name="payment_status" class="form-select" required>
                                    @foreach(['Paid', 'Pending', 'Cancelled', 'Failed'] as $status)
                                    <option value="{{ $status }}" {{ $row->payment_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-3"><label class="form-label small">Purpose</label><input type="text" name="purpose" class="form-control" value="{{ $row->purpose }}"></div>
                        <div class="mb-0"><label class="form-label small">Remarks</label><textarea name="remarks" rows="2" class="form-control">{{ $row->remarks }}</textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-cs-save">Save Changes</button></div>
                </form>
            </div>
        </div>
    </div>
    @endforeach

    @foreach($currentTransactions->where('donation_type', 'guest') as $row)
    <div class="modal fade" id="txnEditguest{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:20px;">
                <form action="{{ route('admin.donations.updateGuest', $row->id) }}" method="POST">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Guest Donation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="event_id" value="{{ $eventId }}">
                        <div class="mb-3"><label class="form-label small">Donor Name</label><input type="text" name="donor_name" class="form-control" value="{{ $row->donor_name }}" required></div>
                        <div class="row g-3 mb-3">
                            <div class="col-6"><label class="form-label small">Amount ({{ $currency }})</label><input type="number" step="0.01" name="amount" class="form-control" value="{{ $row->amount }}" required></div>
                            <div class="col-6"><label class="form-label small">Date</label><input type="date" name="donation_date" class="form-control" value="{{ $row->donation_date }}" required></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    @foreach(['Cash', 'UPI', 'Bank', 'EFT Terminal', 'Stripe'] as $method)
                                    <option value="{{ $method }}" {{ $row->payment_method === $method ? 'selected' : '' }}>{{ $method }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Status</label>
                                <select name="payment_status" class="form-select" required>
                                    @foreach(['Paid', 'Pending', 'Cancelled', 'Failed'] as $status)
                                    <option value="{{ $status }}" {{ $row->payment_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-3"><label class="form-label small">Purpose</label><input type="text" name="purpose" class="form-control" value="{{ $row->purpose }}" required></div>
                        <div class="mb-0"><label class="form-label small">Details</label><textarea name="purpose_details" rows="2" class="form-control">{{ $row->purpose_details }}</textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-cs-save">Save Changes</button></div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
@endif

<div class="cs-card mb-3">
    <div class="fw-bold mb-2"><i class="bi bi-clipboard-check-fill me-1"></i>Settle Till</div>
    <p class="text-muted small mb-3">Count every note and coin in the till, enter it below, then say how much of that you're taking to the bank. What's left is automatically carried forward as the opening balance for the next till count.</p>
    <form id="{{ $formId }}" action="{{ route('admin.cash-settlement.run') }}" method="POST">
        @csrf
        <input type="hidden" name="scope" value="{{ $scope }}">
        @if($eventId)<input type="hidden" name="event_id" value="{{ $eventId }}">@endif
        <input type="hidden" name="period_end" value="{{ now()->toDateString() }}">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small">Till Count — total cash counted ({{ $currency }})</label>
                <input type="number" step="0.01" min="0" name="till_count" class="form-control settle-till-count" data-expected="{{ $expected }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Amount Taken to Banking ({{ $currency }})</label>
                <input type="number" step="0.01" min="0" name="amount_banked" class="form-control settle-amount-banked" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small d-block">&nbsp;</label>
                <div class="d-flex gap-3 flex-wrap">
                    <div><span class="text-muted small d-block">Variance</span><span class="fw-bold settle-variance-display">—</span></div>
                    <div><span class="text-muted small d-block">Carried Forward</span><span class="fw-bold settle-carry-display">—</span></div>
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

@if($canViewCashHistory)
<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="cs-card h-100">
            <div class="fw-bold mb-2"><i class="bi bi-bank2 me-1"></i>Banking History</div>
            @forelse($cashBankings as $banking)
                <div class="border-bottom py-2">
                    <div class="fw-bold">{{ $fmt($banking->amount) }}</div>
                    <div class="text-muted small">
                        {{ $banking->banked_date->format('d M Y') }}
                        @if($banking->reference) &middot; {{ $banking->reference }} @endif
                        @if($banking->recordedBy) &middot; {{ $banking->recordedBy->name }} @endif
                    </div>
                    @if($banking->note)<div class="text-muted small fst-italic">{{ $banking->note }}</div>@endif
                </div>
            @empty
                <p class="text-muted mb-0">No banking entries recorded yet.</p>
            @endforelse
        </div>
    </div>
    <div class="col-lg-6">
        <div class="cs-card h-100">
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
    </div>
</div>
@else
<p class="text-muted small fst-italic mt-2"><i class="bi bi-info-circle me-1"></i>Past settlements and banking history aren't shown here — ask an Admin or Entry-level user if you need to review them.</p>
@endif

</div>

<script>
(function () {
    const form = document.getElementById('{{ $formId }}');
    if (!form) return;
    const tillInput = form.querySelector('.settle-till-count');
    const bankedInput = form.querySelector('.settle-amount-banked');
    const varianceDisplay = form.querySelector('.settle-variance-display');
    const carryDisplay = form.querySelector('.settle-carry-display');
    const noteWrap = form.querySelector('.settle-note-wrap');
    const noteField = form.querySelector('.settle-note-field');
    const expected = parseFloat(tillInput.dataset.expected || '0');
    const currency = @json($currency);

    function fmt(n) { return currency + ' ' + n.toFixed(2); }

    function recompute() {
        const till = parseFloat(tillInput.value);
        const banked = parseFloat(bankedInput.value);
        if (isNaN(till)) {
            varianceDisplay.textContent = '—';
            carryDisplay.textContent = '—';
            noteWrap.style.display = 'none';
            return;
        }
        const variance = Math.round((till - expected) * 100) / 100;
        varianceDisplay.textContent = (variance > 0 ? '+' : '') + fmt(variance);
        varianceDisplay.className = 'fw-bold settle-variance-display ' + (Math.abs(variance) < 0.01 ? '' : (variance > 0 ? 'text-success' : 'text-danger'));
        carryDisplay.textContent = isNaN(banked) ? '—' : fmt(Math.round((till - banked) * 100) / 100);

        const mismatched = Math.abs(variance) >= 0.01;
        noteWrap.style.display = mismatched ? '' : 'none';
        noteField.required = mismatched;
    }

    tillInput.addEventListener('input', recompute);
    bankedInput.addEventListener('input', recompute);
    recompute();

    document.querySelectorAll('.settlement-edit-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById('settlementEdit' + btn.dataset.settlementId);
            if (target) target.style.display = target.style.display === 'none' ? '' : 'none';
        });
    });

    // On the POS pages this whole widget is rendered INSIDE the "Settle Till" modal's own
    // DOM, so each transaction's edit modal is nested inside it too — hiding the parent
    // (display:none) hides its descendants regardless of their own shown state, and a
    // modal using position:fixed inside another modal's transitioning .modal-dialog can
    // also just render in the wrong place. Re-parenting each edit modal to <body> once,
    // up front, sidesteps both: Bootstrap only ever supports one modal per DOM branch
    // cleanly, so each one needs to be its own top-level sibling. No-op on the console
    // pages, where these modals are already top-level.
    document.querySelectorAll('[id^="txnEdit"].modal').forEach(function (modalEl) {
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
    });

    document.querySelectorAll('[id^="txnEdit"].modal').forEach(function (modalEl) {
        let reopenParent = null;
        modalEl.addEventListener('show.bs.modal', function () {
            const openParent = document.querySelector('.modal.show');
            if (openParent && openParent !== modalEl) {
                reopenParent = openParent;
                bootstrap.Modal.getInstance(openParent)?.hide();
            }
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (reopenParent) {
                bootstrap.Modal.getOrCreateInstance(reopenParent).show();
                reopenParent = null;
            }
        });
    });
})();
</script>
