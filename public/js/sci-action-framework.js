/**
 * CBA Smart Terminal (mx51 Simple Cloud Integration) — Action Framework renderer + payment
 * flow, shared by event-pos-donation.blade.php and ticket-pos.blade.php so this polling/
 * rendering logic exists in exactly one place. Mirrors the shape of each blade's own
 * existing (inline) Linkly EFT flow — start-then-poll, same exponential backoff on
 * transient errors, same "never treat an unresolved result as a decline" philosophy — but
 * generalised for SCI's dynamic pos_instructions UI instead of Linkly's fixed 4-key layout.
 *
 * Schema this renders against (verified against mx51's own docs, not guessed):
 *   pos_instructions: { auto_actions: [...], action_form: { layout, properties, details } }
 *   action_form.layout: [ { type: 'horizontal_layout', elements: [{ label, key }, ...] } ]
 *   action_form.properties[key]: one of
 *     { type: 'text', text }
 *     { type: 'button', submit_url, action }   // exactly one of submit_url/action is set
 *     { type: 'input', name }
 *     { type: 'image', mime, encoding, data }
 *   Documented button actions: PRINT_MERCHANT_RECEIPT, PRINT_CUSTOMER_RECEIPT,
 *   TRANSACTION_COMPLETE, RETRY_TRANSACTION, SETTLEMENT_COMPLETE, RETRY_SETTLEMENT,
 *   TEST_ACTION.
 */
