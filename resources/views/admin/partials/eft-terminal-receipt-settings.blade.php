{{-- Receipt printing / signature-verification preferences — common storage across every EFT
     provider (see App\Services\EftReceiptSettings), though only mx51's Simple Cloud
     Integration actually sends these to the gateway today (CbaSciService::createTransaction()
     merges them into every transaction it creates) — Linkly's own requests don't take them
     yet. System-Admin-only, same tier as the Environment switch above: this changes how every
     station's receipts and signature verification behave, not a single terminal's own setup.
     Reads straight from EftReceiptSettings itself (no variables to pass in) — these are
     genuinely global settings, not derived from whatever page happens to include this. --}}
@php
    $printMerchantReceiptOnTerminal = \App\Services\EftReceiptSettings::printMerchantReceiptOnTerminal();
    $promptCustomerReceiptOnTerminal = \App\Services\EftReceiptSettings::promptCustomerReceiptOnTerminal();
    $verifySignatureOnTerminal = \App\Services\EftReceiptSettings::verifySignatureOnTerminal();
    $posAutoPrintSignatureReceipt = \App\Services\EftReceiptSettings::posAutoPrintSignatureReceipt();
@endphp
<div class="eftr-settings-card">
    <div class="eftr-settings-heading"><i class="bi bi-receipt me-1"></i>Receipt Printing &amp; Signature (SCI only, for now)</div>
    <form action="{{ route('admin.eft-terminals.updateReceiptSettings') }}" method="POST" style="width:100%;">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="printMerchantReceiptOnTerminal" name="print_merchant_receipt_on_terminal" value="1" {{ $printMerchantReceiptOnTerminal ? 'checked' : '' }}>
                    <label class="form-check-label" for="printMerchantReceiptOnTerminal">Terminal prints merchant receipt</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="promptCustomerReceiptOnTerminal" name="prompt_customer_receipt_on_terminal" value="1" {{ $promptCustomerReceiptOnTerminal ? 'checked' : '' }}>
                    <label class="form-check-label" for="promptCustomerReceiptOnTerminal">Terminal prompts customer for receipt</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="verifySignatureOnTerminal" name="verify_signature_on_terminal" value="1" {{ $verifySignatureOnTerminal ? 'checked' : '' }}>
                    <label class="form-check-label" for="verifySignatureOnTerminal">EFTPOS signature flow (terminal handles signature)</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="posAutoPrintSignatureReceipt" name="pos_auto_print_signature_receipt" value="1" {{ $posAutoPrintSignatureReceipt ? 'checked' : '' }}>
                    <label class="form-check-label" for="posAutoPrintSignatureReceipt">Auto-print signature receipt from POS</label>
                </div>
            </div>
        </div>
        <p class="text-muted small mt-2 mb-2">Sent to SCI as print_merchant_receipt / prompt_customer_receipt / verify_signature_on_terminal / pos_auto_print_signature_receipt on every transaction. SCI's own recommended defaults: all off except "Auto-print signature receipt from POS", which defaults on.</p>
        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
    </form>
</div>
