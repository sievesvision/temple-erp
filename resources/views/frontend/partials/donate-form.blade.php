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
    $requireDonorEmail = $requireDonorEmail ?? false;
    $requireDonorMobile = $requireDonorMobile ?? false;
    // A locked event's own donation account/contact-email override (if it set one) takes
    // over the bank-transfer card entirely; otherwise this is just the temple's global ones.
    $bankAccountName = $lockedEvent ? $lockedEvent->effectiveDonationAccountName() : $temple['donation_account_name'];
    $bankName = $lockedEvent ? $lockedEvent->effectiveDonationBankName() : $temple['donation_bank_name'];
    $bankBsb = $lockedEvent ? $lockedEvent->effectiveDonationBsb() : $temple['donation_bsb'];
    $bankAccountNumber = $lockedEvent ? $lockedEvent->effectiveDonationAccountNumber() : $temple['donation_account_number'];
    $receiptContactEmails = $lockedEvent ? $lockedEvent->donationContactEmailList() : array_filter([$temple['donation_receipt_email'] ?? null]);
@endphp
<style>
    .quick-amount-chip {
        background: #f5f0e6;
        border: 2px solid transparent;
        color: var(--primary, #b8863a);
        font-weight: 700;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: 0.15s;
    }
    .quick-amount-chip:hover, .quick-amount-chip.active {
        background: var(--primary, #b8863a);
        color: white;
    }

    /* A plain inline alert here used to be the only confirmation a donor got — easy to miss
       entirely, since redirect()->back() after submitting lands them back at the TOP of the
       page (fragments like #donate-now are never sent to the server, so there's no way to
       redirect straight back to this section), leaving the message sitting below the fold
       until/unless they scroll all the way back down to it. A full-screen popup guarantees
       it's seen regardless of scroll position, the same treatment the POS confirmation uses. */
    .donate-confirm-overlay { position: fixed; inset: 0; background: rgba(37,35,31,0.6); z-index: 2000; display: none; align-items: center; justify-content: center; padding: 20px; cursor: pointer; }
    .donate-confirm-overlay.active { display: flex; }
    .donate-confirm-box { background: #fff; border-radius: 18px; max-width: 440px; width: 100%; overflow: hidden; text-align: center; box-shadow: 0 30px 70px rgba(0,0,0,0.3); cursor: default; }
    .donate-confirm-header { background: linear-gradient(135deg, var(--primary, #b8863a), color-mix(in srgb, var(--primary, #b8863a) 55%, black)); color: #fff; padding: 16px 20px; font-weight: 800; letter-spacing: 0.04em; font-size: 0.92rem; text-transform: uppercase; }
    .donate-confirm-box.is-error .donate-confirm-header { background: linear-gradient(135deg, #c0392b, #7b241c); }
    .donate-confirm-body { padding: 2rem 2rem 1.75rem; }
    .donate-confirm-icon { font-size: 3rem; color: var(--primary, #b8863a); margin-bottom: 0.75rem; }
    .donate-confirm-box.is-error .donate-confirm-icon { color: #c0392b; }
    .donate-confirm-message { font-size: 1.05rem; color: var(--ink, #25231f); line-height: 1.6; margin-bottom: 1.5rem; }
    .donate-confirm-ok { background: linear-gradient(135deg, var(--primary, #b8863a), color-mix(in srgb, var(--primary, #b8863a) 55%, black)); color: #fff; border: none; font-weight: 700; padding: 0.85rem 2.25rem; border-radius: 8px; font-size: 1rem; cursor: pointer; }

    /* Each part of the form (details / donating-towards / amount) reads as its own small
       card with an icon-badge heading, rather than one long undifferentiated field list —
       mirrors how the rest of this site already introduces a section with an icon + title. */
    .donate-group-card { background: color-mix(in srgb, var(--primary, #b8863a) 5%, white); border: 1px solid color-mix(in srgb, var(--primary, #b8863a) 16%, white); border-radius: 12px; padding: 1.1rem 1.1rem 1.25rem; margin-bottom: 1rem; }
    .donate-group-head { display: flex; align-items: center; gap: .65rem; margin-bottom: 1rem; }
    .donate-group-icon { width: 34px; height: 34px; border-radius: 50%; background: var(--primary, #b8863a); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .95rem; flex-shrink: 0; }
    .donate-group-head h3 { font-size: 1.05rem; margin: 0; color: var(--ink, #25231f); font-weight: 800; }

    /* The locked single-event "choice" — there's only ever one, so the ring reads as
       "this is what you're donating to" rather than an interactive radio. */
    .donate-event-row { display: flex; align-items: center; gap: .7rem; background: #fff; border: 1px solid var(--line, #e9e1d5); border-radius: 10px; padding: .8rem 1rem; }
    .donate-event-ring { width: 18px; height: 18px; border-radius: 50%; border: 2px solid var(--primary, #b8863a); flex-shrink: 0; position: relative; }
    .donate-event-ring::after { content: ''; position: absolute; inset: 3px; border-radius: 50%; background: var(--primary, #b8863a); }
    .donate-event-row .bank-value { margin: 0; }

    /* Quick amounts stay a rounder "chip" (unlike the form's small-curve buttons elsewhere)
       — outlined rather than filled, so the active one reads clearly against its siblings. */
    .quick-amount-chip { border-radius: 18px; }

    .donate-amount-group { display: flex; align-items: center; border: 1.5px solid var(--line, #e9e1d5); border-radius: 8px; background: color-mix(in srgb, var(--line, #e9e1d5) 20%, white); overflow: hidden; transition: border-color .15s ease, box-shadow .15s ease, background .15s ease; }
    .donate-amount-group:focus-within { border-color: var(--primary, #b8863a); background: #fff; box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary, #b8863a) 16%, transparent); }
    .donate-amount-group-prefix { padding: .7rem .9rem; color: var(--muted, #716c64); font-weight: 700; }
    .donate-amount-group .form-control { border: none !important; box-shadow: none !important; background: transparent !important; padding-left: 0; }

    .donate-method-tabs-foot { margin-top: .25rem; }
    .donate-submit-btn { display: flex; align-items: center; justify-content: center; gap: .6rem; }
</style>

@if(session('success_donation') || $errors->any())
<div class="donate-confirm-overlay active" id="{{ $formId }}-confirmOverlay">
    <div class="donate-confirm-box {{ $errors->any() ? 'is-error' : '' }}">
        <div class="donate-confirm-header">{{ $errors->any() ? 'Please Check Your Details' : 'Donation Confirmed' }}</div>
        <div class="donate-confirm-body">
        <div class="donate-confirm-icon"><i class="bi {{ $errors->any() ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill' }}"></i></div>
        @if(session('success_donation'))
            <p class="donate-confirm-message">{{ session('success_donation') }}</p>
        @else
            <p class="donate-confirm-message mb-0">
                @foreach($errors->all() as $error)
                    {{ $error }}@if(!$loop->last)<br>@endif
                @endforeach
            </p>
        @endif
        <button type="button" class="donate-confirm-ok">OK</button>
        </div>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('{{ $formId }}-confirmOverlay');
    if (!overlay || overlay.dataset.bound) { return; }
    overlay.dataset.bound = '1';
    var hideTimer = setTimeout(function () { overlay.classList.remove('active'); }, 8000);
    // Click anywhere (the OK button's own click bubbles here too) dismisses it.
    overlay.addEventListener('click', function () {
        clearTimeout(hideTimer);
        overlay.classList.remove('active');
    });
})();
</script>
@endif

<div class="donate-tabs-card">
    <div class="donate-method-info mb-4" data-method-info="Bank">
        <div class="donation-bank-card">
            <div class="row g-3">
                <div class="col-md-7"><span class="bank-label">Account name</span><strong class="bank-value">{{ $bankAccountName }}</strong></div>
                <div class="col-md-5"><span class="bank-label">Bank</span><strong class="bank-value">{{ $bankName }}</strong></div>
                <div class="col-md-5"><span class="bank-label">BSB number</span><strong class="bank-value">{{ $bankBsb }}</strong></div>
                <div class="col-md-7"><span class="bank-label">Account number</span><strong class="bank-value">{{ $bankAccountNumber }}</strong></div>
            </div>
            <hr>
            <p class="mb-0 small">
                Transfer directly using these details, then submit the form below so we can match your receipt.
                @if(count($receiptContactEmails))
                    Send a copy of your transfer receipt to
                    @foreach($receiptContactEmails as $i => $contactEmail)
                        <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>{{ $i < count($receiptContactEmails) - 1 ? ' or ' : '' }}
                    @endforeach
                    for an official receipt.
                @endif
            </p>
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

        <div class="donate-group-card">
            <div class="donate-group-head"><span class="donate-group-icon"><i class="bi bi-person-fill"></i></span><h3>Your details</h3></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="{{ $formId }}-donor_name">Your name <span class="text-danger">*</span></label>
                    <input class="form-control" id="{{ $formId }}-donor_name" name="donor_name" placeholder="Enter your full name" value="{{ $prefillName ?? old('donor_name') }}" @if($lockContactFields) readonly @endif required>
                </div>
                <div class="col-md-6">
                    <label for="{{ $formId }}-email">Email for receipt{{ $requireDonorEmail ? '' : ' (optional)' }}</label>
                    <input class="form-control" id="{{ $formId }}-email" name="email" type="email" placeholder="Enter your email address" value="{{ $prefillEmail ?? old('email') }}" @if($lockContactFields) readonly @endif @if($requireDonorEmail) required @endif>
                </div>
                <div class="col-md-6">
                    <label for="{{ $formId }}-mobile">Mobile{{ $requireDonorMobile ? '' : ' (optional)' }}</label>
                    <input class="form-control" id="{{ $formId }}-mobile" name="mobile" placeholder="Enter your mobile number" @if($requireDonorMobile) required @endif>
                </div>
                @if(!$lockedEvent)
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
            </div>
        </div>

        @if($showPlainAmountField)
        <div class="donate-group-card">
            <div class="donate-group-head"><span class="donate-group-icon"><i class="bi bi-currency-exchange"></i></span><h3>Amount ({{ $temple['currency'] }})</h3></div>
            <div class="donate-amount-group">
                <span class="donate-amount-group-prefix">{{ $temple['currency'] }}</span>
                <input class="form-control" id="{{ $formId }}-amount" name="amount" type="number" min="1" step=".01" placeholder="Enter amount" required>
            </div>
            <div class="quick-amount-row d-flex flex-wrap gap-2 mt-3" id="{{ $formId }}-quick-amounts">
                @foreach([101, 501, 1001, 2001] as $qa)
                    <button type="button" class="quick-amount-chip" data-amount="{{ $qa }}">{{ $qa }}</button>
                @endforeach
            </div>
        </div>
        @endif

        @if($lockedEvent)
            <input type="hidden" name="event_id" value="{{ $lockedEvent->event_id }}">
            <div class="donate-group-card">
                <div class="donate-group-head"><span class="donate-group-icon"><i class="bi bi-building"></i></span><h3>Donating towards</h3></div>
                <div class="donate-event-row">
                    <span class="donate-event-ring"></span>
                    <i class="bi bi-building" style="color:var(--primary, #b8863a);"></i>
                    <strong class="bank-value">{{ $lockedEvent->event_name }}</strong>
                </div>
            </div>

            @if($singleOption)
                <div class="donate-group-card">
                    <div class="donate-group-head"><span class="donate-group-icon"><i class="bi bi-currency-exchange"></i></span><h3>Donation Amount ({{ $temple['currency'] }})</h3></div>
                    @if($singleOption->amount === null)
                        <div class="donate-amount-group">
                            <span class="donate-amount-group-prefix">{{ $temple['currency'] }}</span>
                            <input class="form-control" id="{{ $formId }}-amount" name="amount" type="number" min="1" step=".01" placeholder="Enter amount" required>
                        </div>
                        <div class="quick-amount-row d-flex flex-wrap gap-2 mt-3" id="{{ $formId }}-quick-amounts">
                            @foreach([101, 501, 1001, 2001] as $qa)
                                <button type="button" class="quick-amount-chip" data-amount="{{ $qa }}">{{ $qa }}</button>
                            @endforeach
                        </div>
                        <input type="hidden" name="selections_json" id="{{ $formId }}-selections-json" value="">
                    @elseif($singleOption->allow_quantity)
                        <div class="d-flex align-items-center gap-2" style="max-width:180px;">
                            <label class="mb-0 small">Quantity</label>
                            <input type="number" min="1" value="1" class="form-control" id="{{ $formId }}-single-qty">
                        </div>
                        <input type="hidden" name="amount" id="{{ $formId }}-amount" value="{{ $singleOption->amount }}">
                        <input type="hidden" name="selections_json" id="{{ $formId }}-selections-json">
                    @else
                        <div class="bank-value">{{ $temple['currency'] }} {{ number_format($singleOption->amount, 2) }}</div>
                        <input type="hidden" name="amount" value="{{ $singleOption->amount }}">
                        <input type="hidden" name="selections_json" value="{{ json_encode([['option_id' => $singleOption->id, 'label' => $singleOption->label, 'quantity' => null, 'amount' => (float) $singleOption->amount]]) }}">
                    @endif
                </div>
                <input type="hidden" name="purpose" value="{{ $singleOption->label }}">
            @elseif($useTiers)
                <div class="donate-group-card">
                    <div class="donate-group-head"><span class="donate-group-icon"><i class="bi bi-currency-exchange"></i></span><h3>Donation Amount</h3></div>
                    <label class="small text-muted mb-2 d-block">Choose how you'd like to contribute (select as many as you like)</label>
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
        @endif

        <div class="row g-3">
            <div class="col-12">
                <label for="{{ $formId }}-purpose_details">Details / Dedication (optional)</label>
                <textarea class="form-control" id="{{ $formId }}-purpose_details" name="purpose_details" rows="2" placeholder="In honour of... or any other details about this donation"></textarea>
            </div>
            <input type="hidden" name="transaction_id" value="">
            @if($requireCaptcha ?? false)
            <div class="col-12">
                @include('partials.recaptcha-widget')
            </div>
            @endif

            <div class="col-12">
                <label class="d-block mb-2">How would you like to donate?</label>
                <ul class="nav nav-pills donate-method-tabs" id="{{ $formId }}-tabs" role="tablist">
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
                    <div class="small text-muted donate-method-tabs-foot"><i class="bi bi-info-circle me-1"></i>Online payment is temporarily unavailable. Please use Bank Transfer or Cash at Temple instead.</div>
                @endif
            </div>

            <div class="col-12">
                <button class="btn w-100 py-3 donate-submit-btn" type="submit" data-label-Bank="Record my bank transfer" data-label-Cash="Record my cash pledge" data-label-Stripe="Continue with Stripe"><i class="bi bi-heart-fill"></i> <span class="donate-submit-label">Record my bank transfer</span> <i class="bi bi-arrow-right"></i></button>
                <small class="text-muted d-block mt-2 text-center"><i class="bi bi-shield-check me-1"></i>Secure {{ $temple['currency'] }} donation processing</small>
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
            if (submitBtn) {
                var label = submitBtn.querySelector('.donate-submit-label');
                var text = submitBtn.getAttribute('data-label-' + method);
                if (label) { label.textContent = text; } else { submitBtn.textContent = text; }
            }
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
