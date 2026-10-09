<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Order</title>
    <style>
        :root {
            --ink: #16211B;
            --accent: #2F6F4E;
            --accent-dark: #1F4E37;
            --accent-light: #E8F3EC;
            --gold: #C9952E;
            --cream: #FBF8F1;
            --white: #FFFFFF;
            --border: #DDE5DF;
            --muted: #5B6B60;
            --danger: #C0392B;
            --danger-light: #FDECEA;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; margin: 0; overscroll-behavior: none; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: var(--cream); color: var(--ink); display: flex; flex-direction: column;
            user-select: none;
        }
        button { font-family: inherit; }
        .hidden { display: none !important; }

        /* ---------- Top bar ---------- */
        .topbar { background: var(--accent); color: #fff; padding: 14px 20px; display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
        .topbar img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; }
        .topbar h1 { font-size: 1.05rem; font-weight: 800; margin: 0; flex: 1; }
        .topbar .back-btn { background: rgba(255,255,255,0.15); border: none; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 700; font-size: 0.9rem; min-height: 44px; }

        /* ---------- Panels ---------- */
        .panel { flex: 1; display: none; flex-direction: column; min-height: 0; }
        .panel.active { display: flex; }

        /* ---------- Browse: tickets grid ---------- */
        #ticketGrid { flex: 1; overflow-y: auto; padding: 18px; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; align-content: start; }
        .tile { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 16px 12px; text-align: center; cursor: pointer; min-height: 150px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 2px 6px rgba(0,0,0,0.04); position: relative; }
        .tile .swatch { width: 100%; height: 44px; border-radius: 10px; margin-bottom: 8px; }
        .tile .name { font-weight: 700; font-size: 0.98rem; line-height: 1.3; }
        .tile .price { font-weight: 800; color: var(--accent-dark); font-size: 1.1rem; margin-top: 6px; }
        .tile .qty-badge { position: absolute; top: 10px; right: 10px; background: var(--accent); color: #fff; font-weight: 800; font-size: 0.85rem; min-width: 26px; height: 26px; border-radius: 50%; display: none; align-items: center; justify-content: center; }
        .tile.has-qty .qty-badge { display: flex; }

        /* ---------- Browse: donation tiers ---------- */
        #donationPane { flex: 1; overflow-y: auto; padding: 18px; }
        .event-banner { background: var(--accent-light); border-radius: 14px; padding: 16px 18px; margin-bottom: 16px; }
        .event-banner h2 { margin: 0; font-size: 1.15rem; color: var(--accent-dark); }
        .tier-pill { background: var(--white); border: 2px solid var(--border); border-radius: 16px; padding: 16px 18px; margin-bottom: 12px; cursor: pointer; }
        .tier-pill.selected { border-color: var(--accent); background: var(--accent-light); }
        .tier-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .tier-label { font-weight: 700; font-size: 1.05rem; }
        .tier-amount { font-weight: 800; color: var(--accent-dark); font-size: 1.1rem; }
        .tier-qty { display: none; align-items: center; gap: 10px; margin-top: 10px; }
        .tier-pill.selected .tier-qty.allow { display: flex; }
        .qty-btn { width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--border); background: var(--white); font-size: 1.2rem; font-weight: 800; }
        .tier-custom-input { display: none; margin-top: 10px; }
        .tier-pill.selected .tier-custom-input.show { display: block; }
        .tier-custom-input input { width: 100%; font-size: 1.3rem; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border); }
        .quick-amounts { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .quick-amount-btn { padding: 10px 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--white); font-weight: 700; }
        .quick-amount-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

        /* ---------- Cart/summary bar ---------- */
        .summary-bar { flex-shrink: 0; background: var(--white); border-top: 1px solid var(--border); padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .summary-bar .total { font-size: 1.3rem; font-weight: 800; }
        .summary-bar .count { color: var(--muted); font-size: 0.85rem; }
        .primary-btn { background: var(--accent); color: #fff; border: none; border-radius: 14px; padding: 16px 28px; font-size: 1.05rem; font-weight: 800; min-height: 48px; cursor: pointer; }
        .primary-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .secondary-btn { background: var(--white); color: var(--ink); border: 1px solid var(--border); border-radius: 14px; padding: 14px 22px; font-size: 1rem; font-weight: 700; min-height: 48px; cursor: pointer; }

        /* ---------- Checkout ---------- */
        #checkoutBody { flex: 1; overflow-y: auto; padding: 20px; max-width: 560px; margin: 0 auto; width: 100%; }
        .checkout-line { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 1rem; }
        .checkout-total-line { display: flex; justify-content: space-between; padding: 16px 0 8px; font-size: 1.3rem; font-weight: 800; }
        .field-group { margin: 18px 0; }
        .field-group label { display: block; font-weight: 700; font-size: 0.9rem; margin-bottom: 6px; }
        .field-group input { width: 100%; padding: 14px; border-radius: 12px; border: 1px solid var(--border); font-size: 1.05rem; min-height: 48px; }
        .pay-methods { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-top: 18px; }
        .pay-method-btn { padding: 18px 10px; border-radius: 14px; border: 2px solid var(--border); background: var(--white); font-weight: 700; font-size: 1rem; min-height: 64px; cursor: pointer; }
        .pay-method-btn.selected { border-color: var(--accent); background: var(--accent-light); }

        /* ---------- Payment panel (EFT) ---------- */
        #panelPayment { align-items: center; justify-content: center; padding: 24px; }
        .pay-card { width: min(92vw, 420px); background: var(--white); border-radius: 20px; padding: 28px 24px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.12); }
        .pay-amount { font-size: 2rem; font-weight: 800; margin-bottom: 18px; }
        .pay-status-box { border-radius: 14px; padding: 18px; margin-bottom: 16px; background: var(--cream); border: 2px solid var(--border); min-height: 70px; display: flex; flex-direction: column; justify-content: center; gap: 4px; }
        .pay-status-box.success { background: var(--accent-light); border-color: var(--accent); }
        .pay-status-box.error { background: var(--danger-light); border-color: var(--danger); }
        .pay-status-line1 { font-weight: 800; font-size: 1.05rem; }
        .pay-status-line2 { color: var(--muted); font-size: 0.92rem; }
        #payActionFramework { margin-bottom: 14px; }
        .sci-af-row { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-bottom: 8px; }
        .sci-af-btn { padding: 14px 20px; border-radius: 12px; border: 2px solid var(--accent); background: #fff; color: var(--accent-dark); font-weight: 800; min-height: 48px; }
        .sci-af-text { color: var(--muted); font-size: 0.9rem; margin-bottom: 8px; }
        .sci-af-details { color: var(--muted); font-size: 0.75rem; margin-top: 10px; }
        .eft-keys { display: none; gap: 10px; justify-content: center; margin-bottom: 14px; }
        .eft-keys.active { display: flex; }
        .eft-key-btn { padding: 14px 22px; border-radius: 12px; border: 2px solid var(--accent); background: #fff; font-weight: 800; min-height: 48px; }

        /* ---------- Confirmation ---------- */
        #panelConfirmation { align-items: center; justify-content: center; text-align: center; padding: 24px; }
        .confirm-icon { width: 92px; height: 92px; border-radius: 50%; background: var(--accent); color: #fff; font-size: 2.6rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; }
        .confirm-number { font-size: 1.6rem; font-weight: 800; color: var(--accent-dark); margin-bottom: 8px; }
        .confirm-message { color: var(--muted); font-size: 1rem; max-width: 420px; margin: 0 auto 28px; line-height: 1.5; }

        /* ---------- Idle warning ---------- */
        #idleOverlay { position: fixed; inset: 0; background: rgba(22,33,27,0.72); display: none; align-items: center; justify-content: center; z-index: 50; }
        #idleOverlay.active { display: flex; }
        .idle-card { background: #fff; border-radius: 20px; padding: 32px 28px; text-align: center; width: min(90vw, 360px); }
        .idle-card h2 { margin: 0 0 8px; }
        .idle-card p { color: var(--muted); margin-bottom: 20px; }

        @media (min-width: 820px) and (orientation: landscape) {
            #panelBrowse { flex-direction: row; }
            #ticketGrid, #donationPane { flex: 1; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <img id="brandLogo" src="" alt="" onerror="this.style.display='none'">
        <h1 id="brandTitle">Loading…</h1>
        <button type="button" class="back-btn hidden" id="backBtn">Back</button>
    </div>

    <!-- Browse -->
    <div class="panel active" id="panelBrowse">
        <div id="ticketGrid" class="hidden"></div>
        <div id="donationPane" class="hidden"></div>
        <div class="summary-bar">
            <div>
                <div class="total" id="browseTotal">$0.00</div>
                <div class="count" id="browseCount">Nothing selected yet</div>
            </div>
            <button type="button" class="primary-btn" id="toCheckoutBtn" disabled>Checkout</button>
        </div>
    </div>

    <!-- Checkout -->
    <div class="panel" id="panelCheckout">
        <div id="checkoutBody">
            <div id="checkoutLines"></div>
            <div class="checkout-total-line"><span>Total</span><span id="checkoutTotal">$0.00</span></div>

            <div class="field-group">
                <label for="donorName">Name <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
                <input type="text" id="donorName" placeholder="Your name">
            </div>
            <div class="field-group" id="emailField">
                <label for="donorEmail">Email <span id="emailOptionalTag" style="font-weight:400;color:var(--muted);">(optional)</span></label>
                <input type="email" id="donorEmail" placeholder="you@example.com">
            </div>
            <div class="field-group" id="mobileField">
                <label for="donorMobile">Mobile <span id="mobileOptionalTag" style="font-weight:400;color:var(--muted);">(optional)</span></label>
                <input type="tel" id="donorMobile" placeholder="04XX XXX XXX">
            </div>

            <div class="field-group">
                <label>Payment Method</label>
                <div class="pay-methods" id="payMethodButtons"></div>
            </div>
        </div>
        <div class="summary-bar">
            <div class="total" id="checkoutBarTotal">$0.00</div>
            <button type="button" class="primary-btn" id="placeOrderBtn" disabled>Select a payment method</button>
        </div>
    </div>

    <!-- Payment -->
    <div class="panel" id="panelPayment">
        <div class="pay-card">
            <div class="pay-amount" id="payAmount">$0.00</div>
            <div class="pay-status-box" id="payStatusBox">
                <div class="pay-status-line1" id="payStatusLine1">Starting…</div>
                <div class="pay-status-line2" id="payStatusLine2"></div>
            </div>
            <div id="payActionFramework"></div>
            <div class="eft-keys" id="eftKeys">
                <button type="button" class="eft-key-btn" id="eftKeyYes">Yes</button>
                <button type="button" class="eft-key-btn" id="eftKeyOk">OK</button>
                <button type="button" class="eft-key-btn" id="eftKeyNo">No</button>
                <button type="button" class="eft-key-btn" id="eftKeyAuthorise">Authorise</button>
            </div>
            <!-- A self-service kiosk never lets an anonymous customer self-declare a stalled
                 payment's outcome (unlike the staff-operated POS pages' own override Yes/No) —
                 this only ever appears after 60s+ of zero progress. The framework-wired
                 Yes/No buttons it needs to exist stay permanently off-screen (never shown to
                 the customer); the visible content is just a dead-end "get staff" message. -->
            <div id="payOverride" hidden>
                <p style="font-weight:700;margin-bottom:14px;" id="payOverrideQuestion">We couldn't confirm this payment automatically.</p>
                <p id="payOverrideSaving" hidden style="color:var(--muted);">Recording…</p>
                <button type="button" class="primary-btn" style="width:100%;" id="payOverrideHelpBtn">Please see a staff member</button>
                <button type="button" id="payOverrideYesBtn" style="position:absolute;left:-9999px;" tabindex="-1" aria-hidden="true">Yes</button>
                <button type="button" id="payOverrideNoBtn" style="position:absolute;left:-9999px;" tabindex="-1" aria-hidden="true">No</button>
                <button type="button" id="payOverrideKeepWaitingBtn" style="position:absolute;left:-9999px;" tabindex="-1" aria-hidden="true">Keep waiting</button>
            </div>
            <button type="button" class="secondary-btn" id="payCancelBtn" style="width:100%;">Cancel</button>
        </div>
    </div>

    <!-- Confirmation -->
    <div class="panel" id="panelConfirmation">
        <div class="confirm-icon">&#10003;</div>
        <div class="confirm-number" id="confirmNumber"></div>
        <p class="confirm-message" id="confirmMessage">Thank you!</p>
        <button type="button" class="primary-btn" id="doneBtn">Done</button>
    </div>

    <!-- Idle warning -->
    <div id="idleOverlay">
        <div class="idle-card">
            <h2>Still there?</h2>
            <p id="idleCountdownText">Returning to the start screen in 30s…</p>
            <button type="button" class="primary-btn" id="idleStayBtn" style="width:100%;">I'm still here</button>
        </div>
    </div>

    <script src="{{ asset('js/sci-action-framework.js') }}?v={{ @filemtime(public_path('js/sci-action-framework.js')) }}"></script>
    <script src="{{ asset('js/print-agent.js') }}?v={{ @filemtime(public_path('js/print-agent.js')) }}"></script>
    <script>
    (function () {
        'use strict';

        var STORAGE_KEY = 'ssvkKioskDeviceToken';
        var TOKEN = null;
        try { TOKEN = localStorage.getItem(STORAGE_KEY); } catch (e) {}
        if (!TOKEN) { window.location.href = '{{ route('kiosk.pair') }}'; return; }

        var WELCOME_URL = '{{ route('kiosk.welcome') }}';
        var CSRF_TOKEN = '{{ csrf_token() }}';
        var BOOT = null; // populated from bootstrap()
        var CURRENCY = 'AUD';

        var cart = {};          // tickets: ticket_id -> {id, name, price, quantity}
        var selections = {};    // donations: option_key -> {option_id, label, quantity, amount}
        var checkoutContext = null; // {kind:'tickets'|'donations', lines:[...], total, meta}
        var selectedPaymentMethod = null;

        function money(n) { return CURRENCY + ' ' + Number(n).toFixed(2); }

        function kioskFetch(url, opts) {
            opts = opts || {};
            opts.headers = Object.assign({ 'X-Kiosk-Device-Token': TOKEN, 'Accept': 'application/json' }, opts.headers || {});
            return fetch(url, opts).then(function (res) {
                if (res.status === 401) { window.location.href = '{{ route('kiosk.pair') }}'; return Promise.reject('unauthorised'); }
                return res.json().then(function (data) { return { status: res.status, data: data }; });
            });
        }

        function showPanel(name) {
            ['Browse', 'Checkout', 'Payment', 'Confirmation'].forEach(function (p) {
                document.getElementById('panel' + p).classList.toggle('active', p === name);
            });
            document.getElementById('backBtn').classList.toggle('hidden', name !== 'Checkout');
            idle.paused = (name === 'Payment');
        }

        // ============================================================
        // Bootstrap
        // ============================================================
        kioskFetch('{{ route('kiosk.api.bootstrap') }}').then(function (result) {
            BOOT = result.data;
            document.getElementById('brandLogo').src = (BOOT.temple && BOOT.temple.logo) || '';
            document.getElementById('brandTitle').textContent = (BOOT.temple && (BOOT.temple.brand_title || BOOT.temple.name)) || 'Order';
            CURRENCY = (BOOT.temple && BOOT.temple.currency) || 'AUD';

            if (!BOOT.module || (BOOT.module === 'donations' && BOOT.closed)) {
                document.getElementById('brandTitle').textContent = 'Not available right now';
                return;
            }

            if (BOOT.module === 'tickets') {
                renderTicketGrid();
            } else {
                renderDonationTiers();
            }
            idle.start();
        }).catch(function () {});

        // ============================================================
        // Tickets — cart
        // ============================================================
        function renderTicketGrid() {
            var grid = document.getElementById('ticketGrid');
            grid.classList.remove('hidden');
            grid.innerHTML = '';
            (BOOT.catalog || []).forEach(function (t) {
                var tile = document.createElement('div');
                tile.className = 'tile';
                tile.id = 'tile-' + t.id;
                tile.innerHTML =
                    '<div><div class="swatch" style="background:' + (t.background_color || '#2F6F4E') + ';"></div>' +
                    '<div class="name">' + escapeHtml(t.name) + '</div></div>' +
                    '<div class="price">' + money(t.price) + '</div>' +
                    '<div class="qty-badge" id="qtybadge-' + t.id + '">0</div>';
                tile.addEventListener('click', function () { addToCart(t); });
                grid.appendChild(tile);
            });
        }

        function addToCart(ticket) {
            var line = cart[ticket.id] || { id: ticket.id, name: ticket.name, price: Number(ticket.price), quantity: 0 };
            line.quantity += 1;
            cart[ticket.id] = line;
            renderCartSummary();
        }

        function renderCartSummary() {
            var total = 0, count = 0;
            Object.keys(cart).forEach(function (id) {
                var l = cart[id];
                total += l.price * l.quantity;
                count += l.quantity;
                var badge = document.getElementById('qtybadge-' + id);
                var tile = document.getElementById('tile-' + id);
                if (badge) { badge.textContent = l.quantity; }
                if (tile) { tile.classList.toggle('has-qty', l.quantity > 0); }
            });
            document.getElementById('browseTotal').textContent = money(total);
            document.getElementById('browseCount').textContent = count > 0 ? (count + ' item' + (count === 1 ? '' : 's') + ' selected') : 'Nothing selected yet';
            document.getElementById('toCheckoutBtn').disabled = count === 0;
        }

        // ============================================================
        // Donations — tiers (mirrors event_donation_options: fixed+qty, free-amount, or the
        // QUICK_AMOUNTS fallback when an event has no options configured at all)
        // ============================================================
        var QUICK_AMOUNTS = [51, 101, 201, 501, 1001];

        function renderDonationTiers() {
            var pane = document.getElementById('donationPane');
            pane.classList.remove('hidden');
            var html = '<div class="event-banner"><h2>' + escapeHtml(BOOT.event.name) + '</h2></div>';

            var options = BOOT.donation_options || [];
            if (!options.length) {
                html += '<div class="tier-pill" id="tier-quick"><div class="tier-row"><span class="tier-label">General Donation</span></div>' +
                    '<div class="quick-amounts">' + QUICK_AMOUNTS.map(function (a) { return '<button type="button" class="quick-amount-btn" data-amount="' + a + '">' + money(a) + '</button>'; }).join('') +
                    '</div><div class="tier-custom-input show" style="margin-top:10px;"><input type="number" min="1" step="0.01" id="quickCustomAmount" placeholder="Or enter your own amount"></div></div>';
                pane.innerHTML = html;
                bindQuickAmounts();
                return;
            }

            options.forEach(function (opt) {
                var hasAmount = opt.amount !== null;
                var key = 'opt-' + opt.id;
                html += '<div class="tier-pill" id="' + key + '" data-option-id="' + opt.id + '" data-label="' + escapeHtml(opt.label) + '">' +
                    '<div class="tier-row"><span class="tier-label">' + escapeHtml(opt.label) + '</span>' +
                    (hasAmount ? '<span class="tier-amount">' + money(opt.amount) + '</span>' : '') + '</div>';
                if (hasAmount && opt.allow_quantity) {
                    html += '<div class="tier-qty allow"><button type="button" class="qty-btn" data-dir="-1">−</button><span id="qtyval-' + opt.id + '">1</span><button type="button" class="qty-btn" data-dir="1">+</button></div>';
                }
                if (!hasAmount) {
                    html += '<div class="tier-custom-input show"><input type="number" min="1" step="0.01" placeholder="Enter amount" id="amtinput-' + opt.id + '"></div>';
                }
                html += '</div>';
            });
            pane.innerHTML = html;

            options.forEach(function (opt) {
                var el = document.getElementById('opt-' + opt.id);
                el.addEventListener('click', function (e) {
                    if (e.target.closest('.qty-btn') || e.target.tagName === 'INPUT') { return; }
                    toggleDonationOption(opt, el);
                });
                var qtyBtns = el.querySelectorAll('.qty-btn');
                qtyBtns.forEach(function (b) {
                    b.addEventListener('click', function (e) {
                        e.stopPropagation();
                        adjustDonationQty(opt, parseInt(b.dataset.dir, 10));
                    });
                });
                var amtInput = document.getElementById('amtinput-' + opt.id);
                if (amtInput) {
                    amtInput.addEventListener('input', function () { setFreeAmount(opt, el, amtInput.value); });
                    amtInput.addEventListener('click', function (e) { e.stopPropagation(); });
                }
            });
        }

        function bindQuickAmounts() {
            var el = document.getElementById('tier-quick');
            var buttons = el.querySelectorAll('.quick-amount-btn');
            var customInput = document.getElementById('quickCustomAmount');
            buttons.forEach(function (b) {
                b.addEventListener('click', function () {
                    buttons.forEach(function (x) { x.classList.remove('active'); });
                    b.classList.add('active');
                    customInput.value = '';
                    selections['quick'] = { option_id: null, label: 'General Donation', quantity: 1, amount: Number(b.dataset.amount) };
                    el.classList.add('selected');
                    recalcDonationTotal();
                });
            });
            customInput.addEventListener('input', function () {
                buttons.forEach(function (x) { x.classList.remove('active'); });
                var v = parseFloat(customInput.value);
                if (v > 0) {
                    selections['quick'] = { option_id: null, label: 'General Donation', quantity: 1, amount: v };
                    el.classList.add('selected');
                } else {
                    delete selections['quick'];
                    el.classList.remove('selected');
                }
                recalcDonationTotal();
            });
        }

        function toggleDonationOption(opt, el) {
            var key = 'opt-' + opt.id;
            if (selections[key]) {
                delete selections[key];
                el.classList.remove('selected');
            } else {
                var amount = opt.amount !== null ? Number(opt.amount) : 0;
                selections[key] = { option_id: opt.id, label: opt.label, quantity: 1, amount: amount };
                el.classList.add('selected');
            }
            recalcDonationTotal();
        }

        function adjustDonationQty(opt, dir) {
            var key = 'opt-' + opt.id;
            var sel = selections[key];
            if (!sel) { return; }
            sel.quantity = Math.max(1, sel.quantity + dir);
            var qtyEl = document.getElementById('qtyval-' + opt.id);
            if (qtyEl) { qtyEl.textContent = sel.quantity; }
            recalcDonationTotal();
        }

        function setFreeAmount(opt, el, value) {
            var key = 'opt-' + opt.id;
            var v = parseFloat(value);
            if (v > 0) {
                selections[key] = { option_id: opt.id, label: opt.label, quantity: 1, amount: v };
                el.classList.add('selected');
            } else {
                delete selections[key];
                el.classList.remove('selected');
            }
            recalcDonationTotal();
        }

        function recalcDonationTotal() {
            var total = 0, count = 0;
            Object.keys(selections).forEach(function (k) {
                var s = selections[k];
                total += s.amount * s.quantity;
                count += 1;
            });
            document.getElementById('browseTotal').textContent = money(total);
            document.getElementById('browseCount').textContent = count > 0 ? 'Donation selected' : 'Nothing selected yet';
            document.getElementById('toCheckoutBtn').disabled = total <= 0;
        }

        // ============================================================
        // Checkout panel
        // ============================================================
        document.getElementById('toCheckoutBtn').addEventListener('click', function () {
            buildCheckoutContext();
            renderCheckout();
            showPanel('Checkout');
        });
        document.getElementById('backBtn').addEventListener('click', function () { showPanel('Browse'); });

        function buildCheckoutContext() {
            if (BOOT.module === 'tickets') {
                var lines = Object.values(cart).map(function (l) { return { label: l.name + ' × ' + l.quantity, amount: l.price * l.quantity }; });
                var total = lines.reduce(function (s, l) { return s + l.amount; }, 0);
                checkoutContext = { kind: 'tickets', lines: lines, total: total };
            } else {
                var dlines = Object.keys(selections).map(function (k) {
                    var s = selections[k];
                    var qtyTxt = s.quantity > 1 ? ' × ' + s.quantity : '';
                    return { label: s.label + qtyTxt, amount: s.amount * s.quantity };
                });
                var dtotal = dlines.reduce(function (s, l) { return s + l.amount; }, 0);
                checkoutContext = { kind: 'donations', lines: dlines, total: dtotal };
            }
        }

        function renderCheckout() {
            var linesEl = document.getElementById('checkoutLines');
            linesEl.innerHTML = checkoutContext.lines.map(function (l) {
                return '<div class="checkout-line"><span>' + escapeHtml(l.label) + '</span><span>' + money(l.amount) + '</span></div>';
            }).join('');
            document.getElementById('checkoutTotal').textContent = money(checkoutContext.total);
            document.getElementById('checkoutBarTotal').textContent = money(checkoutContext.total);

            var requireEmail = BOOT.module === 'donations' && BOOT.event.require_email;
            var requireMobile = BOOT.module === 'donations' && BOOT.event.require_mobile;
            document.getElementById('emailOptionalTag').textContent = requireEmail ? '(required)' : '(optional)';
            document.getElementById('mobileOptionalTag').textContent = requireMobile ? '(required)' : '(optional)';

            selectedPaymentMethod = null;
            var methods = BOOT.payment_methods || [];
            var methodsEl = document.getElementById('payMethodButtons');
            methodsEl.innerHTML = methods.map(function (m) {
                return '<button type="button" class="pay-method-btn" data-method="' + m + '">' + (m === 'EFT Terminal' ? 'Card' : m) + '</button>';
            }).join('');
            methodsEl.querySelectorAll('.pay-method-btn').forEach(function (b) {
                b.addEventListener('click', function () {
                    methodsEl.querySelectorAll('.pay-method-btn').forEach(function (x) { x.classList.remove('selected'); });
                    b.classList.add('selected');
                    selectedPaymentMethod = b.dataset.method;
                    var placeBtn = document.getElementById('placeOrderBtn');
                    placeBtn.disabled = false;
                    placeBtn.textContent = selectedPaymentMethod === 'EFT Terminal' ? 'Pay with Card' : 'Place Order';
                });
            });
        }

        document.getElementById('placeOrderBtn').addEventListener('click', function () {
            if (!selectedPaymentMethod) { return; }
            var requireEmail = BOOT.module === 'donations' && BOOT.event.require_email;
            var requireMobile = BOOT.module === 'donations' && BOOT.event.require_mobile;
            var email = document.getElementById('donorEmail').value.trim();
            var mobile = document.getElementById('donorMobile').value.trim();
            if (requireEmail && !email) { alert('Please enter an email address.'); return; }
            if (requireMobile && !mobile) { alert('Please enter a mobile number.'); return; }

            var btn = this;
            btn.disabled = true;

            if (selectedPaymentMethod === 'EFT Terminal') {
                btn.disabled = false;
                startCardPayment();
                return;
            }

            if (BOOT.module === 'tickets') {
                submitTicketCheckout(selectedPaymentMethod).finally(function () { btn.disabled = false; });
            } else {
                submitDonationCheckout(selectedPaymentMethod).finally(function () { btn.disabled = false; });
            }
        });

        function donorFields() {
            return {
                name: document.getElementById('donorName').value.trim(),
                email: document.getElementById('donorEmail').value.trim(),
                mobile: document.getElementById('donorMobile').value.trim(),
            };
        }

        function submitTicketCheckout(method) {
            var donor = donorFields();
            var cartLines = Object.values(cart).map(function (l) { return { ticket_id: l.id, name: l.name, price: l.price, quantity: l.quantity }; });
            var body = new URLSearchParams();
            body.set('customer_name', donor.name);
            body.set('email', donor.email);
            body.set('mobile', donor.mobile);
            body.set('cart_json', JSON.stringify(cartLines));
            body.set('payment_method', method);

            return kioskFetch('{{ route('kiosk.api.tickets.checkout') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            }).then(function (result) {
                if (!result.data.success) { alert(result.data.message || 'Could not record the order.'); return; }
                var orderId = result.data.order_id;
                cart = {};
                showConfirmation('#' + String(orderId).padStart(5, '0'), 'Your tickets have been recorded.');
                kioskFetch('{{ url('/kiosk/api/tickets/auto-print') }}/' + orderId, {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                }).catch(function () {});
            }).catch(function () { alert('Network error — please try again.'); });
        }

        function submitDonationCheckout(method) {
            var donor = donorFields();
            var serverMethod = method === 'Bank Transfer' ? 'Bank' : method;
            var purpose = checkoutContext.lines.map(function (l) { return l.label; }).join(', ').slice(0, 250) || 'Event Donation';
            var selectionsJson = Object.keys(selections).map(function (k) {
                var s = selections[k];
                return { option_id: s.option_id, label: s.label, quantity: s.quantity, amount: s.amount };
            });

            var body = new URLSearchParams();
            body.set('event_id', BOOT.event.id);
            body.set('amount', checkoutContext.total.toFixed(2));
            body.set('selections_json', JSON.stringify(selectionsJson));
            body.set('payment_status', 'Paid');
            body.set('donor_name', donor.name || 'Guest');
            body.set('donation_date', new Date().toISOString().slice(0, 10));
            body.set('email', donor.email);
            body.set('mobile', donor.mobile);
            body.set('payment_method', serverMethod);
            body.set('purpose', purpose);
            body.set('purpose_details', '');

            return kioskFetch('{{ route('kiosk.api.donations.guest') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            }).then(function (result) {
                if (!result.data.success) { alert(result.data.message || 'Could not record the donation.'); return; }
                selections = {};
                var receiptNo = result.data.donation_id ? ('G-' + String(result.data.donation_id).padStart(6, '0')) : '';
                showConfirmation(receiptNo, 'Thank you for your generosity! A receipt has been emailed if you provided your email address.');
            }).catch(function () { alert('Network error — please try again.'); });
        }

        // ============================================================
        // Card payment — mx51 (Action Framework, reused as-is) or Linkly (fixed-key flow)
        // ============================================================
        var eftKeyButtons = {
            yes: document.getElementById('eftKeyYes'), ok: document.getElementById('eftKeyOk'),
            no: document.getElementById('eftKeyNo'), authorise: document.getElementById('eftKeyAuthorise'),
        };
        var linklySessionId = null, linklyPollCancelled = false, linklyConsecutiveErrors = 0;

        var sciFlow = SciActionFramework.createFlow({
            startUrl: '',
            statusUrlBase: '{{ url('/kiosk/api/cba-sci/charge/status') }}',
            actionUrlBase: '{{ url('/kiosk/api/cba-sci/charge/action') }}',
            cancelUrlBase: '{{ url('/kiosk/api/cba-sci/charge/cancel') }}',
            overrideUrlBase: '{{ url('/kiosk/api/cba-sci/charge/cancel') }}',
            csrfToken: CSRF_TOKEN,
            currencyCode: CURRENCY,
            attemptStorageKey: 'kioskSciAttempt',
            el: {
                overlay: document.getElementById('panelPayment'),
                amount: document.getElementById('payAmount'),
                statusBox: document.getElementById('payStatusBox'),
                statusLine1: document.getElementById('payStatusLine1'),
                statusLine2: document.getElementById('payStatusLine2'),
                actionContainer: document.getElementById('payActionFramework'),
                cancelBtn: document.getElementById('payCancelBtn'),
                // A self-service kiosk never lets an anonymous customer self-declare a
                // stalled payment's outcome — these three stay permanently off-screen (see
                // the markup above); the visible override UI is just a "get staff" dead-end.
                overrideBox: document.getElementById('payOverride'),
                overrideYesBtn: document.getElementById('payOverrideYesBtn'),
                overrideNoBtn: document.getElementById('payOverrideNoBtn'),
                overrideKeepWaitingBtn: document.getElementById('payOverrideKeepWaitingBtn'),
                overrideQuestion: document.getElementById('payOverrideQuestion'),
                overrideSaving: document.getElementById('payOverrideSaving'),
                countdown: null,
                headerTitleError: document.getElementById('payStatusLine1'),
            },
            buildStartBody: function (attempt) {
                var extra = { donor_name: attempt.name || '', email: attempt.email || '', mobile: attempt.mobile || '' };
                if (BOOT.module === 'tickets') { extra.cart_json = JSON.stringify(attempt.cart || []); }
                else { extra.purpose = attempt.purpose || ''; extra.purpose_details = ''; }
                return extra;
            },
            onToast: function () {},
            onApproved: function (resultId) { finishCardPayment(resultId); },
            onDeclined: function (message) { showPaymentError(message || 'Payment declined.'); },
            onUnresolved: function (message) { showPaymentError(message || 'Could not confirm the result — please see a staff member.'); },
            onLocalCancel: function () { showPanel('Checkout'); },
        });

        function startCardPayment() {
            showPanel('Payment');
            document.getElementById('payAmount').textContent = money(checkoutContext.total);
            document.getElementById('payStatusBox').className = 'pay-status-box';
            document.getElementById('payStatusLine1').textContent = 'Starting…';
            document.getElementById('payStatusLine2').textContent = '';
            document.getElementById('payActionFramework').innerHTML = '';
            document.getElementById('eftKeys').classList.remove('active');

            var donor = donorFields();
            var clientRef = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('kiosk-' + Date.now() + '-' + Math.random().toString(36).slice(2));
            var provider = BOOT.eft ? BOOT.eft.provider : 'linkly';

            if (provider === 'cba_sci') {
                var startUrlBase = BOOT.module === 'tickets' ? '{{ url('/kiosk/api/tickets/cba-sci/start') }}' : '{{ url('/kiosk/api/donations/cba-sci/start') }}';
                sciFlow.startUrl = startUrlBase;
                sciFlow.start(document.getElementById('placeOrderBtn'), {
                    amount: checkoutContext.total, clientRef: clientRef, name: donor.name || 'Customer',
                    email: donor.email, mobile: donor.mobile,
                    cart: Object.values(cart).map(function (l) { return { ticket_id: l.id, name: l.name, price: l.price, quantity: l.quantity }; }),
                    purpose: checkoutContext.lines.map(function (l) { return l.label; }).join(', ').slice(0, 250),
                    terminalId: BOOT.eft.terminal_id,
                });
            } else {
                startLinklyPayment(clientRef, donor);
            }
        }

        function startLinklyPayment(clientRef, donor) {
            linklyPollCancelled = false;
            linklySessionId = null;
            linklyConsecutiveErrors = 0;

            var startUrlBase = BOOT.module === 'tickets' ? '{{ route('kiosk.api.tickets.eft.start') }}' : '{{ route('kiosk.api.donations.eft.start') }}';
            var body = new URLSearchParams();
            body.set('amount', checkoutContext.total.toFixed(2));
            body.set('client_ref', clientRef);
            body.set('donor_name', donor.name || '');
            body.set('email', donor.email || '');
            body.set('mobile', donor.mobile || '');
            if (BOOT.eft && BOOT.eft.terminal_id) { body.set('terminal_id', BOOT.eft.terminal_id); }
            if (BOOT.module === 'tickets') {
                var cartLines = Object.values(cart).map(function (l) { return { ticket_id: l.id, name: l.name, price: l.price, quantity: l.quantity }; });
                body.set('cart_json', JSON.stringify(cartLines));
            } else {
                body.set('event_id', BOOT.event.id);
                body.set('purpose', checkoutContext.lines.map(function (l) { return l.label; }).join(', ').slice(0, 250));
                body.set('purpose_details', '');
            }

            kioskFetch(startUrlBase, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            }).then(function (result) {
                if (!result.data.success) { showPaymentError(result.data.message || 'Could not start the terminal transaction.'); return; }
                linklySessionId = result.data.session_id;
                pollLinkly(donor);
            }).catch(function () { showPaymentError('Could not reach the EFT terminal — please try again.'); });
        }

        function linklyNextDelay(transient) {
            if (!transient) { linklyConsecutiveErrors = 0; return 1200; }
            linklyConsecutiveErrors++;
            return Math.min(1200 * Math.pow(2, linklyConsecutiveErrors), 30000);
        }

        function updateEftKeys(controls) {
            var anyVisible = false;
            Object.keys(eftKeyButtons).forEach(function (key) {
                var visible = !!(controls && controls[key]);
                eftKeyButtons[key].hidden = !visible;
                eftKeyButtons[key].onclick = visible ? function () { sendLinklyKey(key); } : null;
                if (visible) { anyVisible = true; }
            });
            document.getElementById('eftKeys').classList.toggle('active', anyVisible);
        }

        function sendLinklyKey(key) {
            Object.values(eftKeyButtons).forEach(function (b) { b.disabled = true; });
            kioskFetch('{{ url('/kiosk/api/eft/charge/sendkey') }}/' + encodeURIComponent(linklySessionId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'key=' + encodeURIComponent(key),
            }).then(function () { Object.values(eftKeyButtons).forEach(function (b) { b.disabled = false; }); })
              .catch(function () { Object.values(eftKeyButtons).forEach(function (b) { b.disabled = false; }); });
        }

        function pollLinkly(donor) {
            if (linklyPollCancelled) { return; }
            kioskFetch('{{ url('/kiosk/api/eft/charge/status') }}/' + encodeURIComponent(linklySessionId))
                .then(function (result) {
                    if (linklyPollCancelled) { return; }
                    var data = result.data;
                    if (data.display && data.display.length) {
                        document.getElementById('payStatusLine1').textContent = data.display[0] || '';
                        document.getElementById('payStatusLine2').textContent = data.display[1] || '';
                    }
                    updateEftKeys(data.done ? null : data.controls);
                    if (!data.done) {
                        setTimeout(function () { pollLinkly(donor); }, linklyNextDelay(!!data.transient_error));
                        return;
                    }
                    if (data.success) {
                        finishCardPayment(data.donation_id, true);
                    } else {
                        showPaymentError(data.message || 'Payment declined.');
                    }
                })
                .catch(function () { setTimeout(function () { pollLinkly(donor); }, linklyNextDelay(true)); });
        }

        document.getElementById('payOverrideHelpBtn').addEventListener('click', returnToWelcome);

        document.getElementById('payCancelBtn').addEventListener('click', function () {
            if (linklySessionId) {
                linklyPollCancelled = true;
                kioskFetch('{{ url('/kiosk/api/eft/charge/cancel') }}/' + encodeURIComponent(linklySessionId), {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                }).catch(function () {});
                showPanel('Checkout');
                return;
            }
            if (sciFlow && document.getElementById('panelPayment').classList.contains('active') && BOOT.eft && BOOT.eft.provider === 'cba_sci') {
                // sci-action-framework.js's own Cancel Payment button is cfg.el.cancelBtn —
                // already wired to this same button, its own click handler handles the rest.
                return;
            }
            showPanel('Checkout');
        });

        function showPaymentError(message) {
            document.getElementById('payStatusBox').className = 'pay-status-box error';
            document.getElementById('payStatusLine1').textContent = 'Payment not completed';
            document.getElementById('payStatusLine2').textContent = message;
            setTimeout(function () { showPanel('Checkout'); }, 2600);
        }

        function finishCardPayment(resultId) {
            linklySessionId = null;
            document.getElementById('payStatusBox').className = 'pay-status-box success';
            document.getElementById('payStatusLine1').textContent = 'Payment approved';
            var isTicket = BOOT.module === 'tickets';
            var number = isTicket ? ('#' + String(resultId).padStart(5, '0')) : (resultId ? ('G-' + String(resultId).padStart(6, '0')) : '');
            var message = isTicket ? 'Your tickets have been recorded.' : 'Thank you for your generosity!';
            if (isTicket) { cart = {}; } else { selections = {}; }
            setTimeout(function () {
                showConfirmation(number, message);
                if (isTicket && resultId) {
                    kioskFetch('{{ url('/kiosk/api/tickets/auto-print') }}/' + resultId, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } }).catch(function () {});
                }
            }, 1000);
        }

        // ============================================================
        // Confirmation
        // ============================================================
        function showConfirmation(number, message) {
            document.getElementById('confirmNumber').textContent = number;
            document.getElementById('confirmMessage').textContent = message;
            showPanel('Confirmation');
            idle.scheduleAutoReturn();
        }
        document.getElementById('doneBtn').addEventListener('click', returnToWelcome);

        function returnToWelcome() { window.location.href = WELCOME_URL; }

        // ============================================================
        // Idle timer — resets on touch, paused during Payment panel (a payment mid-flight
        // must never be interrupted), hard-returns to Welcome on timeout (which also resets
        // all JS state for free via a full page navigation).
        // ============================================================
        var idle = (function () {
            var WARN_AFTER_MS = 90000, RESET_AFTER_MS = 120000, CONFIRM_AUTO_MS = 10000;
            var lastActivity = Date.now();
            var warnTimer = null, resetTimer = null, autoReturnTimer = null;
            var started = false;

            function reset() {
                lastActivity = Date.now();
                document.getElementById('idleOverlay').classList.remove('active');
                clearTimeout(warnTimer);
                clearTimeout(resetTimer);
                if (!started || idle.paused) { return; }
                warnTimer = setTimeout(showWarning, WARN_AFTER_MS);
            }
            function showWarning() {
                if (idle.paused) { return; }
                document.getElementById('idleOverlay').classList.add('active');
                var remaining = Math.ceil((RESET_AFTER_MS - WARN_AFTER_MS) / 1000);
                document.getElementById('idleCountdownText').textContent = 'Returning to the start screen in ' + remaining + 's…';
                resetTimer = setTimeout(returnToWelcome, RESET_AFTER_MS - WARN_AFTER_MS);
            }
            document.getElementById('idleStayBtn').addEventListener('click', reset);
            ['touchstart', 'click', 'keydown'].forEach(function (evt) {
                document.addEventListener(evt, function () { if (!idle.paused) { reset(); } }, { passive: true });
            });

            return {
                paused: false,
                start: function () { started = true; reset(); },
                scheduleAutoReturn: function () {
                    clearTimeout(autoReturnTimer);
                    autoReturnTimer = setTimeout(returnToWelcome, CONFIRM_AUTO_MS);
                },
            };
        })();

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
    })();
    </script>
</body>
</html>
