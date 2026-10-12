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
})();
</script>
