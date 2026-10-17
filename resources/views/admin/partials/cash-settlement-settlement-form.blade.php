{{-- Extracted from cash-settlement-pane.blade.php — see cash-settlement-transactions.
     blade.php's identical note on why, and $settlementFirst there for the ordering this
     split exists to support. Settlement — the period's cash summary and the till-count
     form that settles it share one card, since they're two views of the same figures
     rather than separate concerns: the form's own "expected"/variance math is computed
     straight from the summary beside it.
     $settlementFirst also selects a different LAYOUT here (Event POS's own 4-column
     grid — summary / inputs / computed figures / button, all left-to-right — requested
     specifically for that page) rather than just reordering where this card sits; every
     other caller keeps the original two-column (summary left, form right) layout below. --}}
@if($settlementFirst)
<div class="cs-card mb-3">
    <div class="cs-settle-header">
        <div class="fw-bold"><i class="bi bi-clipboard-check-fill me-1"></i>Settlement</div>
        @if($context === 'pos' || !$canViewCashHistory)
        <div class="cs-settle-last">
            <i class="bi bi-clock-history me-1"></i>
            <span class="fw-bold">Last settled:</span> {{ $lastSettlementDate ? $lastSettlementDate->format('d M Y, g:i A') : 'Never — this is the first period' }}
        </div>
        @endif
    </div>
    <form id="{{ $formId }}" action="{{ route('admin.cash-settlement.run') }}" method="POST">
        @csrf
        <input type="hidden" name="scope" value="{{ $scope }}">
        @if($eventId)<input type="hidden" name="event_id" value="{{ $eventId }}">@endif
        <div class="cs-settle-grid">
            <div class="cs-settle-col">
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
            </div>
            <div class="cs-settle-col">
                <div class="cs-audit-field">
                    <label>Till Count — total cash counted</label>
                    <div class="cs-audit-input-wrap">
                        <span class="cs-audit-currency">{{ $currency }}</span>
                        <input type="number" step="0.01" min="0" name="till_count" class="cs-audit-input settle-till-count" data-expected="{{ $expected }}" required>
                    </div>
                </div>
                <div class="cs-audit-field">
                    <label>Amount Taken to Banking</label>
                    <div class="cs-audit-input-wrap">
                        <span class="cs-audit-currency">{{ $currency }}</span>
                        <input type="number" step="0.01" min="0" name="amount_banked" class="cs-audit-input settle-amount-banked" required>
                    </div>
                </div>
            </div>
            <div class="cs-settle-col">
                <div class="cs-summary-row">
                    <span class="cs-summary-row-label"><i class="bi bi-arrow-left-right" style="color:#B7791F;"></i>Difference</span>
                    <span class="cs-summary-row-value settle-variance-display">{{ $fmt(0) }}</span>
                </div>
                <div class="cs-summary-row" style="border-bottom:none;">
                    <span class="cs-summary-row-label"><i class="bi bi-arrow-repeat" style="color:#2a6fdb;"></i>Carried Forward</span>
                    <span class="cs-summary-row-value settle-carry-display">{{ $fmt(0) }}</span>
                </div>
            </div>
            <div class="cs-settle-col cs-settle-col-btn">
                <button type="submit" class="cs-settle-btn-primary" onclick="return confirm('This locks in the till count as a permanent settlement record and starts the next period from what\'s carried forward. Continue?');">
                    <i class="bi bi-check-circle-fill me-2"></i>Settle Till Now
                </button>
            </div>
        </div>
        <div class="settle-note-wrap" style="display:none;">
            <label class="form-label small">Note — explain the mismatch <span class="text-danger">*</span></label>
            <textarea name="variance_note" class="form-control settle-note-field" rows="2" maxlength="1000" placeholder="e.g. a donor's change was short, or an extra note was found"></textarea>
        </div>
        @error('variance_note')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </form>
</div>
@else
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
@endif
