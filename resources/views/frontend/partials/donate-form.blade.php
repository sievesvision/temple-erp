@php
    // Expects: $temple (array), optional $events (collection, for the general form's event picker),
    // optional $lockedEvent (Event model, for the per-event donation page), $formAction (route url), $formId (unique dom id)
    $formId = $formId ?? 'donate-form';
    $lockedEvent = $lockedEvent ?? null;
    $events = $events ?? collect();
    $donationOptions = $donationOptions ?? collect();
    // A single configured option (e.g. one plain "General Donation") is always included —
    // there's nothing to choose between, so it isn't shown as a selectable checkbox tier.
    $singleOption = ($lockedEvent && $donationOptions->count() === 1) ? $donationOptions->first() : null;
    $useTiers = $lockedEvent && $donationOptions->count() > 1;
    $showPlainAmountField = !$lockedEvent || ($lockedEvent && $donationOptions->isEmpty());
    $stripeEnabled = $stripeEnabled ?? true;
    $prefillName = $prefillName ?? null;
    $prefillEmail = $prefillEmail ?? null;
    $lockContactFields = $lockContactFields ?? false;
    $requireContactDetails = $requireContactDetails ?? false;
@endphp
<style>
    .quick-amount-chip {
        background: #f5f0e6;
        border: 2px solid transparent;
        color: var(--primary, #b8863a);
        font-weight: 700;
        padding: 8px 16px;
        border-radius: 40px;
        font-size: 0.9rem;
        transition: 0.15s;
    }
    .quick-amount-chip:hover, .quick-amount-chip.active {
        background: var(--primary, #b8863a);
        color: white;
    }
</style>
<div class="donate-tabs-card">
    <ul class="nav nav-pills donate-method-tabs mb-4" id="{{ $formId }}-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-method="Bank" type="button" role="tab">
                <i class="bi bi-bank2 me-1"></i> Bank Transfer
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-method="Cash" type="button" role="tab">
                <i class="bi bi-cash-coin me-1"></i> Cash at Temple
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-method="Stripe" type="button" role="tab" @if(!$stripeEnabled) disabled title="Online payment is currently unavailable" style="opacity:0.5;cursor:not-allowed;" @endif>
                <i class="bi bi-credit-card me-1"></i> Online Payment
                @if(!$stripeEnabled)
                    <span class="badge bg-secondary ms-1" style="font-size:0.65rem;">Unavailable</span>
                @endif
            </button>
        </li>
    </ul>
    @if(!$stripeEnabled)
        <div class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>Online payment is temporarily unavailable. Please use Bank Transfer or Cash at Temple instead.</div>
    @endif

    <div class="donate-method-info mb-4" data-method-info="Bank">
        <div class="donation-bank-card">
            <div class="row g-3">
                <div class="col-md-7"><span class="bank-label">Account name</span><strong class="bank-value">{{ $temple['donation_account_name'] }}</strong></div>
                <div class="col-md-5"><span class="bank-label">Bank</span><strong class="bank-value">{{ $temple['donation_bank_name'] }}</strong></div>
                <div class="col-md-5"><span class="bank-label">BSB number</span><strong class="bank-value">{{ $temple['donation_bsb'] }}</strong></div>
                <div class="col-md-7"><span class="bank-label">Account number</span><strong class="bank-value">{{ $temple['donation_account_number'] }}</strong></div>
            </div>
            <hr>
            <p class="mb-0 small">Transfer directly using these details, then submit the form below so we can match your receipt. Send a copy of your transfer receipt to <a href="mailto:{{ $temple['donation_receipt_email'] }}">{{ $temple['donation_receipt_email'] }}</a> for an official receipt.</p>
        </div>
    </div>
    <div class="donate-method-info mb-4" data-method-info="Cash" style="display:none;">
        <div class="donation-bank-card">
            <p class="mb-0"><i class="bi bi-info-circle me-2"></i>You can hand your offering directly to the temple counter during opening hours. Submitting this form records your pledge so we can prepare your receipt when you visit.</p>
        </div>
    </div>
    <div class="donate-method-info mb-4" data-method-info="Stripe" style="display:none;">
        <div class="donation-bank-card">
            <p class="mb-0"><i class="bi bi-shield-check me-2"></i>Pay securely online in {{ $temple['currency'] }}. Online payments are securely processed through Stripe. Your card details are handled directly by Stripe and are not stored on our website or systems.</p>
        </div>
    </div>

    <form class="donation-form" id="{{ $formId }}" method="POST" action="{{ $formAction }}">
        @csrf
        <input type="hidden" name="payment_method" id="{{ $formId }}-method" value="Bank">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="{{ $formId }}-donor_name">Your name</label>
                <input class="form-control" id="{{ $formId }}-donor_name" name="donor_name" value="{{ $prefillName ?? old('donor_name') }}" @if($lockContactFields) readonly @endif required>
            </div>
            <div class="col-md-6">
                <label for="{{ $formId }}-email">Email for receipt{{ $requireContactDetails ? '' : ' (optional)' }}</label>
                <input class="form-control" id="{{ $formId }}-email" name="email" type="email" value="{{ $prefillEmail ?? old('email') }}" @if($lockContactFields) readonly @endif @if($requireContactDetails) required @endif>
            </div>
            <div class="col-md-6">
                <label for="{{ $formId }}-mobile">Mobile{{ $requireContactDetails ? '' : ' (optional)' }}</label>
                <input class="form-control" id="{{ $formId }}-mobile" name="mobile" @if($requireContactDetails) required @endif>
            </div>
            @if($showPlainAmountField)
            <div class="col-md-6">
                <label for="{{ $formId }}-amount">Amount ({{ $temple['currency'] }})</label>
                <input class="form-control" id="{{ $formId }}-amount" name="amount" type="number" min="1" step=".01" required>
                <div class="quick-amount-row d-flex flex-wrap gap-2 mt-2" id="{{ $formId }}-quick-amounts">
                    @foreach([101, 501, 1001, 2001] as $qa)
                        <button type="button" class="quick-amount-chip" data-amount="{{ $qa }}">{{ $qa }}</button>
                    @endforeach
                </div>
            </div>
            @endif

            @if($lockedEvent)
                <input type="hidden" name="event_id" value="{{ $lockedEvent->event_id }}">
                <div class="col-12">
                    <label>Donating towards</label>
                    <div class="locked-event-badge"><i class="bi bi-calendar-heart me-2"></i>{{ $lockedEvent->event_name }}</div>
                </div>

                @if($singleOption)
                    @if($singleOption->amount === null)
                        <div class="col-md-6">
                            <label for="{{ $formId }}-amount">Donation Amount ({{ $temple['currency'] }})</label>
                            <input class="form-control" id="{{ $formId }}-amount" name="amount" type="number" min="1" step=".01" required>
                            <div class="quick-amount-row d-flex flex-wrap gap-2 mt-2" id="{{ $formId }}-quick-amounts">
                                @foreach([101, 501, 1001, 2001] as $qa)
                                    <button type="button" class="quick-amount-chip" data-amount="{{ $qa }}">{{ $qa }}</button>
                                @endforeach
                            </div>
                            <input type="hidden" name="selections_json" id="{{ $formId }}-selections-json" value="">
                        </div>
                    @elseif($singleOption->allow_quantity)
                        <div class="col-12">
                            <label>Donation Amount</label>
                            <div class="d-flex align-items-center gap-2" style="max-width:180px;">
                                <label class="mb-0 small">Quantity</label>
                                <input type="number" min="1" value="1" class="form-control" id="{{ $formId }}-single-qty">
                            </div>
                            <input type="hidden" name="amount" id="{{ $formId }}-amount" value="{{ $singleOption->amount }}">
                            <input type="hidden" name="selections_json" id="{{ $formId }}-selections-json">
                        </div>
                    @else
                        <div class="col-12">
                            <label>Donation Amount</label>
                            <div class="bank-value">{{ $temple['currency'] }} {{ number_format($singleOption->amount, 2) }}</div>
                            <input type="hidden" name="amount" value="{{ $singleOption->amount }}">
                            <input type="hidden" name="selections_json" value="{{ json_encode([['option_id' => $singleOption->id, 'label' => $singleOption->label, 'quantity' => null, 'amount' => (float) $singleOption->amount]]) }}">
                        </div>
                    @endif
                    <input type="hidden" name="purpose" value="{{ $singleOption->label }}">
                @elseif($useTiers)
                    <div class="col-12">
                        <label>Choose how you'd like to contribute (select as many as you like)</label>
                        <div class="donation-tier-options" id="{{ $formId }}-tiers">
                            @foreach($donationOptions as $option)
                                <div class="donation-tier-option">
                                    <label class="tier-option-label">
                                        <input type="checkbox" name="{{ $formId }}_tier_choice[]" value="{{ $option->id }}" data-amount="{{ $option->amount ?? '' }}" data-allow-qty="{{ $option->allow_quantity ? '1' : '0' }}" data-label="{{ $option->label }}">
                                        <span class="tier-option-text">
                                            <strong>{{ $option->label }}</strong>
                                            <span class="tier-amount">@if($option->amount !== null){{ $temple['currency'] }} {{ number_format($option->amount, 2) }}@if($option->allow_quantity) each @endif @else Any amount @endif</span>
                                        </span>
                                    </label>
                                    @if($option->allow_quantity)
                                        <div class="tier-qty-wrap" style="display:none;">
                                            <label class="small mb-0 me-2">Qty</label>
                                            <input type="number" min="1" value="1" class="form-control form-control-sm tier-qty-input">
                                        </div>
                                    @elseif($option->amount === null)
                                        <div class="tier-qty-wrap flex-column align-items-stretch" style="display:none;">
                                            <div class="d-flex align-items-center">
                                                <label class="small mb-0 me-2">{{ $temple['currency'] }}</label>
                                                <input type="number" min="1" step=".01" placeholder="Amount" class="form-control form-control-sm tier-free-amount-input">
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 mt-1 tier-free-quick-amounts">
                                                @foreach([101, 501, 1001, 2001] as $qa)
                                                    <button type="button" class="quick-amount-chip" style="padding:4px 10px; font-size:0.78rem;" data-amount="{{ $qa }}">{{ $qa }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="tier-total-row d-flex justify-content-between align-items-center mt-2">
                            <span class="fw-bold">Total amount</span>
                            <span class="fw-bold" id="{{ $formId }}-tier-total">{{ $temple['currency'] }} 0.00</span>
                        </div>
                        <input type="hidden" name="amount" id="{{ $formId }}-amount">
                        <input type="hidden" name="purpose" id="{{ $formId }}-purpose">
                        <input type="hidden" name="selections_json" id="{{ $formId }}-selections-json">
                    </div>
                @else
                    <input type="hidden" name="purpose" value="Event Donation">
                @endif
            @else
                <div class="col-md-6">
                    <label for="{{ $formId }}-purpose">Purpose</label>
                    <select class="form-select" id="{{ $formId }}-purpose" name="purpose" required>
                        <option>General Donation</option>
                        <option>Temple Maintenance</option>
                        <option>Pooja</option>
                        <option>Community</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="{{ $formId }}-event_id">Donate towards an event (optional)</label>
                    <select class="form-select" id="{{ $formId }}-event_id" name="event_id">
                        <option value="">General fund</option>
                        @foreach($events as $event)
                            <option value="{{ $event->event_id }}">{{ $event->event_name }} · {{ date('d M Y', strtotime($event->event_date)) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-12">
                <label for="{{ $formId }}-purpose_details">Details / Dedication (optional)</label>
                <textarea class="form-control" id="{{ $formId }}-purpose_details" name="purpose_details" rows="2" placeholder="In honour of... or any other details about this donation"></textarea>
            </div>
            <input type="hidden" name="transaction_id" value="">
            <div class="col-12">
                <button class="btn w-100 py-3" type="submit" data-label-Bank="Record my bank transfer" data-label-Cash="Record my cash pledge" data-label-Stripe="Continue with Stripe">Record my bank transfer</button>
                <small class="text-muted d-block mt-2"><i class="bi bi-shield-check me-1"></i>Secure {{ $temple['currency'] }} donation processing</small>
            </div>
        </div>
    </form>
</div>
<script>
(function () {
    var root = document.getElementById('{{ $formId }}-tabs');
    if (!root || root.dataset.bound) { return; }
    root.dataset.bound = '1';
    var methodInput = document.getElementById('{{ $formId }}-method');
    var submitBtn = document.querySelector('#{{ $formId }} button[type="submit"]');
    root.querySelectorAll('.nav-link').forEach(function (tab) {
        tab.addEventListener('click', function () {
            root.querySelectorAll('.nav-link').forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var method = tab.getAttribute('data-method');
            methodInput.value = method;
            document.querySelectorAll('[data-method-info]').forEach(function (panel) {
                if (panel.closest('.donate-tabs-card') === root.closest('.donate-tabs-card')) {
                    panel.style.display = (panel.getAttribute('data-method-info') === method) ? '' : 'none';
                }
            });
            if (submitBtn) { submitBtn.textContent = submitBtn.getAttribute('data-label-' + method); }
        });
    });

    // Quick donation amount presets (only present when the plain Amount field is shown,
    // i.e. no event tiers) — click to fill it in instantly instead of typing.
    var quickAmountsRow = document.getElementById('{{ $formId }}-quick-amounts');
    var amountField = document.getElementById('{{ $formId }}-amount');
    if (quickAmountsRow && amountField) {
        quickAmountsRow.querySelectorAll('.quick-amount-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                amountField.value = chip.getAttribute('data-amount');
                quickAmountsRow.querySelectorAll('.quick-amount-chip').forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
            });
        });
        amountField.addEventListener('input', function () {
            quickAmountsRow.querySelectorAll('.quick-amount-chip').forEach(function (c) {
                c.classList.toggle('active', c.getAttribute('data-amount') === amountField.value);
            });
        });
    }
})();
</script>
@if($singleOption && $singleOption->amount === null)
<script>
(function () {
    // The single free-amount option is always "selected" — no checkbox — so its
    // selections_json needs to track the plain Amount field directly instead of a tier recalc.
    var amountField = document.getElementById('{{ $formId }}-amount');
    var selectionsHidden = document.getElementById('{{ $formId }}-selections-json');
    if (!amountField || !selectionsHidden) { return; }
    var optionId = {{ (int) $singleOption->id }};
    var label = @json($singleOption->label);

    function recalc() {
        var amount = parseFloat(amountField.value) || 0;
        selectionsHidden.value = amount > 0 ? JSON.stringify([{ option_id: optionId, label: label, quantity: null, amount: amount }]) : '';
    }
    amountField.addEventListener('input', recalc);
    recalc();
})();
</script>
@endif
@if($singleOption && $singleOption->allow_quantity)
<script>
(function () {
    var qtyInput = document.getElementById('{{ $formId }}-single-qty');
    var amountHidden = document.getElementById('{{ $formId }}-amount');
    var selectionsHidden = document.getElementById('{{ $formId }}-selections-json');
    if (!qtyInput || !amountHidden) { return; }
    var baseAmount = {{ (float) $singleOption->amount }};
    var optionId = {{ (int) $singleOption->id }};
    var label = @json($singleOption->label);

    function recalc() {
        var qty = parseInt(qtyInput.value, 10) || 1;
        var total = baseAmount * qty;
        amountHidden.value = total.toFixed(2);
        if (selectionsHidden) {
            selectionsHidden.value = JSON.stringify([{ option_id: optionId, label: label, quantity: qty, amount: total }]);
        }
    }
    qtyInput.addEventListener('input', recalc);
    recalc();
})();
</script>
@endif
@if($useTiers)
<script>
(function () {
    var wrap = document.getElementById('{{ $formId }}-tiers');
    if (!wrap || wrap.dataset.bound) { return; }
    wrap.dataset.bound = '1';

    // Quick-amount presets for each free-amount tier option — fills the amount and checks
    // the option's box (dispatching input so the existing recalc()/total logic picks it up).
    wrap.querySelectorAll('.donation-tier-option').forEach(function (row) {
        var freeInput = row.querySelector('.tier-free-amount-input');
        var checkbox = row.querySelector('input[type="checkbox"]');
        row.querySelectorAll('.tier-free-quick-amounts .quick-amount-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                if (checkbox) { checkbox.checked = true; }
                freeInput.value = chip.getAttribute('data-amount');
                row.querySelectorAll('.quick-amount-chip').forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
                freeInput.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    });

    var amountHidden = document.getElementById('{{ $formId }}-amount');
    var purposeHidden = document.getElementById('{{ $formId }}-purpose');
    var selectionsHidden = document.getElementById('{{ $formId }}-selections-json');
    var totalDisplay = document.getElementById('{{ $formId }}-tier-total');
    var submitBtn = document.querySelector('#{{ $formId }} button[type="submit"]');
    var currency = @json($temple['currency']);

    function recalc() {
        var total = 0;
        var labels = [];
        var selections = [];

        wrap.querySelectorAll('.donation-tier-option').forEach(function (row) {
            var checkbox = row.querySelector('input[type="checkbox"]');
            var extra = row.querySelector('.tier-qty-wrap');
            var isChecked = checkbox.checked;
            if (extra) { extra.style.display = isChecked ? 'flex' : 'none'; }
            row.classList.toggle('selected', isChecked);
            if (!isChecked) { return; }

            var baseAmountRaw = checkbox.getAttribute('data-amount');
            var allowQty = checkbox.getAttribute('data-allow-qty') === '1';
            var label = checkbox.getAttribute('data-label');
            var optionId = checkbox.value;
            var qty = null;
            var amount = 0;

            if (baseAmountRaw !== '') {
                var baseAmount = parseFloat(baseAmountRaw) || 0;
                if (allowQty) {
                    var qtyInput = row.querySelector('.tier-qty-input');
                    qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
                    amount = baseAmount * qty;
                    if (qty > 1) { label = label + ' (x' + qty + ')'; }
                } else {
                    amount = baseAmount;
                }
            } else {
                var freeInput = row.querySelector('.tier-free-amount-input');
                amount = freeInput ? (parseFloat(freeInput.value) || 0) : 0;
            }

            if (amount > 0) {
                total += amount;
                labels.push(label);
                selections.push({ option_id: optionId, label: label, quantity: qty, amount: amount });
            }
        });

        amountHidden.value = total.toFixed(2);
        purposeHidden.value = labels.length ? labels.join(', ').substring(0, 250) : 'Event Donation';
        if (selectionsHidden) { selectionsHidden.value = JSON.stringify(selections); }
        if (totalDisplay) { totalDisplay.textContent = currency + ' ' + total.toFixed(2); }
        if (submitBtn) { submitBtn.disabled = total <= 0; }
    }

    wrap.addEventListener('change', recalc);
    wrap.addEventListener('input', recalc);
    recalc();
})();
</script>
@endif
