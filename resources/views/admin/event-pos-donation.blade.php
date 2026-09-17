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

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        const EVENT_OPTIONS = @json($eventOptionsForJs);
        const ENABLED_PAYMENT_METHODS = @json($effectivePaymentMethods);
        const CSRF_TOKEN = @json(csrf_token());
        const STORE_GUEST_URL = @json(route('admin.events.console.storeGuest', $event->event_id));
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

        function showToast(message, isError) {
            const toast = document.getElementById('posToast');
            toast.textContent = message;
            toast.classList.toggle('error', !!isError);
            toast.style.display = 'block';
            setTimeout(function () { toast.style.display = 'none'; }, 2200);
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
            body.set('transaction_id', '');
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
        });
    </script>
</body>
</html>
