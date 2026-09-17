<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>POS · {{ $event->event_name }}</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A;
            --maroon-dark: #4A0A12;
            --gold: #C89B3C;
            --gold-hover: #A67C2B;
            --cream: #F9F3E7;
            --white: #FFFFFF;
            --border: #F0E5D6;
            --text-primary: #1F2A37;
            --text-secondary: #6B7280;
            --success: #10B981;
            --error: #EF4444;
            --serif: 'Playfair Display', Georgia, serif;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { overflow-x: hidden; height: 100%; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--cream); color: var(--text-primary); display: flex; flex-direction: column; }
        h1, h2 { font-family: var(--serif); }
        button, input, select, textarea { font-family: inherit; }

        /* ---------- Minimal topbar — no dashboard chrome, just identity + exits ---------- */
        .pos-topbar {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            color: white; padding: 12px 20px; display: flex; align-items: center; gap: 14px;
            flex-shrink: 0; box-shadow: 0 4px 18px rgba(74,10,18,0.25); z-index: 20;
        }
        .pos-topbar-title { flex: 1; min-width: 0; }
        .pos-topbar-title h1 { font-size: clamp(1.05rem, 2.6vw, 1.35rem); font-weight: 800; color: var(--gold); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pos-topbar-title .pos-subtitle { font-size: 0.7rem; color: rgba(255,255,255,0.65); text-transform: uppercase; letter-spacing: 0.06em; }
        .pos-topbar-btn { background: rgba(255,255,255,0.12); border: none; color: white; width: 42px; height: 42px; border-radius: 12px; font-size: 1.05rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .pos-topbar-btn:hover { background: rgba(255,255,255,0.22); }

        /* ---------- Main entry area ---------- */
        .pos-main { flex: 1; min-height: 0; overflow-y: auto; padding: 18px 16px 8px; }
        .pos-card { max-width: 760px; margin: 0 auto; background: var(--white); border-radius: 18px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(31,42,55,0.05); padding: 20px clamp(16px, 3vw, 30px); }

        .pos-field-label { display: block; font-weight: 700; font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; }
        .pos-input {
            width: 100%; padding: 16px 18px; border: 2px solid var(--border); border-radius: 14px; font-size: 1.15rem; font-weight: 600;
            color: var(--text-primary); background: var(--white); min-height: 58px;
        }
        .pos-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 4px rgba(200,155,60,0.15); }
        .pos-textarea { min-height: auto; font-weight: 500; font-size: 1rem; resize: vertical; margin-bottom: 14px; }
        .pos-row { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 14px; }
        .pos-row.two-col { grid-template-columns: 1fr; }
        @media (min-width: 560px) { .pos-row.two-col { grid-template-columns: 1fr 1fr; } }

        .pos-section-title { font-weight: 800; font-size: 0.95rem; color: var(--text-primary); margin: 20px 0 10px; }
        .pos-section-title:first-child { margin-top: 0; }

        .pos-quick-amounts { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
        .pos-quick-amount-btn { flex: 1 1 calc(25% - 10px); min-width: 90px; background: var(--white); border: 2px solid var(--border); color: var(--gold-hover); font-weight: 800; padding: 16px 8px; min-height: 58px; border-radius: 14px; font-size: 1.05rem; }
        .pos-quick-amount-btn.active { background: var(--gold); border-color: var(--gold); color: white; box-shadow: 0 6px 16px rgba(200,155,60,0.32); }

        .pos-tier-option { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px; border: 2px solid var(--border); border-radius: 14px; margin-bottom: 10px; flex-wrap: wrap; background: var(--white); }
        .pos-tier-option.selected { border-color: var(--gold); background: #FDF6EA; }
        .pos-tier-option.single-option input[type="checkbox"] { display: none; }
        .pos-tier-option label { display: flex; align-items: center; gap: 12px; margin: 0; cursor: pointer; flex: 1; min-width: 160px; font-size: 1.02rem; }
        .pos-tier-option input[type="checkbox"] { width: 26px; height: 26px; accent-color: var(--gold); flex-shrink: 0; }
        .pos-tier-option .pos-tier-qty { width: 70px; padding: 10px; font-size: 1rem; border: 2px solid var(--border); border-radius: 10px; }
        .pos-tier-option .pos-tier-free { width: 120px; padding: 10px; font-size: 1rem; border: 2px solid var(--border); border-radius: 10px; }
        .pos-tier-free-quick-amounts { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; width: 100%; justify-content: flex-end; }
        .pos-tier-free-quick-amounts .pos-tier-quick-btn { background: var(--white); border: 2px solid var(--border); color: var(--gold-hover); font-weight: 700; padding: 8px 14px; min-height: 40px; border-radius: 10px; font-size: 0.85rem; }
        .pos-tier-free-quick-amounts .pos-tier-quick-btn:active { background: var(--gold); border-color: var(--gold); color: white; }
        .pos-tier-total-row { display: flex; justify-content: space-between; font-weight: 800; font-size: 1.1rem; color: var(--text-primary); padding: 10px 4px; }

        .pos-method-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; }
        .pos-method-btn { flex: 1 1 calc(33% - 10px); min-width: 110px; padding: 16px 10px; min-height: 60px; border-radius: 14px; border: 2px solid var(--border); background: var(--white); font-weight: 700; font-size: 0.95rem; color: var(--text-secondary); }
        .pos-method-btn.active { border-color: var(--gold); background: var(--gold); color: white; box-shadow: 0 6px 16px rgba(200,155,60,0.3); }
        .pos-method-btn i { display: block; font-size: 1.3rem; margin-bottom: 4px; }

        .pos-save-btn {
            width: 100%; padding: 20px; border-radius: 16px; border: none; margin-top: 18px;
            background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; font-size: 1.3rem;
            box-shadow: 0 10px 26px rgba(200,155,60,0.35); min-height: 68px;
        }
        .pos-save-btn:disabled { opacity: 0.55; }
        .pos-save-btn:active { transform: scale(0.98); }

        /* ---------- Orders this session — bottom strip ---------- */
        .pos-orders-bar { flex-shrink: 0; background: var(--white); border-top: 1px solid var(--border); padding: 10px clamp(16px, 3vw, 30px); box-shadow: 0 -4px 14px rgba(31,42,55,0.05); }
        .pos-orders-header { display: flex; align-items: center; justify-content: space-between; max-width: 760px; margin: 0 auto 6px; cursor: pointer; }
        .pos-orders-header h4 { margin: 0; font-size: 0.88rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .pos-orders-header .pos-orders-total { font-weight: 800; color: var(--gold-hover); font-size: 0.95rem; }
        .pos-orders-list { max-width: 760px; margin: 0 auto; max-height: 160px; overflow-y: auto; display: none; }
        .pos-orders-list.expanded { display: block; }
        .pos-order-item { display: flex; justify-content: space-between; gap: 10px; padding: 8px 4px; border-bottom: 1px solid var(--cream); font-size: 0.85rem; }
        .pos-order-item:last-child { border-bottom: none; }
        .pos-order-name { font-weight: 700; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
        .pos-order-amount { font-weight: 800; color: var(--text-primary); flex-shrink: 0; }
        .pos-order-time { color: var(--text-secondary); flex-shrink: 0; width: 70px; text-align: right; }
        .pos-orders-empty { color: var(--text-secondary); font-size: 0.85rem; text-align: center; padding: 10px 0; }

        .pos-toast { position: fixed; bottom: 24px; right: 24px; background: var(--success); color: white; padding: 18px 26px; border-radius: 14px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 14px 34px rgba(0,0,0,0.2); z-index: 999; display: none; }
        .pos-toast.error { background: var(--error); }

        /* ---------- EFT terminal status popup — center-screen, mirrors what's on the
           physical/virtual PIN pad while a card payment is in progress ---------- */
        .eft-modal-overlay {
            position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 1000;
            display: none; align-items: center; justify-content: center; padding: 20px;
        }
        .eft-modal-overlay.active { display: flex; }
        .eft-modal {
            background: var(--white); border-radius: 22px; width: 100%; max-width: 380px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.35); overflow: hidden; text-align: center;
        }
        .eft-modal-header {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            color: white; padding: 18px 20px; font-weight: 800; letter-spacing: 0.06em;
            font-size: 0.95rem; text-transform: uppercase;
        }
        .eft-modal-body { padding: 30px 26px 26px; }
        .eft-modal-amount { font-family: var(--serif); font-size: 2.4rem; font-weight: 800; color: var(--text-primary); margin-bottom: 18px; }
        .eft-modal-status-box {
            background: var(--cream); border: 2px solid var(--border); border-radius: 14px;
            padding: 18px 16px; min-height: 90px; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 6px; margin-bottom: 22px;
        }
        .eft-modal-spinner {
            width: 26px; height: 26px; border-radius: 50%;
            border: 3px solid rgba(200,155,60,0.25); border-top-color: var(--gold);
            animation: eftSpin 0.8s linear infinite; margin-bottom: 4px; display: none;
        }
        .eft-modal-status-box.pending .eft-modal-spinner { display: block; }
        .eft-modal-status-icon { font-size: 1.6rem; margin-bottom: 2px; display: none; }
        .eft-modal-status-box.success .eft-modal-status-icon.icon-success { display: block; color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-icon.icon-error { display: block; color: var(--error); }
        .eft-modal-status-line { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); letter-spacing: 0.02em; }
        .eft-modal-status-box.success .eft-modal-status-line { color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-line { color: var(--error); }
        @keyframes eftSpin { to { transform: rotate(360deg); } }
        .eft-modal-cancel-btn {
            width: 100%; padding: 14px; border-radius: 12px; border: 2px solid var(--border);
            background: var(--white); color: var(--text-secondary); font-weight: 700; font-size: 0.95rem;
        }
        .eft-modal-cancel-btn:active { background: var(--cream); }

        @media (max-width: 600px) {
            .pos-topbar-title .pos-subtitle { display: none; }
            .pos-quick-amount-btn { flex: 1 1 calc(50% - 10px); }
            .pos-method-btn { flex: 1 1 calc(50% - 10px); }
        }
    </style>
</head>
<body>
    <header class="pos-topbar">
        <div class="pos-topbar-title">
            <h1>{{ $event->event_name }}</h1>
            <div class="pos-subtitle">Donation POS</div>
        </div>
        @if($switchableEvents->count())
        <div class="dropdown">
            <button class="pos-topbar-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Switch Event">
                <i class="bi bi-arrow-left-right"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Switch Event</h6></li>
                @foreach($switchableEvents as $switchEvent)
                <li><a class="dropdown-item" href="{{ route('admin.events.pos', $switchEvent->event_id) }}">{{ $switchEvent->event_name }}</a></li>
                @endforeach
            </ul>
        </div>
        @endif
        <button type="button" class="pos-topbar-btn" id="posFullscreenBtn" title="Toggle fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
        @if($canReturnToConsole)
        <a href="{{ route('admin.events.console', $event->event_id) }}" class="pos-topbar-btn" title="Back to console"><i class="bi bi-grid-1x2-fill"></i></a>
        @endif
        <a href="{{ route('logout') }}" class="pos-topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </header>

    <div class="pos-main">
        <div class="pos-card">
            <div class="pos-row">
                <div>
                    <label class="pos-field-label">Donor Name</label>
                    <input type="text" class="pos-input" id="posGuestName" placeholder="Full name" autocomplete="off">
                </div>
            </div>
            <div class="pos-row two-col">
                <div>
                    <label class="pos-field-label">Mobile{{ $event->require_donor_mobile ? '' : ' (optional)' }}</label>
                    <input type="text" class="pos-input" id="posGuestMobile" placeholder="04XX XXX XXX" autocomplete="off">
                </div>
                <div>
                    <label class="pos-field-label">Email{{ $event->require_donor_email ? '' : ' (optional)' }}</label>
                    <input type="email" class="pos-input" id="posGuestEmail" placeholder="example@email.com" autocomplete="off">
                </div>
            </div>

            <div class="pos-section-title">Donation Amount</div>
            <div id="posTiersWrap" style="display:none;">
                <div id="posTiers"></div>
                <div class="pos-tier-total-row"><span>Total</span><span id="posTierTotal">{{ $temple['currency'] ?? '' }} 0.00</span></div>
            </div>
            <div id="posSimpleAmountWrap">
                <div class="pos-quick-amounts" id="posQuickAmounts"></div>
                <input type="text" inputmode="decimal" class="pos-input" id="posAmount" placeholder="Amount ({{ $temple['currency'] ?? '' }})">
            </div>

            <div class="pos-section-title">Details (optional)</div>
            <textarea class="pos-input pos-textarea" id="posDetails" rows="2" placeholder="e.g. In memory of..., family name, special request..."></textarea>

            <div class="pos-section-title">Payment Method</div>
            <div class="pos-method-row" id="posMethodRow"></div>

            <button type="button" class="pos-save-btn" id="posSaveBtn"><i class="bi bi-check-circle-fill me-2"></i>Save Donation</button>
        </div>
    </div>

    <div class="pos-orders-bar">
        <div class="pos-orders-header" id="posOrdersToggle">
            <h4><i class="bi bi-clock-history"></i>Orders This Session (<span id="posOrdersCount">0</span>)<i class="bi bi-chevron-up ms-1" id="posOrdersChevron"></i></h4>
            <span class="pos-orders-total" id="posOrdersTotal">{{ $temple['currency'] ?? '' }} 0.00</span>
        </div>
        <div class="pos-orders-list" id="posOrdersList"></div>
    </div>

    <div class="pos-toast" id="posToast"></div>

    <!-- Shown only if a previous EFT Terminal attempt was left unresolved by a refresh/
         crash — see the DOMContentLoaded handler and startOrResumeEftPurchase() below.
         Hidden by default; never auto-triggers a new charge on its own. -->
    <div id="eftResumeBanner" style="display:none; position:fixed; top:0; left:0; right:0; z-index:2000; background:#7a1f1f; color:#fff; padding:12px 18px; align-items:center; gap:14px; flex-wrap:wrap; justify-content:center;">
        <span id="eftResumeBannerText"></span>
        <button type="button" id="eftResumeBannerBtn" style="background:#fff; color:#7a1f1f; border:none; border-radius:8px; padding:6px 16px; font-weight:700;">Resume Checking</button>
        <button type="button" id="eftResumeBannerDismissBtn" style="background:transparent; color:#fff; border:1px solid #fff; border-radius:8px; padding:6px 16px;">Dismiss</button>
    </div>

    <div class="eft-modal-overlay" id="eftModalOverlay">
        <div class="eft-modal">
            <div class="eft-modal-header"><i class="bi bi-credit-card-2-front-fill me-2"></i>Card Payment</div>
            <div class="eft-modal-body">
                <div class="eft-modal-amount" id="eftModalAmount">{{ $temple['currency'] ?? '' }} 0.00</div>
                <div class="eft-modal-status-box pending" id="eftModalStatusBox">
                    <div class="eft-modal-spinner"></div>
                    <i class="bi bi-check-circle-fill eft-modal-status-icon icon-success"></i>
                    <i class="bi bi-x-circle-fill eft-modal-status-icon icon-error"></i>
                    <span class="eft-modal-status-line" id="eftModalStatusLine1">Starting…</span>
                    <span class="eft-modal-status-line" id="eftModalStatusLine2"></span>
                </div>
                <button type="button" class="eft-modal-cancel-btn" id="eftModalCancelBtn">Cancel Payment</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        const EVENT_OPTIONS = @json($eventOptionsForJs);
        const ENABLED_PAYMENT_METHODS = @json($effectivePaymentMethods);
        const CSRF_TOKEN = @json(csrf_token());
        const STORE_GUEST_URL = @json(route('admin.events.console.storeGuest', $event->event_id));
        const EFT_CHARGE_START_URL = @json(route('admin.eft.charge.start'));
        const EFT_CHARGE_STATUS_URL_BASE = @json(url('/admin/eft/charge/status'));
        const EFT_CHARGE_CANCEL_URL_BASE = @json(url('/admin/eft/charge/cancel'));
        const EVENT_ID = {{ $event->event_id }};
        const QUICK_AMOUNTS = [51, 101, 201, 501, 1001];
        const REQUIRE_EMAIL = @json((bool) $event->require_donor_email);
        const REQUIRE_MOBILE = @json((bool) $event->require_donor_mobile);
        const CURRENCY_CODE = @json($temple['currency'] ?? '');

        function escapeHtmlPos(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        // Amount fields use type="text" + inputmode="decimal" rather than type="number" —
        // a plain type="number" input silently reports an empty .value (not the text the
        // clerk can see on screen) whenever the browser's own numeric grammar rejects what
        // was typed, which is exactly what produced "Enter a valid amount" even though a
        // number was clearly entered. Sanitizing on input (digits + at most one decimal
        // point) keeps the same numeric-only behaviour without that failure mode.
        function sanitizeDecimalInput(el) {
            let v = el.value.replace(/[^0-9.]/g, '');
            const firstDot = v.indexOf('.');
            if (firstDot !== -1) {
                v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
            }
            el.value = v;
        }
        function bindDecimalSanitizer(el) {
            el.addEventListener('input', function () { sanitizeDecimalInput(el); });
        }

        // Fullscreen — explicit button only.
        document.getElementById('posFullscreenBtn').addEventListener('click', function () {
            if (!document.fullscreenElement) { document.documentElement.requestFullscreen().catch(function () {}); }
            else { document.exitFullscreen(); }
        });

        // Payment method — big buttons instead of a dropdown.
        const methodRow = document.getElementById('posMethodRow');
        let selectedMethod = null;
        const methodIcons = { Cash: 'bi-cash-coin', UPI: 'bi-phone-fill', 'Bank Transfer': 'bi-bank2', Cheque: 'bi-postcard-fill', 'EFT Terminal': 'bi-credit-card-2-front-fill', Stripe: 'bi-credit-card-fill' };
        (ENABLED_PAYMENT_METHODS.length ? ENABLED_PAYMENT_METHODS : ['Cash']).forEach(function (m, idx) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-method-btn' + (idx === 0 ? ' active' : '');
            btn.innerHTML = '<i class="bi ' + (methodIcons[m] || 'bi-wallet2') + '"></i>' + m;
            btn.dataset.method = m;
            btn.addEventListener('click', function () {
                methodRow.querySelectorAll('.pos-method-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                selectedMethod = m;
            });
            methodRow.appendChild(btn);
            if (idx === 0) { selectedMethod = m; }
        });

        // Donation amount — single-option auto-select / multi-option tiers / plain amount,
        // same logic as the full console's Quick Entry, restyled for touch.
        const amountInput = document.getElementById('posAmount');
        const quickAmountsRow = document.getElementById('posQuickAmounts');
        const tiersWrap = document.getElementById('posTiersWrap');
        const simpleAmountWrap = document.getElementById('posSimpleAmountWrap');
        const tiersContainer = document.getElementById('posTiers');
        const tierTotalDisplay = document.getElementById('posTierTotal');
        let selections = [];
        let purposeValue = 'Event Donation';

        if (EVENT_OPTIONS.length) {
            const singleOption = EVENT_OPTIONS.length === 1;
            tiersWrap.style.display = 'block';
            simpleAmountWrap.style.display = 'none';

            let html = '';
            EVENT_OPTIONS.forEach(function (opt, idx) {
                const hasAmount = opt.amount !== null;
                html += '<div class="pos-tier-option' + (singleOption ? ' single-option selected' : '') + '" data-idx="' + idx + '">'
                    + '<label><input type="checkbox" class="pos-tier-cb" data-idx="' + idx + '"' + (singleOption ? ' checked' : '') + '>'
                    + '<span><strong>' + escapeHtmlPos(opt.label) + '</strong><br><span class="text-muted small">'
                    + (hasAmount ? (CURRENCY_CODE + ' ' + opt.amount.toFixed(2) + (opt.allow_quantity ? ' each' : '')) : 'Any amount')
                    + '</span></span></label>'
                    + (opt.allow_quantity ? '<input type="number" min="1" value="1" class="pos-tier-qty" style="' + (singleOption ? '' : 'display:none;') + '">' : '')
                    + (!hasAmount ? '<div class="d-flex flex-column" style="flex:1 1 100%;"><input type="text" inputmode="decimal" placeholder="Amount" class="pos-tier-free">'
                        + '<div class="pos-tier-free-quick-amounts"></div></div>' : '')
                    + '</div>';
            });
            tiersContainer.innerHTML = html;
            tiersContainer.querySelectorAll('.pos-tier-free').forEach(bindDecimalSanitizer);

            // Quick-amount mini chips for each free-amount tier — the only place amount
            // pills appear once an event has any donation options configured, since the
            // top-level pills (posQuickAmounts) only render when an event has none at all.
            tiersContainer.querySelectorAll('.pos-tier-option').forEach(function (row) {
                const quickWrap = row.querySelector('.pos-tier-free-quick-amounts');
                if (!quickWrap) { return; }
                const freeInput = row.querySelector('.pos-tier-free');
                const cb = row.querySelector('.pos-tier-cb');
                QUICK_AMOUNTS.forEach(function (amt) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'pos-tier-quick-btn';
                    b.textContent = '$' + amt.toLocaleString();
                    b.addEventListener('click', function () {
                        cb.checked = true;
                        freeInput.value = amt.toFixed(2);
                        freeInput.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    quickWrap.appendChild(b);
                });
            });

            function recalcTiers() {
                let total = 0;
                const labels = [];
                selections = [];
                tiersContainer.querySelectorAll('.pos-tier-option').forEach(function (row) {
                    const idx = row.dataset.idx;
                    const cb = row.querySelector('.pos-tier-cb');
                    const qtyInput = row.querySelector('.pos-tier-qty');
                    const freeInput = row.querySelector('.pos-tier-free');
                    if (qtyInput) { qtyInput.style.display = cb.checked ? 'inline-block' : 'none'; }
                    row.classList.toggle('selected', cb.checked);
                    if (!cb.checked) { return; }
                    const opt = EVENT_OPTIONS[idx];
                    let label = opt.label;
                    let qty = null;
                    let amount = 0;
                    if (opt.amount !== null) {
                        qty = (qtyInput && opt.allow_quantity) ? (parseInt(qtyInput.value, 10) || 1) : 1;
                        amount = opt.amount * qty;
                        if (opt.allow_quantity && qty > 1) { label += ' (x' + qty + ')'; }
                    } else {
                        amount = freeInput ? (parseFloat(freeInput.value) || 0) : 0;
                    }
                    if (amount > 0) {
                        total += amount;
                        labels.push(label);
                        selections.push({ option_id: opt.id, label: label, quantity: qty, amount: amount });
                    }
                });
                amountInput.value = total > 0 ? total.toFixed(2) : '';
                tierTotalDisplay.textContent = CURRENCY_CODE + ' ' + total.toFixed(2);
                purposeValue = labels.length ? labels.join(', ').substring(0, 250) : 'Event Donation';
            }

            tiersContainer.addEventListener('change', recalcTiers);
            tiersContainer.addEventListener('input', recalcTiers);
            if (singleOption) { recalcTiers(); }

            window.posResetTiers = function () {
                tiersContainer.querySelectorAll('.pos-tier-cb').forEach(function (cb) {
                    if (!cb.closest('.pos-tier-option').classList.contains('single-option')) { cb.checked = false; }
                });
                tiersContainer.querySelectorAll('.pos-tier-free').forEach(function (i) { i.value = ''; });
                tiersContainer.querySelectorAll('.pos-tier-option').forEach(function (row) {
                    if (!row.classList.contains('single-option')) { row.classList.remove('selected'); }
                });
                tierTotalDisplay.textContent = CURRENCY_CODE + ' 0.00';
                selections = [];
                purposeValue = 'Event Donation';
                if (singleOption) { recalcTiers(); }
            };
        } else {
            tiersWrap.style.display = 'none';
            simpleAmountWrap.style.display = 'block';
            QUICK_AMOUNTS.forEach(function (amt) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pos-quick-amount-btn';
                btn.textContent = '$' + amt.toLocaleString();
                btn.addEventListener('click', function () {
                    amountInput.value = amt.toFixed(2);
                    quickAmountsRow.querySelectorAll('.pos-quick-amount-btn').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                });
                quickAmountsRow.appendChild(btn);
            });
            bindDecimalSanitizer(amountInput);
            amountInput.addEventListener('input', function () {
                quickAmountsRow.querySelectorAll('.pos-quick-amount-btn').forEach(function (b) {
                    b.classList.toggle('active', parseFloat(b.textContent.replace(/[^0-9.]/g, '')) === parseFloat(amountInput.value));
                });
            });
            purposeValue = 'Event Donation';
            window.posResetTiers = function () {
                quickAmountsRow.querySelectorAll('.pos-quick-amount-btn').forEach(function (b) { b.classList.remove('active'); });
                purposeValue = 'Event Donation';
            };
        }

        let toastHideTimer = null;
        function showToast(message, isError) {
            if (toastHideTimer) { clearTimeout(toastHideTimer); toastHideTimer = null; }
            const toast = document.getElementById('posToast');
            toast.textContent = message;
            toast.classList.toggle('error', !!isError);
            toast.style.display = 'block';
            toastHideTimer = setTimeout(function () { toast.style.display = 'none'; }, 2200);
        }
        // Center-screen popup mirroring the PIN pad's own display while a card payment is
        // in progress — a corner toast isn't prominent enough for something the operator
        // and donor both need to watch together.
        const eftModalOverlay = document.getElementById('eftModalOverlay');
        const eftModalAmount = document.getElementById('eftModalAmount');
        const eftModalStatusBox = document.getElementById('eftModalStatusBox');
        const eftModalStatusLine1 = document.getElementById('eftModalStatusLine1');
        const eftModalStatusLine2 = document.getElementById('eftModalStatusLine2');

        // Recovery/idempotency: one in-flight EFT attempt at a time is remembered here (not
        // just in a JS variable, so it survives a browser refresh) — a client-generated
        // client_ref the backend uses to resume the SAME Linkly session instead of starting a
        // second one, for both a re-clicked Pay button and a reload mid-payment. Cleared once
        // Linkly gives a final, terminal answer (approved/declined/cancelled/failed); left in
        // place on a timeout/unknown result so a resume is still possible, per "an UNKNOWN
        // transaction must not be treated as declined, and must not risk a double charge".
        const EFT_ATTEMPT_KEY = 'eftAttempt_' + EVENT_ID;
        function newClientRef() {
            return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
        }
        function saveEftAttempt(attempt) {
            try { sessionStorage.setItem(EFT_ATTEMPT_KEY, JSON.stringify(attempt)); } catch (e) { /* private browsing etc. — recovery just won't survive a refresh */ }
        }
        function loadEftAttempt() {
            try { return JSON.parse(sessionStorage.getItem(EFT_ATTEMPT_KEY) || 'null'); } catch (e) { return null; }
        }
        function clearEftAttempt() {
            try { sessionStorage.removeItem(EFT_ATTEMPT_KEY); } catch (e) {}
        }

        let eftModalLastSignature = null;
        function showEftModal(amount) {
            eftModalAmount.textContent = CURRENCY_CODE + ' ' + amount.toFixed(2);
            eftModalLastSignature = null;
            setEftModalStatus(['Starting…'], 'pending');
            eftModalOverlay.classList.add('active');
        }
        // Only touches the DOM when the status/lines actually differ from what's already
        // shown — polling every ~1.2s would otherwise re-write (and visually flicker) the
        // same unchanged text on every single tick, most of which return nothing new.
        function setEftModalStatus(lines, state) {
            lines = lines && lines.length ? lines : ['Please wait…'];
            const signature = state + '|' + lines.join('|');
            if (signature === eftModalLastSignature) { return; }
            eftModalLastSignature = signature;

            eftModalStatusBox.classList.remove('pending', 'success', 'error');
            eftModalStatusBox.classList.add(state);
            eftModalStatusLine1.textContent = lines[0] || '';
            eftModalStatusLine2.textContent = lines[1] || '';
        }
        function hideEftModal() {
            eftModalOverlay.classList.remove('active');
            eftCurrentSessionId = null;
        }

        // "Orders this session" — sessionStorage only, so it survives a reload of this same
        // browser tab (a kiosk left open all day) but never persists beyond it and never
        // touches the server — the console's own donations table is the real record.
        const SESSION_KEY = 'posOrders_' + EVENT_ID;
        function loadSessionOrders() {
            try { return JSON.parse(sessionStorage.getItem(SESSION_KEY) || '[]'); } catch (e) { return []; }
        }
        function addSessionOrder(order) {
            const orders = loadSessionOrders();
            orders.unshift(order);
            try { sessionStorage.setItem(SESSION_KEY, JSON.stringify(orders)); } catch (e) {}
            renderSessionOrders();
        }
        function renderSessionOrders() {
            const orders = loadSessionOrders();
            const list = document.getElementById('posOrdersList');
            document.getElementById('posOrdersCount').textContent = orders.length;
            document.getElementById('posOrdersTotal').textContent = CURRENCY_CODE + ' ' + orders.reduce(function (s, o) { return s + o.amount; }, 0).toFixed(2);
            list.innerHTML = orders.length
                ? orders.map(function (o) {
                    return '<div class="pos-order-item"><span class="pos-order-name">' + escapeHtmlPos(o.name) + '</span><span class="pos-order-amount">' + CURRENCY_CODE + ' ' + o.amount.toFixed(2) + '</span><span class="pos-order-time">' + o.time + '</span></div>';
                }).join('')
                : '<div class="pos-orders-empty">No donations recorded yet this session.</div>';
        }
        renderSessionOrders();

        const ordersToggle = document.getElementById('posOrdersToggle');
        const ordersList = document.getElementById('posOrdersList');
        const ordersChevron = document.getElementById('posOrdersChevron');
        ordersToggle.addEventListener('click', function () {
            ordersList.classList.toggle('expanded');
            ordersChevron.classList.toggle('bi-chevron-up');
            ordersChevron.classList.toggle('bi-chevron-down');
        });

        function resetPosForm() {
            document.getElementById('posGuestName').value = '';
            document.getElementById('posGuestMobile').value = '';
            document.getElementById('posGuestEmail').value = '';
            document.getElementById('posDetails').value = '';
            amountInput.value = '';
            if (window.posResetTiers) { window.posResetTiers(); }
        }

        function submitGuestDonation(btn, amount, name, emailValue, mobileValue, transactionId, linklySessionId) {
            const today = new Date().toISOString().slice(0, 10);
            const body = new URLSearchParams();
            body.set('event_id', EVENT_ID);
            body.set('amount', amount.toFixed(2));
            body.set('selections_json', JSON.stringify(selections));
            // Cash, UPI and an EFT terminal all confirm the money on the spot, so those record
            // as Paid immediately. A Bank Transfer claim can't be verified at the counter — it
            // sits Pending until an admin checks the account and approves it, same as the
            // public donation form.
            body.set('payment_status', selectedMethod === 'Bank Transfer' ? 'Pending' : 'Paid');
            body.set('transaction_id', transactionId || '');
            // Links this donation back to its Linkly accreditation ledger row — set only for
            // an EFT Terminal payment (see DonationController::linkLedgerToDonation()).
            if (linklySessionId) { body.set('linkly_session_id', linklySessionId); }
            body.set('donor_name', name);
            body.set('donation_date', today);
            body.set('email', emailValue);
            body.set('mobile', mobileValue);
            body.set('payment_method', selectedMethod === 'Bank Transfer' ? 'Bank' : selectedMethod);
            body.set('purpose', purposeValue);
            body.set('purpose_details', document.getElementById('posDetails').value);

            fetch(STORE_GUEST_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    btn.disabled = false;
                    if (result.status >= 200 && result.status < 300 && result.data.success) {
                        const pendingNote = selectedMethod === 'Bank Transfer' ? ' (Pending)' : '';
                        showToast('Saved — ' + CURRENCY_CODE + ' ' + amount.toFixed(2) + pendingNote);
                        addSessionOrder({ name: name, amount: amount, time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) });
                        resetPosForm();
                        document.getElementById('posGuestName').focus();
                    } else {
                        showToast(result.data.message || 'Failed to save.', true);
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    showToast('Network error — please try again.', true);
                });
        }

        document.getElementById('posSaveBtn').addEventListener('click', function () {
            const amount = parseFloat((amountInput.value || '').trim());
            if (!amount || amount <= 0) { showToast('Enter a valid amount.', true); return; }

            const name = document.getElementById('posGuestName').value.trim();
            if (!name) { showToast('Enter the donor name.', true); return; }

            const emailValue = document.getElementById('posGuestEmail').value.trim();
            if (REQUIRE_EMAIL && !emailValue) { showToast('Enter the donor email.', true); return; }

            const mobileValue = document.getElementById('posGuestMobile').value.trim();
            if (REQUIRE_MOBILE && !mobileValue) { showToast('Enter the donor mobile number.', true); return; }

            const btn = this;
            btn.disabled = true;

            // EFT Terminal charges the physical/virtual PIN pad and waits for the donor to
            // tap/insert their card before recording anything — a declined or failed
            // transaction never reaches storeGuestDonation() at all. Runs as start-then-poll
            // (not one blocking call) so the terminal's live prompts ("ENTER PIN", etc.,
            // fed by Linkly's webhook postbacks) can actually reach the screen.
            if (selectedMethod === 'EFT Terminal') {
                startOrResumeEftPurchase(btn, { clientRef: newClientRef(), amount: amount, name: name, email: emailValue, mobile: mobileValue });
                return;
            }

            submitGuestDonation(btn, amount, name, emailValue, mobileValue, '', '');
        });

        // Shared by a fresh Pay click and the "Resume" recovery banner — an existing,
        // not-yet-finished attempt (passed in by resumeEftAttempt()) always wins over
        // starting a brand new one, so a re-clicked Pay button or a page reload mid-payment
        // never starts a second Linkly session for the same checkout.
        function startOrResumeEftPurchase(btn, freshAttempt) {
            const attempt = loadEftAttempt() || freshAttempt;
            saveEftAttempt(attempt);

            eftPollCancelled = false;
            eftCurrentSessionId = null;
            showEftModal(attempt.amount);
            const startBody = new URLSearchParams();
            startBody.set('event_id', EVENT_ID);
            startBody.set('amount', attempt.amount.toFixed(2));
            startBody.set('client_ref', attempt.clientRef);

            fetch(EFT_CHARGE_START_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: startBody.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    if (!(result.status >= 200 && result.status < 300 && result.data.success)) {
                        btn.disabled = false;
                        hideEftModal();
                        showToast(result.data.message || 'Could not start the terminal transaction.', true);
                        return;
                    }
                    eftCurrentSessionId = result.data.session_id;
                    pollEftTransaction(result.data.session_id, btn, attempt.amount, attempt.name, attempt.email, attempt.mobile, Date.now());
                })
                .catch(function () {
                    btn.disabled = false;
                    hideEftModal();
                    showToast('Could not reach the EFT terminal — please try again.', true);
                });
        }

        // Set true by the modal's Cancel button — checked at the top of every poll tick so
        // an abandoned wait actually stops instead of continuing in the background.
        let eftPollCancelled = false;
        // The in-flight transaction's session id, so the Cancel button can tell Linkly to
        // actually cancel it (via cancelEftCharge -> LinklyEftService::cancel(), a sendkey
        // "0" to the terminal) rather than only dismissing the modal locally.
        let eftCurrentSessionId = null;
        document.getElementById('eftModalCancelBtn').addEventListener('click', function () {
            eftPollCancelled = true;
            const sessionId = eftCurrentSessionId;
            const btn = document.getElementById('posSaveBtn');
            hideEftModal();
            btn.disabled = false;
            // An explicit Cancel is a deliberate end to this attempt — the next Pay click
            // should start a brand new one rather than resume it (unlike a timeout/unknown
            // result, where resuming is exactly what's wanted).
            clearEftAttempt();

            if (!sessionId) {
                showToast('Payment cancelled.', true);
                return;
            }

            showToast('Cancelling…');
            fetch(EFT_CHARGE_CANCEL_URL_BASE + '/' + encodeURIComponent(sessionId) + '?event_id=' + encodeURIComponent(EVENT_ID), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    showToast(data.success ? 'Cancel sent to the terminal.' : (data.message || 'The terminal may have already moved past the cancel step.'), !data.success);
                })
                .catch(function () {
                    showToast('Could not reach the terminal to cancel — if it is still waiting, cancel it there too.', true);
                });
        });

        // Polls every ~1.2s for up to ~3 minutes (matching Linkly's own pairing/transaction
        // window guidance) — each response carries the PIN pad's current display text (if
        // any arrived via webhook since the last poll) and, once the terminal finishes,
        // the final approved/declined result.
        function pollEftTransaction(sessionId, btn, amount, name, emailValue, mobileValue, startedAt) {
            if (eftPollCancelled) { return; }

            // This local ~3-minute guard just stops the browser polling forever — it does
            // NOT clear the saved attempt, and startOrResumeEftPurchase() always resumes an
            // existing attempt rather than starting a new one, so clicking Pay again here is
            // safe (it re-attaches to this same Linkly session instead of double-charging).
            // The server independently reaches the same "unknown" conclusion around 200s in
            // pollEftCharge() if this client-side guard is somehow bypassed.
            if (Date.now() - startedAt > 180000) {
                btn.disabled = false;
                setEftModalStatus(['No response from the terminal yet', 'Press Pay to keep checking — this will not charge twice'], 'error');
                setTimeout(hideEftModal, 2200);
                showToast('No final result yet — press Pay to keep checking (safe, will not double-charge).', true);
                return;
            }

            const statusUrl = EFT_CHARGE_STATUS_URL_BASE + '/' + encodeURIComponent(sessionId) + '?event_id=' + encodeURIComponent(EVENT_ID);
            fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (eftPollCancelled) { return; }

                    if (data.display && data.display.length) {
                        setEftModalStatus(data.display, 'pending');
                    }
                    if (!data.done) {
                        setTimeout(function () {
                            pollEftTransaction(sessionId, btn, amount, name, emailValue, mobileValue, startedAt);
                        }, 1200);
                        return;
                    }
                    if (data.success) {
                        // A genuine final answer — this checkout attempt is over either way,
                        // so the next Pay click must start a fresh one, not resume this.
                        clearEftAttempt();
                        setEftModalStatus(['PAYMENT APPROVED', data.auth_code ? 'Auth ' + data.auth_code : 'Saving donation…'], 'success');
                        setTimeout(hideEftModal, 1200);
                        submitGuestDonation(btn, amount, name, emailValue, mobileValue, data.rrn || data.auth_code || '', sessionId);
                    } else if (data.payment_status === 'unknown') {
                        // Per Linkly's Core Payments recovery requirement: never treat this as
                        // a decline, and never let it look "safe to just try again" without
                        // the resume mechanism — so the attempt stays saved for a real resume.
                        setEftModalStatus(['RESULT UNKNOWN', 'Check the terminal/bank statement, then press Pay to resume checking'], 'error');
                        setTimeout(hideEftModal, 2600);
                        btn.disabled = false;
                        showToast(data.message || 'No final result was received — check before retrying.', true);
                    } else {
                        clearEftAttempt();
                        setEftModalStatus(['PAYMENT DECLINED', data.message || ''], 'error');
                        setTimeout(hideEftModal, 1800);
                        btn.disabled = false;
                        showToast(data.message || 'Card declined.', true);
                    }
                })
                .catch(function () {
                    // A single failed poll isn't fatal — try again on the next tick rather
                    // than abandoning a transaction that may still complete on the terminal.
                    setTimeout(function () {
                        pollEftTransaction(sessionId, btn, amount, name, emailValue, mobileValue, startedAt);
                    }, 1200);
                });
        }

        // Resume-after-refresh: if a previous attempt is still sitting in sessionStorage when
        // this page loads (the operator refreshed or the browser crashed mid-payment), never
        // silently start anything — just surface a banner so the operator can explicitly
        // resume checking it. See startOrResumeEftPurchase()/EFT_ATTEMPT_KEY above.
        document.addEventListener('DOMContentLoaded', function () {
            const attempt = loadEftAttempt();
            const banner = document.getElementById('eftResumeBanner');
            if (attempt && banner) {
                document.getElementById('eftResumeBannerText').textContent =
                    'A previous EFT Terminal payment (' + CURRENCY_CODE + ' ' + Number(attempt.amount).toFixed(2) + ' for ' + attempt.name + ') did not finish. It may already be approved on the terminal.';
                banner.style.display = 'flex';
                document.getElementById('eftResumeBannerBtn').addEventListener('click', function () {
                    banner.style.display = 'none';
                    const btn = document.getElementById('posSaveBtn');
                    btn.disabled = true;
                    startOrResumeEftPurchase(btn, attempt);
                });
                document.getElementById('eftResumeBannerDismissBtn').addEventListener('click', function () {
                    banner.style.display = 'none';
                    clearEftAttempt();
                });
            }
        });
    </script>
</body>
</html>
