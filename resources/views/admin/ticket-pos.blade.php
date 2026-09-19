<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Ticket Kiosk</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/ibm-plex-mono/ibm-plex-mono.css') }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A; --maroon-dark: #4A0A12; --gold: #C89B3C; --gold-hover: #A67C2B;
            --cream: #F9F3E7; --white: #FFFFFF; --border: #F0E5D6; --text-primary: #1F2A37;
            --text-secondary: #6B7280; --success: #10B981; --error: #EF4444;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { overflow-x: hidden; height: 100%; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; background: var(--cream); color: var(--text-primary); display: flex; flex-direction: column; }
        button, input, select, textarea { font-family: inherit; }

        .pos-topbar { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 12px 20px; display: flex; align-items: center; gap: 14px; flex-shrink: 0; box-shadow: 0 4px 18px rgba(74,10,18,0.25); z-index: 20; }
        .pos-topbar-title { flex: 1; min-width: 0; }
        .pos-topbar-title h1 { font-size: clamp(1.05rem, 2.6vw, 1.35rem); font-weight: 800; color: var(--gold); margin: 0; }
        .pos-topbar-title .pos-subtitle { font-size: 0.7rem; color: rgba(255,255,255,0.65); text-transform: uppercase; letter-spacing: 0.06em; }
        .pos-topbar-btn { background: rgba(255,255,255,0.12); border: none; color: white; width: 42px; height: 42px; border-radius: 12px; font-size: 1.05rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .pos-topbar-btn:hover { background: rgba(255,255,255,0.22); }

        .pos-main { flex: 1; min-height: 0; overflow-y: auto; padding: 18px 16px 8px; }
        .pos-card { max-width: 760px; margin: 0 auto; background: var(--white); border-radius: 18px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(31,42,55,0.05); padding: 20px clamp(16px, 3vw, 30px); }
        .pos-field-label { display: block; font-weight: 700; font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; }
        .pos-input { width: 100%; padding: 14px 16px; border: 2px solid var(--border); border-radius: 14px; font-size: 1.05rem; font-weight: 600; color: var(--text-primary); background: var(--white); min-height: 52px; }
        .pos-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 4px rgba(200,155,60,0.15); }
        .pos-row { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 14px; }
        .pos-row.two-col { grid-template-columns: 1fr; }
        @media (min-width: 560px) { .pos-row.two-col { grid-template-columns: 1fr 1fr 1fr; } }
        .pos-section-title { font-weight: 800; font-size: 0.95rem; color: var(--text-primary); margin: 20px 0 10px; }
        .pos-section-title:first-child { margin-top: 0; }

        .ticket-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border: 2px solid var(--border); border-radius: 14px; margin-bottom: 10px; background: var(--white); flex-wrap: wrap; }
        .ticket-row.in-cart { border-color: var(--gold); background: #FDF6EA; }
        .ticket-row-info { flex: 1; min-width: 160px; }
        .ticket-row-name { font-weight: 700; font-size: 1.02rem; }
        .ticket-row-price { color: var(--gold-hover); font-weight: 700; font-family: 'IBM Plex Mono', 'Inter', monospace; }
        .ticket-qty-controls { display: flex; align-items: center; gap: 10px; }
        .ticket-qty-btn { width: 44px; height: 44px; border-radius: 12px; border: 2px solid var(--border); background: var(--white); font-size: 1.3rem; font-weight: 800; color: var(--gold-hover); }
        .ticket-qty-btn:active { background: var(--cream); }
        .ticket-qty-value { min-width: 32px; text-align: center; font-weight: 800; font-size: 1.1rem; font-family: 'IBM Plex Mono', 'Inter', monospace; }

        .cart-total-row { display: flex; justify-content: space-between; font-weight: 800; font-size: 1.3rem; color: var(--text-primary); padding: 14px 4px; border-top: 2px solid var(--border); margin-top: 8px; }
        .cart-total-row span:last-child { font-family: 'IBM Plex Mono', 'Inter', monospace; }

        .pos-method-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; }
        .pos-method-btn { flex: 1 1 calc(33% - 10px); min-width: 110px; padding: 16px 10px; min-height: 60px; border-radius: 14px; border: 2px solid var(--border); background: var(--white); font-weight: 700; font-size: 0.95rem; color: var(--text-secondary); }
        .pos-method-btn.active { border-color: var(--gold); background: var(--gold); color: white; box-shadow: 0 6px 16px rgba(200,155,60,0.3); }
        .pos-method-btn i { display: block; font-size: 1.3rem; margin-bottom: 4px; }

        .pos-save-btn { width: 100%; padding: 20px; border-radius: 16px; border: none; margin-top: 18px; background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; font-size: 1.3rem; box-shadow: 0 10px 26px rgba(200,155,60,0.35); min-height: 68px; }
        .pos-save-btn:disabled { opacity: 0.55; }
        .pos-save-btn:active { transform: scale(0.98); }

        .pos-toast { position: fixed; bottom: 24px; right: 24px; background: var(--success); color: white; padding: 18px 26px; border-radius: 14px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 14px 34px rgba(0,0,0,0.2); z-index: 999; display: none; }
        .pos-toast.error { background: var(--error); }

        .eft-modal-overlay { position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .eft-modal-overlay.active { display: flex; }
        .eft-modal { background: var(--white); border-radius: 22px; width: 100%; max-width: 380px; box-shadow: 0 24px 60px rgba(0,0,0,0.35); overflow: hidden; text-align: center; }
        .eft-modal-header { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 18px 20px; font-weight: 800; letter-spacing: 0.06em; font-size: 0.95rem; text-transform: uppercase; }
        .eft-modal-body { padding: 30px 26px 26px; }
        .eft-modal-amount { font-family: 'IBM Plex Mono', 'Inter', monospace; font-variant-numeric: tabular-nums; font-size: 2.4rem; font-weight: 700; color: var(--text-primary); margin-bottom: 18px; }
        .eft-modal-status-box { background: var(--cream); border: 2px solid var(--border); border-radius: 14px; padding: 18px 16px; min-height: 90px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; margin-bottom: 22px; }
        .eft-modal-spinner { width: 26px; height: 26px; border-radius: 50%; border: 3px solid rgba(200,155,60,0.25); border-top-color: var(--gold); animation: eftSpin 0.8s linear infinite; margin-bottom: 4px; display: none; }
        .eft-modal-status-box.pending .eft-modal-spinner { display: block; }
        .eft-modal-status-icon { font-size: 1.6rem; margin-bottom: 2px; display: none; }
        .eft-modal-status-box.success .eft-modal-status-icon.icon-success { display: block; color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-icon.icon-error { display: block; color: var(--error); }
        .eft-modal-status-line { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); letter-spacing: 0.02em; }
        .eft-modal-status-box.success .eft-modal-status-line { color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-line { color: var(--error); }
        @keyframes eftSpin { to { transform: rotate(360deg); } }
        .eft-modal-cancel-btn { width: 100%; padding: 14px; border-radius: 12px; border: 2px solid var(--border); background: var(--white); color: var(--text-secondary); font-weight: 700; font-size: 0.95rem; }
        .eft-modal-cancel-btn:active { background: var(--cream); }
        .eft-modal-keys { display: none; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .eft-modal-keys.active { display: flex; }
        .eft-modal-key-btn { flex: 1 1 auto; min-width: 90px; padding: 13px 10px; border-radius: 12px; border: 2px solid transparent; font-weight: 700; font-size: 0.95rem; color: #fff; }
        .eft-modal-key-btn:active { filter: brightness(0.92); }
        .eft-modal-key-btn.key-ok, .eft-modal-key-btn.key-yes, .eft-modal-key-btn.key-authorise { background: var(--success); }
        .eft-modal-key-btn.key-no { background: var(--error); }
    </style>
</head>
<body>
    <header class="pos-topbar">
        <div class="pos-topbar-title">
            <h1>Ticket Kiosk</h1>
            <div class="pos-subtitle">Sell &amp; Print Tickets</div>
        </div>
        <button type="button" class="pos-topbar-btn" id="posFullscreenBtn" title="Toggle fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
        <a href="{{ route('admin.tickets.index') }}" class="pos-topbar-btn" title="Manage Tickets"><i class="bi bi-grid-1x2-fill"></i></a>
        <a href="{{ route('logout') }}" class="pos-topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </header>

    <div class="pos-main">
        <div class="pos-card">
            <div class="pos-section-title">Tickets</div>
            <div id="ticketList">
                @forelse($tickets as $ticket)
                <div class="ticket-row" data-id="{{ $ticket->id }}" data-name="{{ $ticket->name }}" data-price="{{ $ticket->price }}">
                    <div class="ticket-row-info">
                        <div class="ticket-row-name">{{ $ticket->name }}</div>
                        <div class="ticket-row-price">{{ $temple['currency'] ?? '' }} {{ number_format($ticket->price, 2) }}</div>
                    </div>
                    <div class="ticket-qty-controls">
                        <button type="button" class="ticket-qty-btn ticket-qty-minus">−</button>
                        <span class="ticket-qty-value">0</span>
                        <button type="button" class="ticket-qty-btn ticket-qty-plus">+</button>
                    </div>
                </div>
                @empty
                <p class="text-muted text-center py-4">No active ticket types — add one in Manage Tickets.</p>
                @endforelse
            </div>
            <div class="cart-total-row"><span>Total</span><span id="cartTotal">{{ $temple['currency'] ?? '' }} 0.00</span></div>

            <div class="pos-section-title">Customer (optional)</div>
            <div class="pos-row two-col">
                <div>
                    <label class="pos-field-label">Name</label>
                    <input type="text" class="pos-input" id="ticketCustomerName" placeholder="Optional" autocomplete="off">
                </div>
                <div>
                    <label class="pos-field-label">Mobile</label>
                    <input type="text" class="pos-input" id="ticketCustomerMobile" placeholder="Optional" autocomplete="off">
                </div>
                <div>
                    <label class="pos-field-label">Email</label>
                    <input type="email" class="pos-input" id="ticketCustomerEmail" placeholder="Optional" autocomplete="off">
                </div>
            </div>

            <div class="pos-section-title">Payment Method</div>
            <div class="pos-method-row" id="posMethodRow"></div>

            <button type="button" class="pos-save-btn" id="posSaveBtn"><i class="bi bi-printer-fill me-2"></i>Complete Sale &amp; Print</button>
        </div>
    </div>

    <div class="pos-toast" id="posToast"></div>

    <div id="eftResumeBanner" style="display:none; position:fixed; top:0; left:0; right:0; z-index:2000; background:#7a1f1f; color:#fff; padding:12px 18px; align-items:center; gap:14px; flex-wrap:wrap; justify-content:center;">
        <span id="eftResumeBannerText"></span>
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
                <div class="eft-modal-keys" id="eftModalKeys">
                    <button type="button" class="eft-modal-key-btn key-yes" id="eftModalKeyYes">Yes</button>
                    <button type="button" class="eft-modal-key-btn key-ok" id="eftModalKeyOk">OK</button>
                    <button type="button" class="eft-modal-key-btn key-no" id="eftModalKeyNo">No</button>
                    <button type="button" class="eft-modal-key-btn key-authorise" id="eftModalKeyAuthorise">Authorise</button>
                </div>
                <button type="button" class="eft-modal-cancel-btn" id="eftModalCancelBtn">Cancel Payment</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @php
        $pendingEftRecoveryForJs = $pendingEftRecovery ? [
            'sessionId' => $pendingEftRecovery->linkly_session_id,
            'clientRef' => $pendingEftRecovery->client_ref,
            'amount' => (float) $pendingEftRecovery->amount,
            'name' => $pendingEftRecovery->meta['customer_name'] ?? 'Customer',
            'email' => $pendingEftRecovery->meta['email'] ?? '',
            'mobile' => $pendingEftRecovery->meta['mobile'] ?? '',
        ] : null;
    @endphp
    <script>
        const CSRF_TOKEN = @json(csrf_token());
        const STORE_ORDER_URL = @json(route('admin.tickets.storeOrder'));
        const EFT_CHARGE_START_URL = @json(route('admin.eft.charge.start'));
        const EFT_CHARGE_STATUS_URL_BASE = @json(url('/admin/eft/charge/status'));
        const EFT_CHARGE_CANCEL_URL_BASE = @json(url('/admin/eft/charge/cancel'));
        const EFT_CHARGE_SENDKEY_URL_BASE = @json(url('/admin/eft/charge/sendkey'));
        const CURRENCY_CODE = @json($temple['currency'] ?? '');
        const PENDING_EFT_RECOVERY = @json($pendingEftRecoveryForJs);

        document.getElementById('posFullscreenBtn').addEventListener('click', function () {
            if (!document.fullscreenElement) { document.documentElement.requestFullscreen().catch(function () {}); }
            else { document.exitFullscreen(); }
        });

        // ---------- Cart ----------
        let cart = {}; // ticket_id -> {id, name, price, quantity}
        function updateCartRow(row) {
            const id = row.dataset.id;
            const qty = (cart[id] && cart[id].quantity) || 0;
            row.querySelector('.ticket-qty-value').textContent = qty;
            row.classList.toggle('in-cart', qty > 0);
        }
        function recalcCartTotal() {
            let total = 0;
            Object.values(cart).forEach(function (line) { total += line.price * line.quantity; });
            document.getElementById('cartTotal').textContent = CURRENCY_CODE + ' ' + total.toFixed(2);
            return total;
        }
        document.querySelectorAll('.ticket-row').forEach(function (row) {
            const id = row.dataset.id;
            const name = row.dataset.name;
            const price = parseFloat(row.dataset.price);
            row.querySelector('.ticket-qty-plus').addEventListener('click', function () {
                cart[id] = cart[id] || { id: id, name: name, price: price, quantity: 0 };
                cart[id].quantity++;
                updateCartRow(row);
                recalcCartTotal();
            });
            row.querySelector('.ticket-qty-minus').addEventListener('click', function () {
                if (!cart[id] || cart[id].quantity <= 0) { return; }
                cart[id].quantity--;
                if (cart[id].quantity === 0) { delete cart[id]; }
                updateCartRow(row);
                recalcCartTotal();
            });
        });
        function resetCart() {
            cart = {};
            document.querySelectorAll('.ticket-row').forEach(updateCartRow);
            recalcCartTotal();
            document.getElementById('ticketCustomerName').value = '';
            document.getElementById('ticketCustomerMobile').value = '';
            document.getElementById('ticketCustomerEmail').value = '';
        }
        function cartAsArray() {
            return Object.values(cart).map(function (l) { return { ticket_id: l.id, name: l.name, price: l.price, quantity: l.quantity }; });
        }

        // ---------- Payment method ----------
        const methodRow = document.getElementById('posMethodRow');
        let selectedMethod = null;
        const methodIcons = { Cash: 'bi-cash-coin', UPI: 'bi-phone-fill', 'Bank Transfer': 'bi-bank2', 'EFT Terminal': 'bi-credit-card-2-front-fill' };
        const PAYMENT_METHODS = @json($paymentMethods);
        (PAYMENT_METHODS.length ? PAYMENT_METHODS : ['Cash']).forEach(function (m, idx) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-method-btn' + (idx === 0 ? ' active' : '');
            btn.innerHTML = '<i class="bi ' + (methodIcons[m] || 'bi-wallet2') + '"></i>' + m;
            btn.addEventListener('click', function () {
                methodRow.querySelectorAll('.pos-method-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                selectedMethod = m;
            });
            methodRow.appendChild(btn);
            if (idx === 0) { selectedMethod = m; }
        });

        let toastHideTimer = null;
        function showToast(message, isError) {
            if (toastHideTimer) { clearTimeout(toastHideTimer); toastHideTimer = null; }
            const toast = document.getElementById('posToast');
            toast.textContent = message;
            toast.classList.toggle('error', !!isError);
            toast.style.display = 'block';
            toastHideTimer = setTimeout(function () { toast.style.display = 'none'; }, 2200);
        }

        const eftModalOverlay = document.getElementById('eftModalOverlay');
        const eftModalAmount = document.getElementById('eftModalAmount');
        const eftModalStatusBox = document.getElementById('eftModalStatusBox');
        const eftModalStatusLine1 = document.getElementById('eftModalStatusLine1');
        const eftModalStatusLine2 = document.getElementById('eftModalStatusLine2');
        const eftModalKeys = document.getElementById('eftModalKeys');
        const eftKeyButtons = {
            ok: document.getElementById('eftModalKeyOk'),
            yes: document.getElementById('eftModalKeyYes'),
            no: document.getElementById('eftModalKeyNo'),
            authorise: document.getElementById('eftModalKeyAuthorise'),
        };

        const EFT_ATTEMPT_KEY = 'ticketEftAttempt';
        function newClientRef() {
            return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
        }
        function saveEftAttempt(attempt) {
            try { sessionStorage.setItem(EFT_ATTEMPT_KEY, JSON.stringify(attempt)); } catch (e) {}
        }
        function loadEftAttempt() {
            try { return JSON.parse(sessionStorage.getItem(EFT_ATTEMPT_KEY) || 'null'); } catch (e) { return null; }
        }
        function clearEftAttempt() {
            try { sessionStorage.removeItem(EFT_ATTEMPT_KEY); } catch (e) {}
        }

        let eftModalLastSignature = null;
        let eftModalKeysSignature = null;
        function showEftModal(amount) {
            eftModalAmount.textContent = CURRENCY_CODE + ' ' + amount.toFixed(2);
            eftModalLastSignature = null;
            setEftModalStatus(['Starting…'], 'pending');
            updateEftModalKeys(null, null);
            eftModalOverlay.classList.add('active');
        }
        function updateEftModalKeys(controls, sessionId) {
            const signature = controls ? JSON.stringify(controls) : 'none';
            if (signature === eftModalKeysSignature) { return; }
            eftModalKeysSignature = signature;
            let anyVisible = false;
            Object.keys(eftKeyButtons).forEach(function (key) {
                const visible = !!(controls && controls[key]);
                eftKeyButtons[key].hidden = !visible;
                eftKeyButtons[key].onclick = visible ? function () { sendEftModalKey(key, sessionId); } : null;
                if (visible) { anyVisible = true; }
            });
            eftModalKeys.classList.toggle('active', anyVisible);
        }
        function sendEftModalKey(key, sessionId) {
            Object.values(eftKeyButtons).forEach(function (b) { b.disabled = true; });
            fetch(EFT_CHARGE_SENDKEY_URL_BASE + '/' + encodeURIComponent(sessionId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'key=' + encodeURIComponent(key),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    Object.values(eftKeyButtons).forEach(function (b) { b.disabled = false; });
                    if (!data.success) { showToast(data.message || 'The terminal did not accept that.', true); }
                })
                .catch(function () {
                    Object.values(eftKeyButtons).forEach(function (b) { b.disabled = false; });
                    showToast('Could not reach the terminal — please try again.', true);
                });
        }
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
            updateEftModalKeys(null, null);
        }

        function openPrintView(orderId) {
            if (orderId) { window.open('/admin/tickets/print/' + orderId, '_blank'); }
        }

        document.getElementById('posSaveBtn').addEventListener('click', function () {
            const cartLines = cartAsArray();
            const total = recalcCartTotal();
            if (!cartLines.length || total <= 0) { showToast('Add at least one ticket to the order.', true); return; }

            const name = document.getElementById('ticketCustomerName').value.trim();
            const email = document.getElementById('ticketCustomerEmail').value.trim();
            const mobile = document.getElementById('ticketCustomerMobile').value.trim();
            const btn = this;
            btn.disabled = true;

            if (selectedMethod === 'EFT Terminal') {
                startOrResumeEftPurchase(btn, {
                    clientRef: newClientRef(),
                    amount: total,
                    name: name || 'Customer',
                    email: email,
                    mobile: mobile,
                    cart: cartLines,
                });
                return;
            }

            const body = new URLSearchParams();
            body.set('customer_name', name);
            body.set('email', email);
            body.set('mobile', mobile);
            body.set('cart_json', JSON.stringify(cartLines));
            body.set('payment_method', selectedMethod);

            fetch(STORE_ORDER_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    btn.disabled = false;
                    if (result.status >= 200 && result.status < 300 && result.data.success) {
                        showToast('Sale recorded — printing…');
                        openPrintView(result.data.order_id);
                        resetCart();
                    } else {
                        showToast(result.data.message || 'Failed to save.', true);
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    showToast('Network error — please try again.', true);
                });
        });

        function startOrResumeEftPurchase(btn, freshAttempt) {
            const attempt = loadEftAttempt() || freshAttempt;
            saveEftAttempt(attempt);

            eftPollCancelled = false;
            eftCurrentSessionId = null;
            eftConsecutiveTransientErrors = 0;
            showEftModal(attempt.amount);
            const startBody = new URLSearchParams();
            startBody.set('record_type', 'ticket_order');
            startBody.set('amount', attempt.amount.toFixed(2));
            startBody.set('client_ref', attempt.clientRef);
            startBody.set('donor_name', attempt.name || '');
            startBody.set('email', attempt.email || '');
            startBody.set('mobile', attempt.mobile || '');
            startBody.set('cart_json', JSON.stringify(attempt.cart || []));

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
                    pollEftTransaction(result.data.session_id, btn, attempt.amount, Date.now());
                })
                .catch(function () {
                    btn.disabled = false;
                    hideEftModal();
                    showToast('Could not reach the EFT terminal — please try again.', true);
                });
        }

        let eftPollCancelled = false;
        let eftCurrentSessionId = null;
        document.getElementById('eftModalCancelBtn').addEventListener('click', function () {
            const sessionId = eftCurrentSessionId;
            if (!sessionId) {
                eftPollCancelled = true;
                clearEftAttempt();
                hideEftModal();
                document.getElementById('posSaveBtn').disabled = false;
                showToast('Sale cancelled.', true);
                return;
            }

            const cancelBtn = this;
            cancelBtn.disabled = true;
            setEftModalStatus(['Cancelling…'], 'pending');

            fetch(EFT_CHARGE_CANCEL_URL_BASE + '/' + encodeURIComponent(sessionId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    cancelBtn.disabled = false;
                    if (data.success) {
                        eftPollCancelled = true;
                        clearEftAttempt();
                        hideEftModal();
                        document.getElementById('posSaveBtn').disabled = false;
                        showToast('Cancelled on the terminal.');
                    } else {
                        showToast(data.message || 'Could not cancel — the transaction may still complete. Please wait for the result.', true);
                    }
                })
                .catch(function () {
                    cancelBtn.disabled = false;
                    showToast('Could not reach the terminal to cancel — the transaction may still complete. Please wait for the result.', true);
                });
        });

        let eftConsecutiveTransientErrors = 0;
        function eftNextPollDelay(wasTransientError) {
            if (!wasTransientError) { eftConsecutiveTransientErrors = 0; return 1200; }
            eftConsecutiveTransientErrors++;
            return Math.min(1200 * Math.pow(2, eftConsecutiveTransientErrors), 30000);
        }

        function pollEftTransaction(sessionId, btn, amount, startedAt) {
            if (eftPollCancelled) { return; }

            if (Date.now() - startedAt > 180000) {
                btn.disabled = false;
                setEftModalStatus(['No response from the terminal yet', 'Press the button to keep checking — this will not charge twice'], 'error');
                setTimeout(hideEftModal, 2200);
                showToast('No final result yet — press the button to keep checking (safe, will not double-charge).', true);
                return;
            }

            fetch(EFT_CHARGE_STATUS_URL_BASE + '/' + encodeURIComponent(sessionId), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (eftPollCancelled) { return; }

                    if (data.display && data.display.length) { setEftModalStatus(data.display, 'pending'); }
                    updateEftModalKeys(data.done ? null : data.controls, sessionId);
                    if (!data.done) {
                        setTimeout(function () { pollEftTransaction(sessionId, btn, amount, startedAt); }, eftNextPollDelay(!!data.transient_error));
                        return;
                    }
                    if (data.success) {
                        clearEftAttempt();
                        setEftModalStatus(['PAYMENT APPROVED', data.auth_code ? 'Auth ' + data.auth_code : 'Printing…'], 'success');
                        setTimeout(function () {
                            hideEftModal();
                            openPrintView(data.donation_id);
                            resetCart();
                            btn.disabled = false;
                        }, 1200);
                    } else if (data.payment_status === 'unknown') {
                        setEftModalStatus(['RESULT UNKNOWN', 'Check the terminal/bank statement before retrying'], 'error');
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
                    setTimeout(function () { pollEftTransaction(sessionId, btn, amount, startedAt); }, eftNextPollDelay(true));
                });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const banner = document.getElementById('eftResumeBanner');
            if (!banner) { return; }

            if (PENDING_EFT_RECOVERY) {
                const p = PENDING_EFT_RECOVERY;
                document.getElementById('eftResumeBannerText').textContent =
                    'Checking a previous ticket sale (' + CURRENCY_CODE + ' ' + p.amount.toFixed(2) + ') that did not finish…';
                banner.style.display = 'flex';
                document.getElementById('eftResumeBannerDismissBtn').addEventListener('click', function () {
                    banner.style.display = 'none';
                    eftPollCancelled = true;
                    clearEftAttempt();
                });

                const btn = document.getElementById('posSaveBtn');
                btn.disabled = true;
                eftPollCancelled = false;
                eftCurrentSessionId = p.sessionId;
                eftConsecutiveTransientErrors = 0;
                saveEftAttempt({ clientRef: p.clientRef, amount: p.amount, name: p.name, email: p.email, mobile: p.mobile, cart: [] });
                showEftModal(p.amount);
                pollEftTransaction(p.sessionId, btn, p.amount, Date.now());
            }
        });
    </script>
</body>
</html>