(function (global) {
    'use strict';

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Used only by RETRY_TRANSACTION/RETRY_SETTLEMENT (see handleBuiltinAction()) — a retry
    // must get a brand-new client_ref, or start()'s own "resume existing attempt" check on
    // the server would just hand back the same already-dead transaction instead of truly
    // submitting a new one.
    function newClientRef() {
        if (global.crypto && typeof global.crypto.randomUUID === 'function') { return global.crypto.randomUUID(); }
        return 'retry-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    }

    function printText(title, text) {
        if (!text) { return; }
        var win = window.open('', '_blank', 'width=380,height=600');
        if (!win) { return; }
        win.document.write('<html><head><title>' + escapeHtml(title) + '</title></head><body style="font-family: monospace; white-space: pre-wrap; padding: 16px; font-size: 13px;">' + escapeHtml(text) + '</body></html>');
        win.document.close();
        win.focus();
        setTimeout(function () { try { win.print(); } catch (e) {} }, 300);
    }

    /**
     * @param {Object} cfg
     * @param {string} cfg.startUrl
     * @param {string} cfg.statusUrlBase   e.g. '/admin/cba-sci/charge/status'
     * @param {string} cfg.actionUrlBase   e.g. '/admin/cba-sci/charge/action'
     * @param {string} cfg.cancelUrlBase   e.g. '/admin/cba-sci/charge/cancel'
     * @param {string} cfg.overrideUrlBase e.g. '/admin/cba-sci/charge/override'
     * @param {string} cfg.csrfToken
     * @param {?number|string} cfg.eventId  omitted entirely for a non-event-scoped kiosk (tickets)
     * @param {string} cfg.currencyCode
     * @param {string} cfg.attemptStorageKey  sessionStorage key for same-tab refresh resume
     * @param {Object} cfg.el  DOM refs: overlay, amount, statusBox, statusLine1, statusLine2,
     *                         actionContainer, cancelBtn, overrideBox, overrideYesBtn, overrideNoBtn, overrideKeepWaitingBtn
     * @param {function(Object):void} cfg.buildStartBody  (attempt) => URLSearchParams-ready plain object of extra start fields
     * @param {function(string, ?Object, Object):void} cfg.onApproved  (donationId, resultAmounts, attempt)
     * @param {function(string):void} cfg.onDeclined
     * @param {function(string):void} cfg.onUnresolved
     * @param {function():void} cfg.onLocalCancel  called when the operator cancels before anything started
     */
    function createFlow(cfg) {
        var cancelled = false;
        var transactionId = null;
        var consecutiveTransientErrors = 0;
        var formValues = {};
        var lastStatusSignature = null;
        var merchantReceipt = null;
        var customerReceipt = null;
        // The most recent REAL message mx51 actually sent (e.g. "Waiting for card") — a poll
        // with no message field must never blank this out or fall back to a generic word, or
        // a specific in-progress message gets replaced by nothing every time an intermediate
        // poll happens not to repeat it.
        var lastKnownMessage = null;
        var overrideOfferedAt = null;
        var startedAt = null;
        // mx51's own reference timeout is measured "since the last successful response", not
        // since the transaction started — a long but healthy run (steady PENDING updates)
        // must not trip the override just because it's been going a while; only actual
        // silence should. But "successful response" has to mean genuinely new content, not
        // merely an HTTP 200 — a terminal-side network failure often looks, from mx51 cloud's
        // side, like an ordinary healthy long-poll: it keeps returning 200 OK with the SAME
        // "still PENDING" message/status forever because it's also still waiting to hear from
        // the terminal. Only moves when the message or status actually changes; a genuine
        // network-level failure (the .catch() branch below) is real silence and must NOT
        // reset it either.
        var lastProgressAt = null;
        var lastProgressSignature = null;
        // mx51's own literal wording for "the terminal hasn't even acknowledged the request
        // yet" — verified against real production traffic, not guessed. Unlike a mid-
        // transaction stall (e.g. "Waiting for customer to present card", which means the
        // terminal DID accept it and a card could genuinely already be mid-swipe), this
        // specific message means no card has been touched at all, so there's no ambiguous
        // outcome to resolve. mx51's API has no separate machine-readable status for this —
        // both cases are just PENDING with a different message — so matching the literal text
        // is the only signal available; if mx51 ever rewords it, this simply stops firing
        // rather than misfiring on a message it doesn't recognise.
        var NEVER_ACCEPTED_MESSAGE = 'Waiting for terminal to accept transaction';
        // Set the moment a real Cancel Transaction call is made — per mx51's own transaction-
        // recovery guidance, a cancel gets its own (shorter) no-response deadline before
        // falling back to the manual override dialog, distinct from an ordinary transaction's.
        var cancelRequestedAt = null;
        var activeBtn = null;
        var currentAttempt = null;
        // Guards the shared modal's cancel/override buttons — this module and the caller's
        // own (Linkly) EFT flow both attach listeners to the SAME DOM elements, so each must
        // no-op on a click meant for the other. True only between this flow's own start() and
        // its own hideModal().
        var flowStarted = false;

        function qs(extra) {
            var parts = [];
            if (cfg.eventId !== undefined && cfg.eventId !== null) { parts.push('event_id=' + encodeURIComponent(cfg.eventId)); }
            if (extra) { parts.push(extra); }
            return parts.length ? '?' + parts.join('&') : '';
        }

        function saveAttempt(a) {
            try { sessionStorage.setItem(cfg.attemptStorageKey, JSON.stringify(a)); } catch (e) {}
        }
        function loadAttempt() {
            try { return JSON.parse(sessionStorage.getItem(cfg.attemptStorageKey) || 'null'); } catch (e) { return null; }
        }
        function clearAttempt() {
            try { sessionStorage.removeItem(cfg.attemptStorageKey); } catch (e) {}
        }

        function setStatus(lines, state) {
            lines = lines && lines.length ? lines : ['Please wait…'];
            var signature = state + '|' + lines.join('|');
            if (signature === lastStatusSignature) { return; }
            lastStatusSignature = signature;
            cfg.el.statusBox.classList.remove('pending', 'success', 'error');
            cfg.el.statusBox.classList.add(state);
            cfg.el.statusLine1.textContent = lines[0] || '';
            cfg.el.statusLine2.textContent = lines[1] || '';
        }

        function hideOverride() {
            cfg.el.overrideBox.hidden = true;
            cfg.el.cancelBtn.hidden = false;
        }

        function showOverride() {
            cfg.el.overrideBox.hidden = false;
            cfg.el.cancelBtn.hidden = true;
        }

        function showModal(amount) {
            cfg.el.amount.textContent = cfg.currencyCode + ' ' + Number(amount).toFixed(2);
            lastStatusSignature = null;
            lastKnownMessage = null;
            setStatus(['Starting…'], 'pending');
            cfg.el.actionContainer.innerHTML = '';
            cfg.el.actionContainer.hidden = true;
            hideOverride();
            cfg.el.cancelBtn.hidden = false;
            cfg.el.cancelBtn.textContent = 'Cancel';
            // The shared modal also serves Linkly payments, which have no mx51 branding — the
            // logo only ever shows for this (SCI) flow, and only for as long as it's active.
            if (cfg.el.mx51Logo) { cfg.el.mx51Logo.hidden = false; }
            cfg.el.overlay.classList.add('active');
        }

        function hideModal() {
            flowStarted = false;
            if (cfg.el.mx51Logo) { cfg.el.mx51Logo.hidden = true; }
            cfg.el.overlay.classList.remove('active');
            cfg.el.actionContainer.innerHTML = '';
            hideOverride();
        }

        // mx51's own docs are explicit that both the ordinary PENDING case ("immediately poll
        // for the next version") and a transaction_not_found_within_timeout 404 ("immediately
        // re-poll — this is expected behaviour, not an error") should be re-polled with no
        // added delay — their own long-poll hold is what paces the loop. A large fixed delay
        // here was compounding on top of that hold, making every step feel sluggish. Only a
        // genuine transient failure (a network error, or an unexpected server error) backs
        // off, and only then.
        function nextDelay(transient) {
            if (!transient) { consecutiveTransientErrors = 0; return 150; }
            consecutiveTransientErrors++;
            return Math.min(1200 * Math.pow(2, consecutiveTransientErrors), 30000);
        }

        function handleBuiltinAction(action) {
            if (action === 'PRINT_MERCHANT_RECEIPT') { printText('Merchant Receipt', merchantReceipt); return; }
            if (action === 'PRINT_CUSTOMER_RECEIPT') { printText('Customer Receipt', customerReceipt); return; }
            if (action === 'TRANSACTION_COMPLETE' || action === 'SETTLEMENT_COMPLETE') { hideModal(); return; }
            // mx51's own button-action table: "Re-submit the same transaction with identical
            // parameters" — the transaction this button is attached to is already dead (it's
            // the reason the button appeared), so merely polling it again (the old behaviour
            // here) just re-fetches the same final result forever. A real retry has to start
            // a brand-new transaction with the same amount/details, under a fresh client_ref
            // so the server's own resume-in-place check doesn't just hand back the dead one.
            if (action === 'RETRY_TRANSACTION' || action === 'RETRY_SETTLEMENT') {
                if (!currentAttempt || !activeBtn) { poll(); return; }
                var retryAttempt = {};
                for (var k in currentAttempt) { if (Object.prototype.hasOwnProperty.call(currentAttempt, k)) { retryAttempt[k] = currentAttempt[k]; } }
                retryAttempt.clientRef = newClientRef();
                start(activeBtn, retryAttempt);
                return;
            }
            // TEST_ACTION and anything undocumented: no-op — certification-only / not applicable here.
        }

        function submitElementAction(submitUrl) {
            Array.prototype.forEach.call(cfg.el.actionContainer.querySelectorAll('button'), function (b) { b.disabled = true; });
            fetch(cfg.actionUrlBase + '/' + encodeURIComponent(transactionId) + qs(), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ submit_url: submitUrl, form_values: formValues }),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        showToastFallback(data.message || 'The terminal did not accept that.');
                    }
                    setTimeout(poll, 300);
                })
                .catch(function () {
                    showToastFallback('Could not reach the terminal — please wait, still checking…');
                    setTimeout(poll, 300);
                });
        }

        function showToastFallback(message) {
            if (typeof cfg.onToast === 'function') { cfg.onToast(message); }
        }

        function buildElementNode(key, label, prop) {
            var type = prop && prop.type;
            if (type === 'text') {
                var div = document.createElement('div');
                div.className = 'sci-af-text';
                div.textContent = prop.text || label || '';
                return div;
            }
            if (type === 'button') {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'sci-af-btn';
                btn.textContent = label || (prop.action ? prop.action.replace(/_/g, ' ') : 'Continue');
                btn.addEventListener('click', function () {
                    if (prop.submit_url) { submitElementAction(prop.submit_url); return; }
                    if (prop.action) { handleBuiltinAction(prop.action); return; }
                });
                return btn;
            }
            if (type === 'input') {
                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'sci-af-input';
                input.placeholder = label || '';
                var name = prop.name || key;
                if (Object.prototype.hasOwnProperty.call(formValues, name)) { input.value = formValues[name]; }
                input.addEventListener('input', function () { formValues[name] = input.value; });
                return input;
            }
            if (type === 'image' && prop.data) {
                var img = document.createElement('img');
                img.className = 'sci-af-image';
                img.src = 'data:' + (prop.mime || 'image/png') + ';' + (prop.encoding || 'base64') + ',' + prop.data;
                return img;
            }
            return null;
        }

        function renderInstructions(posInstructions) {
            var form = (posInstructions && posInstructions.action_form) || {};
            var properties = form.properties || {};
            var layout = form.layout || [];
            var details = form.details || null;
            var hasContent = layout.length > 0 || (details && Object.keys(details).length > 0);

            // mx51 doesn't necessarily repeat the full Action Framework form on every single
            // poll response while the operator is still deciding (e.g. an AWAITING_POS
            // long-poll return where nothing has changed) — wiping the container on an
            // empty/missing form would yank the just-rendered Approve/Decline buttons out
            // from under the operator's cursor. Mirrors lastKnownMessage's rule: only ever
            // replace what's shown with genuinely new content, never with nothing.
            if (!hasContent && cfg.el.actionContainer.children.length) {
                return;
            }

            cfg.el.actionContainer.innerHTML = '';

            if (posInstructions) {
                layout.forEach(function (group) {
                    var row = document.createElement('div');
                    row.className = 'sci-af-row';
                    (group.elements || []).forEach(function (ref) {
                        var prop = properties[ref.key] || {};
                        var node = buildElementNode(ref.key, ref.label, prop);
                        if (node) { row.appendChild(node); }
                    });
                    if (row.children.length) { cfg.el.actionContainer.appendChild(row); }
                });

                if (!layout.length && details && Object.keys(details).length) {
                    var box = document.createElement('div');
                    box.className = 'sci-af-details';
                    Object.keys(details).forEach(function (k) {
                        var line = document.createElement('div');
                        line.textContent = k + ': ' + details[k];
                        box.appendChild(line);
                    });
                    cfg.el.actionContainer.appendChild(box);
                }
            }

            cfg.el.actionContainer.hidden = cfg.el.actionContainer.children.length === 0;
            // Avoid two "cancel"-ish affordances competing for attention at once — once mx51's
            // own Action Framework is presenting real buttons/inputs for the operator to use,
            // the generic Cancel Payment button steps aside. The override dialog (once shown)
            // owns cancelBtn's spot instead — never fight it back into view over that.
            if (!overrideOfferedAt) {
                cfg.el.cancelBtn.hidden = !cfg.el.actionContainer.hidden;
            }
        }

        // mx51's certification requirements are explicit: "Approved/Declined message and
        // response code given by the Action Framework are displayed on the POS" — that's
        // mx51's own data.message (their reference shows it as e.g. "(000) APPROVED"), not a
        // generic string this app makes up. It's shown as the primary line; the generic label
        // is only a fallback for the rare case mx51 didn't send one.
        // mx51's certification checklist requires the Print Merchant/Customer Receipt and
        // Done buttons to actually appear and be usable after a finalised result — closing
        // the modal on a fixed timer regardless would yank them away before the operator
        // could ever click Print. renderInstructions() already ran for this same response by
        // the time these are called, so actionContainer's hidden state tells us whether mx51
        // sent anything to wait for; only auto-close when it didn't.
        function finishSuccess(donationId, resultAmounts, resultMessage) {
            clearAttempt();
            setStatus([resultMessage || 'PAYMENT APPROVED', 'Saving…'], 'success');
            if (activeBtn) { activeBtn.disabled = false; }
            cfg.onApproved(donationId, resultAmounts, currentAttempt);
            if (cfg.el.actionContainer.hidden) { setTimeout(hideModal, 1200); }
        }

        function finishDeclined(message, resultStatus) {
            clearAttempt();
            var fallback = resultStatus === 'CANCELLED' ? 'PAYMENT CANCELLED' : 'PAYMENT DECLINED';
            var subline = resultStatus === 'CANCELLED' ? 'Transaction cancelled' : 'Transaction declined';
            setStatus([message || fallback, subline], 'error');
            if (activeBtn) { activeBtn.disabled = false; }
            cfg.onDeclined(message);
            if (cfg.el.actionContainer.hidden) { setTimeout(hideModal, 1800); }
        }

        function finishUnresolved(message) {
            // Unlike finishSuccess()/finishDeclined(), this was never clearing the saved
            // attempt — so once a transaction ended up here (including via the override
            // dialog's own "No / Unresolved" outcome), the SAME stale clientRef stayed in
            // sessionStorage and resumeFromStorage() kept resuming it on every future page
            // load, indefinitely, regardless of login/logout. The server itself now correctly
            // treats this outcome as final (see CbaSciController::override()), but the client
            // has to stop trying to resume the same dead attempt too.
            clearAttempt();
            setStatus(['RESULT UNKNOWN', message || 'Check the terminal before retrying'], 'error');
            setTimeout(hideModal, 2200);
            if (activeBtn) { activeBtn.disabled = false; }
            cfg.onUnresolved(message);
        }

        function poll() {
            if (cancelled || !transactionId) { return; }

            // mx51's own documented recovery flow calls for a shorter deadline once a cancel
            // has been requested than for an ordinary transaction — their reference POS
            // (Espresso) uses 1 minute since the last successful response / 20 seconds after
            // a cancel, explicitly as an indicative starting point rather than a mandated
            // value, which is what these mirror.
            // Cancellation uses a fixed deadline from the moment cancel was requested (mx51's
            // wording: "no FINALISED response... within a defined period", not reset by
            // interim chatter); the ordinary case resets whenever the terminal actually reports
            // something new, per "no response for a defined period" — a genuinely progressing
            // PENDING transaction never trips it, but one stuck repeating the same message
            // forever (e.g. the terminal itself has gone offline) now correctly does.
            var overrideBaseline = cancelRequestedAt || lastProgressAt || startedAt;
            var overrideDeadline = cancelRequestedAt ? 20000 : 60000;
            if (overrideBaseline && Date.now() - overrideBaseline > overrideDeadline && !overrideOfferedAt) {
                overrideOfferedAt = Date.now();
                setStatus(['No response from the terminal yet', 'Confirm the outcome below, or keep waiting'], 'error');
                showOverride();
            }

            // If the terminal has never even acknowledged this transaction, there's nothing
            // ambiguous to resolve — no card was ever touched, so unlike a mid-transaction
            // stall this can fail fast and clean (like a real network failure) instead of
            // making the operator wait through the slower "did it go through?" override flow.
            if (lastKnownMessage === NEVER_ACCEPTED_MESSAGE
                && !cancelRequestedAt && Date.now() - startedAt > 20000) {
                clearAttempt();
                setStatus(['TERMINAL NOT REACHABLE', 'Check the network/terminal connection and try again'], 'error');
                setTimeout(hideModal, 2200);
                if (activeBtn) { activeBtn.disabled = false; }
                cfg.onDeclined('Terminal did not respond — check network and terminal connections.');
                return;
            }

            fetch(cfg.statusUrlBase + '/' + encodeURIComponent(transactionId) + qs(), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (cancelled) { return; }

                    // Real progress only — a poll that repeats the exact same status/message
                    // the terminal already reported isn't evidence anything is still moving,
                    // even though the HTTP call itself succeeded.
                    var progressSignature = (data.status || '') + '|' + (data.message || '');
                    if (progressSignature !== lastProgressSignature) {
                        lastProgressSignature = progressSignature;
                        lastProgressAt = Date.now();
                    }

                    merchantReceipt = data.merchant_receipt || merchantReceipt;
                    customerReceipt = data.customer_receipt || customerReceipt;

                    var pos = data.pos_instructions || null;
                    var autoActions = (pos && pos.auto_actions) || [];
                    autoActions.forEach(function (a) { handleBuiltinAction(typeof a === 'string' ? a : (a && a.action)); });

                    if (data.status === 'DEVICE_NOT_CONNECTED') {
                        clearAttempt();
                        setStatus(['TERMINAL NOT CONNECTED', data.message || 'Check the network/terminal and try again'], 'error');
                        setTimeout(hideModal, 2200);
                        if (activeBtn) { activeBtn.disabled = false; }
                        cfg.onDeclined(data.message || 'Terminal not connected.');
                        return;
                    }

                    // mx51's own reference UI leads with the specific message ("Waiting for
                    // customer to present card"), not the raw status word — and a poll that
                    // doesn't repeat the message (mx51 doesn't guarantee one on every single
                    // response, only on real transitions) must never blank out the last real
                    // one or fall back to a generic placeholder while nothing has actually
                    // changed. Only ever replace it with a genuinely new message.
                    if (data.message) { lastKnownMessage = data.message; }
                    setStatus([lastKnownMessage || 'Please wait…', data.status || ''], 'pending');
                    renderInstructions(pos);

                    if (!data.done) {
                        setTimeout(poll, nextDelay(!!data.transient_error));
                        return;
                    }

                    // AWAITING_POS is NOT a final result — it means the terminal needs the
                    // operator to act on the Action Framework form just rendered above (e.g.
                    // Approve/Decline Signature). Treating it as "done" here previously fell
                    // through to finishUnresolved() and closed the modal before the operator
                    // could ever click anything. mx51's own docs say to stop the normal
                    // polling cadence in this state (the operator's click resumes it promptly
                    // via submitElementAction()) — this much slower background check exists
                    // only so an abandoned/buttonless AWAITING_POS still reaches the override
                    // safety net above instead of hanging forever.
                    if (data.status === 'AWAITING_POS') {
                        setTimeout(poll, 4000);
                        return;
                    }

                    if (data.donation_id) {
                        finishSuccess(data.donation_id, null, data.message);
                        return;
                    }
                    if (data.result_financial_status === 'DECLINED' || data.result_financial_status === 'CANCELLED') {
                        finishDeclined(data.message, data.result_financial_status);
                        return;
                    }
                    // APPROVED-but-no-record (e.g. the donor name was missing, or a ticket
                    // cart was already consumed by an earlier poll) is NOT the same as a
                    // genuinely unknown result — the card WAS charged, so staff must be told
                    // that plainly rather than being left thinking nothing happened.
                    if (data.result_financial_status === 'APPROVED') {
                        finishUnresolved('Card was charged (APPROVED) but the record could not be saved automatically — note the amount and record it manually. ' + (data.message || ''));
                        return;
                    }
                    finishUnresolved(data.message);
                })
                .catch(function () {
                    if (cancelled) { return; }
                    setTimeout(poll, nextDelay(true));
                });
        }

        function start(btn, attempt) {
            flowStarted = true;
            cancelled = false;
            transactionId = null;
            consecutiveTransientErrors = 0;
            overrideOfferedAt = null;
            lastProgressAt = null;
            lastProgressSignature = null;
            cancelRequestedAt = null;
            formValues = {};
            cfg.el.cancelBtn.disabled = false;
            activeBtn = btn;
            currentAttempt = attempt;
            saveAttempt(attempt);
            showModal(attempt.amount);

            var body = new URLSearchParams();
            if (cfg.eventId !== undefined && cfg.eventId !== null) { body.set('event_id', cfg.eventId); }
            body.set('amount', Number(attempt.amount).toFixed(2));
            body.set('client_ref', attempt.clientRef);
            body.set('donor_name', attempt.name || '');
            body.set('email', attempt.email || '');
            body.set('mobile', attempt.mobile || '');
            if (attempt.terminalId) { body.set('terminal_id', attempt.terminalId); }
            var extra = cfg.buildStartBody ? (cfg.buildStartBody(attempt) || {}) : {};
            Object.keys(extra).forEach(function (k) { body.set(k, extra[k]); });

            fetch(cfg.startUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    if (!(result.status >= 200 && result.status < 300 && result.data.success)) {
                        clearAttempt();
                        btn.disabled = false;
                        hideModal();
                        showToastFallback(result.data.message || 'Could not start the terminal transaction.');
                        return;
                    }
                    transactionId = result.data.transaction_id;
                    startedAt = Date.now();
                    lastProgressAt = startedAt;
                    lastProgressSignature = (result.data.status || '') + '|' + (result.data.message || '');
                    // mx51's own create response carries a real message too (their docs'
                    // example: "Waiting for terminal to accept transaction") — show it now
                    // rather than leaving the generic "Starting…" placeholder up until the
                    // first poll response comes back.
                    if (result.data.message) { lastKnownMessage = result.data.message; }
                    setStatus([lastKnownMessage || 'Please wait…', result.data.status || ''], 'pending');
                    renderInstructions(result.data.pos_instructions || null);
                    poll();
                })
                .catch(function () {
                    btn.disabled = false;
                    hideModal();
                    showToastFallback('Could not reach the mx51 Cloud service — please try again.');
                });
        }

        cfg.el.cancelBtn.addEventListener('click', function () {
            if (!flowStarted) { return; }
            if (!transactionId) {
                cancelled = true;
                clearAttempt();
                hideModal();
                if (activeBtn) { activeBtn.disabled = false; }
                cfg.onLocalCancel();
                return;
            }
            if (cancelRequestedAt) { return; }
            // A real Cancel Transaction call — this alone doesn't resolve the outcome (mx51
            // may not be able to stop a card that's already charging), so polling keeps
            // running exactly as it does for any other transaction and the eventual
            // FINALISED result (CANCELLED, or APPROVED if the cancel came too late) is what
            // actually closes the flow. The override dialog is the fallback if that never
            // arrives — see poll()'s shorter cancel-specific deadline.
            cancelRequestedAt = Date.now();
            cfg.el.cancelBtn.disabled = true;
            setStatus(['Cancelling…', 'Waiting for the terminal to confirm'], 'pending');
            fetch(cfg.cancelUrlBase + '/' + encodeURIComponent(transactionId) + qs(), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (cancelled) { return; }
                    if (!data.success) { showToastFallback(data.message || 'Could not cancel — still waiting for the terminal.'); }
                })
                .catch(function () {
                    if (cancelled) { return; }
                    showToastFallback('Could not reach the mx51 Cloud service to cancel — still waiting for the terminal.');
                });
        });

        cfg.el.overrideKeepWaitingBtn.addEventListener('click', function () {
            if (!flowStarted) { return; }
            hideOverride();
            cfg.el.cancelBtn.disabled = false;
            // Re-arm the safety net for another full interval rather than leaving it
            // permanently spent — without this, choosing "keep waiting" once meant the
            // override could never reappear again for the rest of this transaction, no matter
            // how much longer the terminal stayed unresponsive or whether Cancel was pressed
            // afterward. "Keep waiting" should mean exactly that: wait one more interval, then
            // ask again if there's still nothing.
            overrideOfferedAt = null;
            lastProgressAt = Date.now();
            if (cancelRequestedAt) { cancelRequestedAt = Date.now(); }
        });

        function submitOverride(outcome) {
            cfg.el.overrideYesBtn.disabled = true;
            cfg.el.overrideNoBtn.disabled = true;
            fetch(cfg.overrideUrlBase + '/' + encodeURIComponent(transactionId) + qs(), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'outcome=' + encodeURIComponent(outcome),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    cfg.el.overrideYesBtn.disabled = false;
                    cfg.el.overrideNoBtn.disabled = false;
                    if (!data.success) {
                        showToastFallback(data.message || 'Could not record the outcome — please try again.');
                        return;
                    }
                    // The override is a final, local decision — but poll() was still running
                    // on its own independent setTimeout chain, and mx51 itself may genuinely
                    // never resolve this transaction. Left running, the very next poll would
                    // fetch mx51's still-unresolved answer, overwrite this outcome (both the
                    // display AND — server-side, since poll() re-queries mx51 unconditionally
                    // — the just-finalised database row), and pop the "Approved" message right
                    // back to the ambiguous waiting state. This must stop it for good.
                    cancelled = true;
                    // The override endpoint returns a decision, not a fresh Action Framework
                    // payload — whatever buttons happened to still be in actionContainer from
                    // before (often stale, sometimes nothing at all) has no bearing on this
                    // outcome. finishSuccess()/finishDeclined()/finishUnresolved() only
                    // auto-close the modal when actionContainer is empty, so leaving old
                    // content sitting there was blocking the close indefinitely — "Approved /
                    // Saving…" would show, then just sit there forever. renderInstructions()
                    // itself now deliberately refuses to clear real content with nothing (see
                    // its own guard), so that's the wrong tool here — this has to force it.
                    cfg.el.actionContainer.innerHTML = '';
                    cfg.el.actionContainer.hidden = true;
                    if (data.donation_id) { finishSuccess(data.donation_id, null); return; }
                    if (data.result_financial_status === 'APPROVED') { finishSuccess(data.donation_id, null); return; }
                    if (data.result_financial_status && data.result_financial_status !== 'UNKNOWN') { finishDeclined(data.message, data.result_financial_status); return; }
                    finishUnresolved('Marked unresolved — please verify against the terminal/bank statement.');
                })
                .catch(function () {
                    cfg.el.overrideYesBtn.disabled = false;
                    cfg.el.overrideNoBtn.disabled = false;
                    showToastFallback('Network error recording the outcome — please try again.');
                });
        }

        cfg.el.overrideYesBtn.addEventListener('click', function () { if (flowStarted) { submitOverride('approved'); } });
        cfg.el.overrideNoBtn.addEventListener('click', function () { if (flowStarted) { submitOverride('unresolved'); } });

        return {
            start: start,
            isActive: function () { return !!transactionId; },
            resumeFromStorage: function (btn) {
                var attempt = loadAttempt();
                if (!attempt) { return false; }
                btn.disabled = true;
                start(btn, attempt);
                return true;
            },
        };
    }

    global.SciActionFramework = { createFlow: createFlow };
})(window);
