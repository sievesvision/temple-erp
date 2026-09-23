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
        var overrideOfferedAt = null;
        var startedAt = null;
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
            setStatus(['Starting…'], 'pending');
            cfg.el.actionContainer.innerHTML = '';
            cfg.el.actionContainer.hidden = true;
            hideOverride();
            cfg.el.cancelBtn.textContent = 'Cancel';
            cfg.el.overlay.classList.add('active');
        }

        function hideModal() {
            flowStarted = false;
            cfg.el.overlay.classList.remove('active');
            cfg.el.actionContainer.innerHTML = '';
            hideOverride();
        }

        function nextDelay(transient) {
            if (!transient) { consecutiveTransientErrors = 0; return 1200; }
            consecutiveTransientErrors++;
            return Math.min(1200 * Math.pow(2, consecutiveTransientErrors), 30000);
        }

        function handleBuiltinAction(action) {
            if (action === 'PRINT_MERCHANT_RECEIPT') { printText('Merchant Receipt', merchantReceipt); return; }
            if (action === 'PRINT_CUSTOMER_RECEIPT') { printText('Customer Receipt', customerReceipt); return; }
            if (action === 'TRANSACTION_COMPLETE' || action === 'SETTLEMENT_COMPLETE') { hideModal(); return; }
            if (action === 'RETRY_TRANSACTION' || action === 'RETRY_SETTLEMENT') { poll(); return; }
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
            cfg.el.actionContainer.innerHTML = '';
            if (!posInstructions) { cfg.el.actionContainer.hidden = true; return; }

            var form = posInstructions.action_form || {};
            var properties = form.properties || {};
            var layout = form.layout || [];
            var details = form.details || null;

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

            cfg.el.actionContainer.hidden = cfg.el.actionContainer.children.length === 0;
        }

        function finishSuccess(donationId, resultAmounts) {
            clearAttempt();
            setStatus(['PAYMENT APPROVED', 'Saving…'], 'success');
            setTimeout(hideModal, 1200);
            if (activeBtn) { activeBtn.disabled = false; }
            cfg.onApproved(donationId, resultAmounts, currentAttempt);
        }

        function finishDeclined(message) {
            clearAttempt();
            setStatus(['PAYMENT DECLINED', message || ''], 'error');
            setTimeout(hideModal, 1800);
            if (activeBtn) { activeBtn.disabled = false; }
            cfg.onDeclined(message);
        }

        function finishUnresolved(message) {
            setStatus(['RESULT UNKNOWN', message || 'Check the terminal before retrying'], 'error');
            setTimeout(hideModal, 2200);
            if (activeBtn) { activeBtn.disabled = false; }
            cfg.onUnresolved(message);
        }

        function poll() {
            if (cancelled || !transactionId) { return; }

            // Same 3-minute local guard as the Linkly flow — past this, offer the manual
            // override rather than continuing to error out indefinitely, since (unlike
            // Linkly) SCI has no cancel API to fall back on if the card may still charge.
            if (startedAt && Date.now() - startedAt > 180000 && !overrideOfferedAt) {
                overrideOfferedAt = Date.now();
                setStatus(['No response from the terminal yet', 'Confirm the outcome below, or keep waiting'], 'error');
                showOverride();
            }

            fetch(cfg.statusUrlBase + '/' + encodeURIComponent(transactionId) + qs(), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (cancelled) { return; }

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

                    if (data.message) { setStatus([data.status || 'Processing', data.message], 'pending'); }
                    renderInstructions(pos);

                    if (!data.done) {
                        setTimeout(poll, nextDelay(!!data.transient_error));
                        return;
                    }

                    if (data.donation_id) {
                        finishSuccess(data.donation_id, null);
                        return;
                    }
                    if (data.result_financial_status === 'DECLINED' || data.result_financial_status === 'CANCELLED') {
                        finishDeclined(data.message);
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
            formValues = {};
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
                    renderInstructions(result.data.pos_instructions || null);
                    poll();
                })
                .catch(function () {
                    btn.disabled = false;
                    hideModal();
                    showToastFallback('Could not reach the CBA Smart Terminal service — please try again.');
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
            // No cancel API exists for SCI (confirmed in mx51's docs — the recovery flow is
            // the only mechanism) — asking "did it go through?" is the only honest option
            // once a transaction has actually started on the terminal.
            setStatus(['Waiting for the terminal…', 'Confirm the outcome below if it\'s stuck'], 'pending');
            showOverride();
        });

        cfg.el.overrideKeepWaitingBtn.addEventListener('click', function () {
            if (!flowStarted) { return; }
            hideOverride();
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
                    if (data.donation_id) { finishSuccess(data.donation_id, null); return; }
                    if (data.result_financial_status === 'APPROVED') { finishSuccess(data.donation_id, null); return; }
                    if (data.result_financial_status && data.result_financial_status !== 'UNKNOWN') { finishDeclined(data.message); return; }
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
