{{-- Extracted from cash-settlement-pane.blade.php — same reasoning as cash-settlement-
     transactions.blade.php's own note on why, and $settlementFirst there for the ordering
     this split exists to support: grouped with the Settlement form in both layouts (right
     before it) rather than always-first, so it never shifts the Current Period Transactions-
     before-Settlement order CashSettlementPaneLiveRefreshTest locks in for Ticket POS.
     Shares cash-settlement-pane.blade.php's own variable scope — $currentTransactions,
     $fmt, $scope, $eventId come from there.

     Partial Pledge Payments: "Received by Payment Type"/"Total Received" now explode a
     split/partially-paid pledge into its real per-payment amounts (via
     CashSettlementService::paymentMethodBreakdown(), event scope only) instead of grouping
     $currentTransactions's own donation-level payment_method mirror — that mirror reads
     'Split' for a multi-method pledge and, worse, a Partially Paid donation is excluded
     from $currentTransactions's Paid-only filter entirely, so its received money would
     otherwise be missing from this total, not just mislabeled. Tickets scope keeps
     grouping $currentTransactions exactly as before — no pledge/ledger concept there.
     "Pending Transactions" stays donation-level in both scopes; it's a review count, not
     a by-method split. --}}
@php
    $csAmountOf = fn ($r) => (float) ($r->amount ?? $r->total_amount ?? 0);
    if ($scope === 'event') {
        $csBreakdown = \App\Services\CashSettlementService::paymentMethodBreakdown($scope, $eventId);
        $csSummaryByMethod = collect($csBreakdown['by_method']);
        $csTotalReceived = $csBreakdown['total'];
    } else {
        $csSummaryByMethod = $currentTransactions
            ->filter(fn ($r) => $r->payment_status === 'Paid')
            ->groupBy('payment_method')
            ->map(fn ($rows) => $rows->sum($csAmountOf))
            ->sortDesc();
        $csTotalReceived = $csSummaryByMethod->sum();
    }
    $csPendingRows = $currentTransactions->filter(fn ($r) => $r->payment_status === 'Pending');
    $csPendingCount = $csPendingRows->count();
    $csPendingTotal = $csPendingRows->sum($csAmountOf);
@endphp

<div class="cs-card mb-3">
    <div class="fw-bold mb-3"><i class="bi bi-receipt-cutoff me-1"></i>Settlement Summary</div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="cs-summary-row">
                <span class="cs-summary-row-label"><i class="bi bi-check-circle-fill" style="color:#15803D;"></i>Total Received (Completed)</span>
                <span class="cs-summary-row-value">{{ $fmt($csTotalReceived) }}</span>
            </div>
            <div class="cs-summary-row" style="border-bottom:none;">
                <span class="cs-summary-row-label"><i class="bi bi-hourglass-split" style="color:#B7791F;"></i>Pending Transactions</span>
                <span class="cs-summary-row-value">{{ $csPendingCount }} &middot; {{ $fmt($csPendingTotal) }}</span>
            </div>
        </div>
        <div class="col-md-8">
            <div class="mb-2" style="font-size:0.72rem; text-transform:uppercase; letter-spacing:0.4px; color:#8A6A1E; font-weight:700;">Received by Payment Type</div>
            @if($csSummaryByMethod->isEmpty())
            <div class="text-muted small">Nothing received yet this period.</div>
            @else
            <div class="d-flex flex-wrap gap-3">
                @foreach($csSummaryByMethod as $method => $amount)
                <div class="cs-tile" style="flex:1; min-width:140px;">
                    <div class="cs-label">{{ $method }}</div>
                    <div class="cs-value">{{ $fmt($amount) }}</div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
