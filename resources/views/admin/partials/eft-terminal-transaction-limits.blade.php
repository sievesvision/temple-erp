{{-- Minimum EFT Terminal transaction amount — common storage/enforcement across both
     providers (see App\Services\EftTransactionLimits), used by CbaSciController::
     startPurchase() (mx51) and DonationController::startEftCharge() (Linkly, shared by the
     Donation POS and Ticket POS) in place of a hardcoded "must be at least 1". System-
     Admin-only, same tier as the other settings cards on this page. --}}
@php $eftMinimumAmount = \App\Services\EftTransactionLimits::minimumAmount(); @endphp
<div class="eftr-settings-card">
    <div class="eftr-settings-heading"><i class="bi bi-sliders me-1"></i>Transaction Limits</div>
    <form action="{{ route('admin.eft-terminals.updateTransactionLimits') }}" method="POST" class="d-flex align-items-end gap-3 flex-wrap" style="width:100%;">
        @csrf
        <div>
            <label class="form-label small mb-1" for="eftMinimumTransactionAmount">Minimum transaction amount</label>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm rounded-3" id="eftMinimumTransactionAmount" name="minimum_transaction_amount" value="{{ number_format($eftMinimumAmount, 2, '.', '') }}" style="max-width:140px;">
        </div>
        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
        <p class="text-muted small mb-0" style="flex-basis:100%;">Applies to starting an EFT Terminal purchase, for both SCI and Linkly. An attempt below this amount is rejected before it ever reaches the terminal.</p>
    </form>
</div>
