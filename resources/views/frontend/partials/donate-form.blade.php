@php
    // Expects: $temple (array), optional $events (collection, for the general form's event picker),
    // optional $lockedEvent (Event model, for the per-event donation page), $formAction (route url), $formId (unique dom id)
    $formId = $formId ?? 'donate-form';
    $lockedEvent = $lockedEvent ?? null;
    $events = $events ?? collect();
    $donationOptions = $donationOptions ?? collect();
    $useTiers = $lockedEvent && $donationOptions->isNotEmpty();
@endphp
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
            <button class="nav-link" data-method="Stripe" type="button" role="tab">
                <i class="bi bi-credit-card me-1"></i> Online Payment
            </button>
        </li>
    </ul>

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
                <input class="form-control" id="{{ $formId }}-donor_name" name="donor_name" required>
            </div>
            <div class="col-md-6">
                <label for="{{ $formId }}-email">Email for receipt</label>
                <input class="form-control" id="{{ $formId }}-email" name="email" type="email">
            </div>
            <div class="col-md-6">
                <label for="{{ $formId }}-mobile">Mobile (optional)</label>
                <input class="form-control" id="{{ $formId }}-mobile" name="mobile">
            </div>
            @if(!$useTiers)
            <div class="col-md-6">
                <label for="{{ $formId }}-amount">Amount ({{ $temple['currency'] }})</label>
                <input class="form-control" id="{{ $formId }}-amount" name="amount" type="number" min="1" step=".01" required>
            </div>
            @endif

            @if($lockedEvent)
                <input type="hidden" name="event_id" value="{{ $lockedEvent->event_id }}">
                <div class="col-12">
                    <label>Donating towards</label>
                    <div class="locked-event-badge"><i class="bi bi-calendar-heart me-2"></i>{{ $lockedEvent->event_name }}</div>
                </div>

                @if($useTiers)
                    <div class="col-12">
                        <label>Choose how you'd like to contribute</label>
                        <div class="donation-tier-options" id="{{ $formId }}-tiers">
                            @foreach($donationOptions as $option)
                                <div class="donation-tier-option">
                                    <label class="tier-option-label">
                                        <input type="radio" name="{{ $formId }}_tier_choice" value="{{ $option->id }}" data-amount="{{ $option->amount ?? '' }}" data-allow-qty="{{ $option->allow_quantity ? '1' : '0' }}" data-label="{{ $option->label }}" {{ $loop->first ? 'checked' : '' }}>
                                        <span class="tier-option-text">
                                            <strong>{{ $option->label }}</strong>
                                            <span class="tier-amount">@if($option->amount !== null){{ $temple['currency'] }} {{ number_format($option->amount, 2) }}@if($option->allow_quantity) each @endif @else Any amount @endif</span>
                                        </span>
                                    </label>
                                    @if($option->allow_quantity)
                                        <div class="tier-qty-wrap">
                                            <label class="small mb-0 me-2">Qty</label>
                                            <input type="number" min="1" value="1" class="form-control form-control-sm tier-qty-input">
                                        </div>
                                    @elseif($option->amount === null)
                                        <div class="tier-qty-wrap">
                                            <label class="small mb-0 me-2">{{ $temple['currency'] }}</label>
                                            <input type="number" min="1" step=".01" placeholder="Amount" class="form-control form-control-sm tier-free-amount-input">
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <input type="hidden" name="amount" id="{{ $formId }}-amount">
                        <input type="hidden" name="purpose" id="{{ $formId }}-purpose">
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
                <label for="{{ $formId }}-purpose_details">Dedication (optional)</label>
                <input class="form-control" id="{{ $formId }}-purpose_details" name="purpose_details" placeholder="In honour of...">
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
})();
</script>
@if($useTiers)
<script>
(function () {
    var wrap = document.getElementById('{{ $formId }}-tiers');
    if (!wrap || wrap.dataset.bound) { return; }
    wrap.dataset.bound = '1';
    var amountHidden = document.getElementById('{{ $formId }}-amount');
    var purposeHidden = document.getElementById('{{ $formId }}-purpose');

    function recalc() {
        var checked = wrap.querySelector('input[type="radio"]:checked');
        wrap.querySelectorAll('.donation-tier-option').forEach(function (row) {
            var extra = row.querySelector('.tier-qty-wrap');
            var isChecked = row.querySelector('input[type="radio"]').checked;
            if (extra) { extra.style.display = isChecked ? 'flex' : 'none'; }
            row.classList.toggle('selected', isChecked);
        });
        if (!checked) { return; }
        var row = checked.closest('.donation-tier-option');
        var baseAmountRaw = checked.getAttribute('data-amount');
        var allowQty = checked.getAttribute('data-allow-qty') === '1';
        var amount = 0;
        if (baseAmountRaw !== '') {
            var baseAmount = parseFloat(baseAmountRaw) || 0;
            if (allowQty) {
                var qtyInput = row.querySelector('.tier-qty-input');
                var qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
                amount = baseAmount * qty;
            } else {
                amount = baseAmount;
            }
        } else {
            var freeInput = row.querySelector('.tier-free-amount-input');
            amount = freeInput ? (parseFloat(freeInput.value) || 0) : 0;
        }
        amountHidden.value = amount;
        purposeHidden.value = checked.getAttribute('data-label');
    }

    wrap.addEventListener('change', recalc);
    wrap.addEventListener('input', recalc);
    recalc();
})();
</script>
@endif
