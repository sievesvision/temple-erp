{{-- Shared by event-console.blade.php and ticket-console.blade.php's Cash Banking pane.
     Expects: $scope ('event'|'tickets'), $eventId (nullable), $cashPreview (array from
     CashSettlementService::preview()), $cashBankings, $cashSettlements (collections),
     $temple (for currency). Deliberately styled with only the plain Bootstrap utility
     classes + .card-panel/.stat-tile/.btn-save that both host consoles already define,
     rather than introducing a new shared CSS dependency either file would need to add. --}}
@php
    $currency = $temple['currency'] ?? '';
    $fmt = fn ($n) => $currency . ' ' . number_format($n, 2);
@endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-tile"><div class="stat-icon" style="background:var(--gold, #C89B3C);"><i class="bi bi-box-arrow-in-right"></i></div><div class="stat-text"><div class="label">Opening Balance</div><div class="value">{{ $fmt($cashPreview['opening_balance']) }}</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile"><div class="stat-icon" style="background:var(--success, #10B981);"><i class="bi bi-cash-coin"></i></div><div class="stat-text"><div class="label">Cash Received</div><div class="value">{{ $fmt($cashPreview['cash_received']) }}</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile"><div class="stat-icon" style="background:var(--maroon, #6B0F1A);"><i class="bi bi-bank"></i></div><div class="stat-text"><div class="label">Banked</div><div class="value">{{ $fmt($cashPreview['amount_banked']) }}</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile"><div class="stat-icon" style="background:#B7791F;"><i class="bi bi-safe2-fill"></i></div><div class="stat-text"><div class="label">Remaining to Bank</div><div class="value">{{ $fmt($cashPreview['closing_balance']) }}</div></div></div>
    </div>
</div>

<p class="text-muted small">
    Period: {{ $cashPreview['period_start']->format('d M Y') }} &ndash; {{ $cashPreview['period_end']->format('d M Y') }} (since the last settlement, or the beginning if none has been run yet).
    These per-{{ $scope === 'event' ? 'donation-type' : 'ticket-type' }} figures below are for visibility only — a banking deposit is always a single lump sum, never split by category, so "Remaining to Bank" above is the one number that matters for reconciliation.
</p>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-panel h-100">
            <div class="fw-bold mb-2"><i class="bi bi-tags-fill me-1"></i>Cash Received by {{ $scope === 'event' ? 'Donation Type' : 'Ticket Type' }} (this period)</div>
            @forelse($cashPreview['breakdown'] as $row)
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>{{ $row['label'] }}</span>
                    <span class="fw-bold">{{ $fmt($row['cash_received']) }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No cash received in this period yet.</p>
            @endforelse
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-panel h-100">
            <div class="fw-bold mb-2"><i class="bi bi-plus-circle-fill me-1"></i>Record a Banking Deposit</div>
            <form action="{{ route('admin.cash-settlement.recordBanking') }}" method="POST">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}">
                @if($eventId)<input type="hidden" name="event_id" value="{{ $eventId }}">@endif
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small">Amount ({{ $currency }})</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Date Banked</label>
                        <input type="date" name="banked_date" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Reference <span class="text-muted">(optional)</span></label>
                        <input type="text" name="reference" class="form-control" maxlength="255">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Note <span class="text-muted">(optional)</span></label>
                        <input type="text" name="note" class="form-control" maxlength="255">
                    </div>
                </div>
                <button type="submit" class="btn-save mt-3">Record Banking</button>
            </form>
        </div>
    </div>
</div>

<div class="card-panel mt-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
            <div class="fw-bold"><i class="bi bi-clipboard-check-fill me-1"></i>Run Settlement</div>
            <p class="text-muted small mb-0">
                Locks in: Opening {{ $fmt($cashPreview['opening_balance']) }} + Received {{ $fmt($cashPreview['cash_received']) }}
                &minus; Banked {{ $fmt($cashPreview['amount_banked']) }} = Closing {{ $fmt($cashPreview['closing_balance']) }},
                as a permanent record, and starts the next period fresh from that closing balance.
            </p>
        </div>
        <form action="{{ route('admin.cash-settlement.run') }}" method="POST" class="d-flex align-items-end gap-2" onsubmit="return confirm('This locks in the figures as a permanent settlement record. Continue?');">
            @csrf
            <input type="hidden" name="scope" value="{{ $scope }}">
            @if($eventId)<input type="hidden" name="event_id" value="{{ $eventId }}">@endif
            <div>
                <label class="form-label small mb-1">Settle as of</label>
                <input type="date" name="period_end" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
            </div>
            <button type="submit" class="btn-save text-nowrap">Run Settlement Now</button>
        </form>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card-panel h-100">
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
        <div class="card-panel h-100">
            <div class="fw-bold mb-2"><i class="bi bi-journal-check me-1"></i>Settlement History</div>
            @forelse($cashSettlements as $settlement)
                <div class="border-bottom py-2">
                    <div class="fw-bold">{{ $settlement->period_start->format('d M Y') }} &ndash; {{ $settlement->period_end->format('d M Y') }}</div>
                    <div class="text-muted small">
                        Opening {{ $fmt($settlement->opening_balance) }} &rarr; Closing {{ $fmt($settlement->closing_balance) }}
                        @if($settlement->performedBy) &middot; by {{ $settlement->performedBy->name }} @endif
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">No settlements run yet.</p>
            @endforelse
        </div>
    </div>
</div>
