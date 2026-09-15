<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Console · {{ $event->event_name }}</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f6f1eb; color: #1e1e2a; }
        .console-topbar {
            background: linear-gradient(135deg, #2d1f0e, #4a3520);
            color: white;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .console-topbar h1 { font-size: 1.2rem; font-weight: 700; margin: 0; }
        .console-topbar .back-link { color: rgba(255,255,255,0.7); font-size: 0.85rem; text-decoration: none; }
        .console-topbar .back-link:hover { color: white; }
        .console-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .console-tab-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: 0.2s;
        }
        .console-tab-btn.active { background: #b8863a; }
        .console-tab-btn:hover { background: #b8863a; }
        .btn-fullscreen { background: rgba(255,255,255,0.15); border: none; color: white; padding: 10px 16px; border-radius: 40px; font-weight: 600; font-size: 0.85rem; }
        .console-body { padding: 24px; max-width: 1400px; margin: 0 auto; }
        .console-pane { display: none; }
        .console-pane.active { display: block; }
        .console-card { background: white; border-radius: 20px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.04); margin-bottom: 20px; }
        .stat-tile { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 8px 24px rgba(0,0,0,0.04); text-align: center; }
        .stat-tile .label { color: #7b6b5a; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
        .stat-tile .value { font-size: 1.7rem; font-weight: 700; color: #2d1f0e; margin-top: 6px; }
        .quick-entry-toggle { display: flex; gap: 10px; margin-bottom: 20px; }
        .quick-entry-toggle button { flex: 1; padding: 14px; border-radius: 14px; border: 2px solid #f0ece6; background: white; font-weight: 700; font-size: 1rem; }
        .quick-entry-toggle button.active { border-color: #b8863a; background: #fdf6ea; color: #b8863a; }
        .qe-field label { font-weight: 600; font-size: 0.85rem; margin-bottom: 4px; display: block; }
        .qe-field input, .qe-field select { padding: 14px; font-size: 1.05rem; border-radius: 12px; }
        .devotee-combobox-wrap { position: relative; }
        .devotee-combobox-results { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e5ddd0; border-radius: 12px; max-height: 260px; overflow-y: auto; z-index: 20; box-shadow: 0 12px 30px rgba(0,0,0,0.1); display: none; }
        .devotee-combobox-results.show { display: block; }
        .devotee-combobox-item { padding: 12px 16px; cursor: pointer; border-bottom: 1px solid #f0ece6; }
        .devotee-combobox-item:hover, .devotee-combobox-item.highlighted { background: #fdf6ea; }
        .donation-tier-option { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 16px; border: 2px solid #f0ece6; border-radius: 14px; margin-bottom: 10px; cursor: pointer; }
        .donation-tier-option.selected { border-color: #b8863a; background: #fdf9f2; }
        .donation-tier-option label { font-size: 1rem; margin: 0; cursor: pointer; }
        .btn-save-next { width: 100%; padding: 20px; font-size: 1.2rem; font-weight: 700; background: linear-gradient(135deg, #b8863a, #d4a05a); color: white; border: none; border-radius: 16px; }
        .btn-save-next:disabled { opacity: 0.6; }
        .qe-toast { position: fixed; bottom: 24px; right: 24px; background: #1f9d6a; color: white; padding: 16px 24px; border-radius: 14px; font-weight: 600; box-shadow: 0 12px 30px rgba(0,0,0,0.15); z-index: 999; display: none; }
        .qe-toast.error { background: #dc3545; }
        table.console-table { width: 100%; border-collapse: collapse; }
        table.console-table th, table.console-table td { padding: 10px 12px; text-align: left; font-size: 0.85rem; border-bottom: 1px solid #f0ece6; }
        table.console-table th { background: #faf5eb; font-weight: 700; color: #7b6b5a; text-transform: uppercase; font-size: 0.72rem; }
        .btn-refresh { background: #f0ece6; border: none; padding: 8px 18px; border-radius: 40px; font-weight: 600; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="console-topbar">
        <div>
            @php $backRoute = session('active_role', auth()->user()->role ?? null) === 'Event Coordinator' ? 'event-coordinator.my-events' : 'admin.events.index'; @endphp
            <a href="{{ route($backRoute) }}" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back to {{ $backRoute === 'event-coordinator.my-events' ? 'My Events' : 'Events' }}</a>
            <h1 class="mt-1">{{ $event->event_name }}</h1>
        </div>
        <div class="console-tabs">
            <button type="button" class="console-tab-btn active" data-pane="pane-table">Donations Table</button>
            @if($canAddDonation)
            <button type="button" class="console-tab-btn" data-pane="pane-entry">Quick Entry</button>
            @endif
            <button type="button" class="console-tab-btn" data-pane="pane-dashboard">Dashboard</button>
        </div>
        <button type="button" class="btn-fullscreen" id="fullscreenBtn"><i class="bi bi-arrows-fullscreen me-1"></i>Fullscreen</button>
    </div>

    <div class="console-body">
        <!-- DONATIONS TABLE -->
        <div class="console-pane active" id="pane-table">
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn-refresh" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
            </div>
            <div class="console-card" style="overflow-x:auto;">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>Type</th><th>ID</th><th>Name</th><th>Contact</th>
                            @foreach($options as $opt)<th>{{ $opt->label }}</th>@endforeach
                            <th>Other</th><th>Total</th><th>Payment</th><th>Txn ID</th><th>Date</th><th>Status</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->donation_type === 'devotee' ? 'Devotee' : 'Guest' }}</td>
                            <td><strong>{{ $row->display_id }}</strong></td>
                            <td>{{ $row->display_name }}</td>
                            <td>{{ $row->mobile ?? $row->email ?? '—' }}</td>
                            @foreach($options as $opt)
                            <td>@if(($row->option_amounts[$opt->id] ?? 0) > 0){{ number_format($row->option_amounts[$opt->id], 2) }}@else — @endif</td>
                            @endforeach
                            <td>@if($row->other_amount > 0){{ number_format($row->other_amount, 2) }}@else — @endif</td>
                            <td><strong>{{ number_format($row->amount, 2) }}</strong></td>
                            <td>{{ $row->payment_method }}</td>
                            <td>{{ $row->transaction_id }}</td>
                            <td>{{ date('d M Y', strtotime($row->donation_date)) }}</td>
                            <td>{{ $row->payment_status }}</td>
                            <td class="text-end">@include('admin.partials.donation-actions', ['row' => $row])</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ 8 + $options->count() }}" class="text-center text-muted py-4">No donations recorded for this event yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($canAddDonation)
        <!-- QUICK ENTRY -->
        <div class="console-pane" id="pane-entry">
            <div class="console-card">
                <div class="quick-entry-toggle">
                    <button type="button" class="active" id="qeToggleDevotee">Existing Devotee</button>
                    <button type="button" id="qeToggleGuest">Guest</button>
                </div>

                <div id="qeDevoteeFields">
                    <div class="qe-field mb-3 devotee-combobox-wrap">
                        <label>Search Devotee (name, email, or mobile)</label>
                        <input type="text" class="form-control" id="qeDevoteeSearch" placeholder="Start typing...">
                        <input type="hidden" id="qeDevoteeId">
                        <div class="devotee-combobox-results" id="qeDevoteeResults"></div>
                    </div>
                </div>

                <div id="qeGuestFields" style="display:none;">
                    <div class="qe-field mb-3">
                        <label>Donor Name</label>
                        <input type="text" class="form-control" id="qeGuestName">
                    </div>
                    <div class="row">
                        <div class="col-md-6 qe-field mb-3">
                            <label>Email</label>
                            <input type="email" class="form-control" id="qeGuestEmail">
                        </div>
                        <div class="col-md-6 qe-field mb-3">
                            <label>Mobile</label>
                            <input type="text" class="form-control" id="qeGuestMobile">
                        </div>
                    </div>
                </div>

                <div id="qeTiers" class="mb-3"></div>
                <div class="qe-field mb-3">
                    <label>Amount</label>
                    <input type="number" step="0.01" class="form-control" id="qeAmount" placeholder="0.00">
                </div>
                <div class="qe-field mb-3">
                    <label>Payment Method</label>
                    <select class="form-select" id="qePaymentMethod"></select>
                </div>
                <div class="qe-field mb-4">
                    <label>Transaction ID (optional)</label>
                    <input type="text" class="form-control" id="qeTransactionId">
                </div>

                <button type="button" class="btn-save-next" id="qeSaveBtn">Save &amp; Next</button>
            </div>
        </div>
        @endif

        <!-- DASHBOARD -->
        <div class="console-pane" id="pane-dashboard">
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn-refresh" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="stat-tile"><div class="label">Paid Total</div><div class="value">{{ number_format($summary['paid_total'], 2) }}</div></div></div>
                <div class="col-md-3"><div class="stat-tile"><div class="label">Pending Total</div><div class="value">{{ number_format($summary['pending_total'], 2) }}</div></div></div>
                <div class="col-md-3"><div class="stat-tile"><div class="label">Paid Donations</div><div class="value">{{ $summary['paid_count'] }}</div></div></div>
                <div class="col-md-3"><div class="stat-tile"><div class="label">Total Donations</div><div class="value">{{ $summary['donation_count'] }}</div></div></div>
            </div>
            <div class="console-card">
                <h6 class="fw-bold mb-3">By Option (Paid)</h6>
                @forelse($options as $opt)
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>{{ $opt->label }}</span>
                    <strong>{{ number_format($summary['option_totals'][$opt->id] ?? 0, 2) }}</strong>
                </div>
                @empty
                <p class="text-muted mb-0">No donation options configured for this event.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="qe-toast" id="qeToast"></div>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        // Tab switching
        document.querySelectorAll('.console-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.console-tab-btn').forEach(function (b) { b.classList.remove('active'); });
                document.querySelectorAll('.console-pane').forEach(function (p) { p.classList.remove('active'); });
                this.classList.add('active');
                document.getElementById(this.dataset.pane).classList.add('active');
            });
        });

        // Fullscreen toggle
        document.getElementById('fullscreenBtn').addEventListener('click', function () {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(function () {});
            } else {
                document.exitFullscreen();
            }
        });

        @if($canAddDonation)
        const DEVOTEES = @json($devotees);
        const EVENT_OPTIONS = @json($eventOptionsForJs);
        const ENABLED_PAYMENT_METHODS = @json($enabledPaymentMethods);
        const CSRF_TOKEN = @json(csrf_token());
        const STORE_DEVOTEE_URL = @json(route('admin.events.console.storeDevotee', $event->event_id));
        const STORE_GUEST_URL = @json(route('admin.events.console.storeGuest', $event->event_id));
        const EVENT_ID = {{ $event->event_id }};

        let qeMode = 'devotee';
        const toggleDevoteeBtn = document.getElementById('qeToggleDevotee');
        const toggleGuestBtn = document.getElementById('qeToggleGuest');
        const devoteeFields = document.getElementById('qeDevoteeFields');
        const guestFields = document.getElementById('qeGuestFields');

        toggleDevoteeBtn.addEventListener('click', function () {
            qeMode = 'devotee';
            toggleDevoteeBtn.classList.add('active');
            toggleGuestBtn.classList.remove('active');
            devoteeFields.style.display = '';
            guestFields.style.display = 'none';
        });
        toggleGuestBtn.addEventListener('click', function () {
            qeMode = 'guest';
            toggleGuestBtn.classList.add('active');
            toggleDevoteeBtn.classList.remove('active');
            guestFields.style.display = '';
            devoteeFields.style.display = 'none';
        });

        // Payment method select, respecting the configured enabled list.
        const paymentSelect = document.getElementById('qePaymentMethod');
        (ENABLED_PAYMENT_METHODS.length ? ENABLED_PAYMENT_METHODS : ['Cash']).forEach(function (m, idx) {
            const opt = document.createElement('option');
            opt.value = m === 'Bank Transfer' ? 'Bank Transfer' : m;
            opt.textContent = m;
            paymentSelect.appendChild(opt);
        });

        // Devotee search combobox — client-side filter over a pre-loaded array.
        const devoteeSearch = document.getElementById('qeDevoteeSearch');
        const devoteeResults = document.getElementById('qeDevoteeResults');
        const devoteeIdInput = document.getElementById('qeDevoteeId');

        devoteeSearch.addEventListener('input', function () {
            devoteeIdInput.value = '';
            const q = this.value.trim().toLowerCase();
            if (q.length < 2) { devoteeResults.classList.remove('show'); return; }
            const matches = DEVOTEES.filter(function (d) {
                return (d.name && d.name.toLowerCase().includes(q))
                    || (d.email && d.email.toLowerCase().includes(q))
                    || (d.mobile && d.mobile.toLowerCase().includes(q));
            }).slice(0, 15);
            if (!matches.length) { devoteeResults.classList.remove('show'); return; }
            devoteeResults.innerHTML = matches.map(function (d) {
                return '<div class="devotee-combobox-item" data-id="' + d.devotee_id + '" data-name="' + escapeHtmlQe(d.name) + '">'
                    + '<div class="fw-semibold">' + escapeHtmlQe(d.name) + '</div>'
                    + '<div class="text-muted small">' + escapeHtmlQe(d.email || '') + (d.mobile ? ' · ' + escapeHtmlQe(d.mobile) : '') + '</div></div>';
            }).join('');
            devoteeResults.classList.add('show');
        });
        devoteeResults.addEventListener('click', function (e) {
            const item = e.target.closest('.devotee-combobox-item');
            if (!item) { return; }
            devoteeIdInput.value = item.dataset.id;
            devoteeSearch.value = item.dataset.name;
            devoteeResults.classList.remove('show');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.devotee-combobox-wrap')) { devoteeResults.classList.remove('show'); }
        });

        function escapeHtmlQe(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        // Tier picker — same computation pattern as the admin Add Donation modals, laid out
        // full-width/touch-friendly here.
        const tiersContainer = document.getElementById('qeTiers');
        const amountInput = document.getElementById('qeAmount');
        let selections = [];
        // Default so a manually-typed amount (no tier checked) still submits a valid
        // purpose — matches the public donate-form's own "no tier selected" fallback.
        let purposeValue = 'Event Donation';

        if (EVENT_OPTIONS.length) {
            let html = '';
            EVENT_OPTIONS.forEach(function (opt, idx) {
                const hasAmount = opt.amount !== null;
                html += '<div class="donation-tier-option" data-idx="' + idx + '">'
                    + '<label class="d-flex align-items-center gap-2 mb-0" style="flex:1;">'
                    + '<input type="checkbox" class="tier-cb" data-idx="' + idx + '">'
                    + '<span><strong>' + escapeHtmlQe(opt.label) + '</strong><br><span class="text-muted small">'
                    + (hasAmount ? (opt.amount.toFixed(2) + (opt.allow_quantity ? ' each' : '')) : 'Any amount')
                    + '</span></span></label>'
                    + (opt.allow_quantity ? '<input type="number" min="1" value="1" class="form-control form-control-sm tier-qty" style="width:80px; display:none;">' : '')
                    + (!hasAmount ? '<input type="number" min="0" step="0.01" placeholder="Amount" class="form-control form-control-sm tier-free" style="width:120px;">' : '')
                    + '</div>';
            });
            tiersContainer.innerHTML = html;

            function recalcTiers() {
                let total = 0;
                const labels = [];
                selections = [];
                tiersContainer.querySelectorAll('.donation-tier-option').forEach(function (row) {
                    const idx = row.dataset.idx;
                    const cb = row.querySelector('.tier-cb');
                    const qtyInput = row.querySelector('.tier-qty');
                    const freeInput = row.querySelector('.tier-free');
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
                purposeValue = labels.length ? labels.join(', ').substring(0, 250) : 'Event Donation';
            }

            tiersContainer.addEventListener('change', recalcTiers);
            tiersContainer.addEventListener('input', recalcTiers);
        } else {
            purposeValue = 'Event Donation';
        }

        function showToast(message, isError) {
            const toast = document.getElementById('qeToast');
            toast.textContent = message;
            toast.classList.toggle('error', !!isError);
            toast.style.display = 'block';
            setTimeout(function () { toast.style.display = 'none'; }, 2500);
        }

        function resetQuickEntry() {
            devoteeSearch.value = '';
            devoteeIdInput.value = '';
            document.getElementById('qeGuestName').value = '';
            document.getElementById('qeGuestEmail').value = '';
            document.getElementById('qeGuestMobile').value = '';
            document.getElementById('qeTransactionId').value = '';
            amountInput.value = '';
            tiersContainer.querySelectorAll('.tier-cb').forEach(function (cb) { cb.checked = false; });
            tiersContainer.querySelectorAll('.tier-free').forEach(function (i) { i.value = ''; });
            tiersContainer.querySelectorAll('.donation-tier-option').forEach(function (row) { row.classList.remove('selected'); });
            selections = [];
            purposeValue = 'Event Donation';
        }

        document.getElementById('qeSaveBtn').addEventListener('click', function () {
            const amount = parseFloat(amountInput.value);
            if (!amount || amount <= 0) { showToast('Enter a valid amount.', true); return; }

            const btn = this;
            btn.disabled = true;

            const today = new Date().toISOString().slice(0, 10);
            const body = new URLSearchParams();
            body.set('event_id', EVENT_ID);
            body.set('amount', amount.toFixed(2));
            body.set('payment_method', paymentSelect.value);
            body.set('transaction_id', document.getElementById('qeTransactionId').value);
            body.set('selections_json', JSON.stringify(selections));
            body.set('donation_date', today);

            let url;
            if (qeMode === 'devotee') {
                if (!devoteeIdInput.value) { showToast('Search and select a devotee first.', true); btn.disabled = false; return; }
                url = STORE_DEVOTEE_URL;
                body.set('devotee_id', devoteeIdInput.value);
                body.set('payment_mode', paymentSelect.value);
                body.set('purpose', purposeValue);
            } else {
                const name = document.getElementById('qeGuestName').value.trim();
                if (!name) { showToast('Enter the donor name.', true); btn.disabled = false; return; }
                url = STORE_GUEST_URL;
                body.set('donor_name', name);
                body.set('email', document.getElementById('qeGuestEmail').value);
                body.set('mobile', document.getElementById('qeGuestMobile').value);
                body.set('purpose', purposeValue);
            }

            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    btn.disabled = false;
                    if (result.status >= 200 && result.status < 300 && result.data.success) {
                        showToast(result.data.message || 'Saved.');
                        resetQuickEntry();
                    } else {
                        showToast(result.data.message || 'Failed to save.', true);
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    showToast('Network error — please try again.', true);
                });
        });
        @endif
    </script>
</body>
</html>
