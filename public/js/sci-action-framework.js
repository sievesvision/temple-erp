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
 *   TEST_ACTION. A button carrying `submit_url` instead of `action` POSTs a JSON payload
 *   (every input's current value, keyed by its own `name`) to that URL and re-renders the
 *   action_form the response carries back — see submitElementAction(). TEST_ACTION has no
 *   submit_url; per mx51's own certification guidance it should "call an internal function",
 *   for which displaying the same JSON payload a Submit to API button would have sent is an
 *   acceptable stand-in — see handleBuiltinAction()'s TEST_ACTION branch.
 *
 * mx51's own branding (e.g. during a signature step) is never injected locally — it arrives
 * as an ordinary `type: 'image'` element in the layout mx51 itself sends, rendered exactly
 * like any other image. An earlier version of this renderer guessed at showing a locally
 * bundled logo whenever a text element's content mentioned "signature", which duplicated
 * mx51's own supplied image and cluttered the modal; there is no special-cased logo handling
 * here anymore.
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

    // Same 80mm thermal-roll formatting as ticket-print.blade.php's own stub (@page sizing +
    // monospace + no margin) so this prints at actual receipt width instead of a shrunk-down
    // A4 page — the receipt text itself comes verbatim from mx51 (already laid out/aligned on
    // its end), this only supplies the paper dimensions it was written to fit.
    function printText(title, text) {
        if (!text) { return; }
        var win = window.open('', '_blank', 'width=380,height=600');
        if (!win) { return; }
        win.document.write(
            '<html><head><title>' + escapeHtml(title) + '</title>' +
            '<style>' +
            '@page { size: 80mm auto; margin: 0; }' +
            'body { margin: 0; padding: 4mm 3mm; font-family: "Consolas", "Courier New", monospace; font-size: 12px; white-space: pre-wrap; word-break: break-word; }' +
            '</style></head><body>' + escapeHtml(text) + '</body></html>'
        );
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
     * @param {?number|string} cfg.eventId  omitted entirely for a non-event-scoped POS page (tickets)
     * @param {string} cfg.currencyCode
     * @param {string} cfg.attemptStorageKey  sessionStorage key for same-tab refresh resume
     * @param {Object} cfg.el  DOM refs: overlay, amount, statusBox, statusLine1, statusLine2,
     *                         actionContainer, cancelBtn, overrideBox, overrideYesBtn, overrideNoBtn, overrideKeepWaitingBtn
     * @param {function(Object):void} cfg.buildStartBody  (attempt) => URLSearchParams-ready plain object of extra start fields
     * @param {function(string, ?Object, Object):void} cfg.onApproved  (donationId, resultAmounts, attempt)
     * @param {function(string):void} cfg.onDeclined
     * @param {function(string):void} cfg.onUnresolved
     * @param {function():void} cfg.onLocalCancel  called when the operator cancels before anything started
     * @param {function():void} [cfg.onModalClosed]  called whenever hideModal() actually runs —
     *        after a finalised result with nothing left to show, after the operator uses a
     *        Print/Done Action Framework button to finish up, or after an explicit Cancel.
     *        A caller that needs to refresh its own page once the operator is truly done
     *        (e.g. a refund updating a transactions table) should do that here, never on a
     *        fixed timer from onApproved/onDeclined — those fire the moment a result is known,
     *        which can be well before mx51's own certification-required Print Merchant/
     *        Customer Receipt buttons have actually been shown or used.
     */
    function createFlow(cfg) {
        var cancelled = false;
        var transactionId = null;
        var consecutiveTransientErrors = 0;
        var formValues = {};
        var lastStatusSignature = null;
        var merchantReceipt = null;
        var customerReceipt = null;
        // Set only when PRINT_MERCHANT_RECEIPT fires via mx51's own auto_actions (i.e. the
        // "Auto-print signature receipt from POS" setting), never for the same action clicked
        // manually off an Action Framework button — see handleBuiltinAction()'s isAuto param.
        var merchantReceiptAutoPrinted = false;
        // The most recent REAL message mx51 actually sent (e.g. "Waiting for card") — a poll
        // with no message field must never blank this out or fall back to a generic word, or
        // a specific in-progress message gets replaced by nothing every time an intermediate
        // poll happens not to repeat it.
        var lastKnownMessage = null;
        var overrideOfferedAt = null;
        var overrideSubmitted = false;
        var startedAt = null;
        // mx51's own reference timeout is measured "since the last successful response", not
        // since the transaction started — a long but healthy run (steady PENDING updates, even
        // repeating the exact same message while the customer takes their time on the terminal)
        // must not trip the override just because it's been going a while; only actual silence
        // should. Per mx51's own documented failure modes, a terminal that's genuinely gone
        // offline gets its own explicit SCI API error (DEVICE_NOT_CONNECTED, handled separately
        // below as an immediate error, never via override) rather than the API silently
        // repeating stale status — so any response at all from the SCI API, unchanged or not,
        // is real proof of life. Only a genuine network-level failure reaching the SCI API
        // itself (the .catch() branch below, which deliberately never touches this) is the
        // silence the override exists to catch — exactly mx51's "POS loses its own network
        // connection" scenario, where the terminal may have already finished the transaction
        // with no way for the POS to find out except asking the operator.
        var lastProgressAt = null;
        // How long a single /status call is given to answer before poll() gives up on it and
        // issues a fresh one — see poll()'s own fetch call. Our OWN backend's call to mx51
        // (CbaSciController::poll() -> CbaSciService::pollTransaction()) is itself capped at a
        // dedicated 15s timeout — deliberately short so even a poll in flight at the exact
        // moment Cancel is clicked still resolves well inside the cancel override's fixed 20s
        // window (see updateWaitingStatus()'s docblock on why that deadline isn't reset by
        // interim responses). This has to sit safely above that 15s backend ceiling, or it
        // aborts every single healthy call before our own backend ever gets the chance to
        // answer — which is exactly what a too-short value here previously did, permanently
        // preventing the clock from ever resetting even on a perfectly healthy transaction.
        var POLL_ABORT_MS = 18000;
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
        // The header used to say "Payment Declined" for every non-approved outcome, including
        // ones that have nothing to do with the card being declined (the operator cancelling,
        // the terminal losing its connection, giving up on an unreachable terminal) — all
        // driven by one hardcoded string instead of what actually happened. finishDeclined()
        // looks the real resultStatus up here; anything not listed (an actual card decline)
        // falls back to "Payment Declined", which is the one case that word is accurate for.
        var DECLINE_HEADER_LABELS = {
            CANCELLED: 'Transaction Cancelled',
            DEVICE_NOT_CONNECTED: 'Terminal Not Connected',
            NOT_REACHABLE: 'Terminal Not Reachable',
        };
        // Set the moment a real Cancel Transaction call is made — per mx51's own transaction-
        // recovery guidance, a cancel gets its own (shorter) no-response deadline before
        // falling back to the manual override dialog, distinct from an ordinary transaction's.
        var cancelRequestedAt = null;
        // Checks the override deadline on its own clock, independent of poll()'s own schedule —
        // mx51's /status endpoint is a long-poll that can hold the connection well past 20s
        // before responding, so a deadline check that only ran inside poll()'s response handler
        // (the original approach) could land 60s+ after Cancel instead of the intended 20s,
        // simply because that's how long the already-in-flight request happened to take.
        var overrideCheckInterval = null;
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
            // submitOverride()'s success path never restores these — it moves straight on to
            // finishSuccess()/finishDeclined()/finishUnresolved(), with no reason to touch an
            // override box that's about to be hidden anyway. But the override box and its
            // Yes/No question don't just disappear with the old transaction; they get reused
            // for the next one, and without this reset a LATER transaction that also reaches
            // the override (even immediately, if something else — like the startedAt race
            // fixed alongside this — bypasses the usual wait first) would show the one that
            // just finished's leftover "Recording the transaction…" instead of ever asking
            // Yes/No at all.
            if (cfg.el.overrideQuestion) { cfg.el.overrideQuestion.hidden = false; }
            if (cfg.el.overrideSaving) { cfg.el.overrideSaving.hidden = true; }
        }

        function showOverride() {
            cfg.el.overrideBox.hidden = false;
            cfg.el.cancelBtn.hidden = true;
        }

        function showModal(amount) {
            cfg.el.amount.textContent = cfg.currencyCode + ' ' + Number(amount).toFixed(2);
            lastStatusSignature = null;
            lastKnownMessage = null;
            cfg.el.statusBox.classList.remove('status-cancelled');
            setStatus(['Starting…'], 'pending');
            if (cfg.el.countdown) { cfg.el.countdown.textContent = ''; }
            merchantReceiptAutoPrinted = false;
            if (cfg.el.printNotice) { cfg.el.printNotice.hidden = true; }
            cfg.el.actionContainer.innerHTML = '';
            cfg.el.actionContainer.hidden = true;
            hideOverride();
            cfg.el.cancelBtn.hidden = false;
            cfg.el.cancelBtn.textContent = 'Cancel';
            cfg.el.overlay.classList.add('active');
        }

        // A final outcome (approved, declined, unresolved) means there's nothing left to ask
        // "did it go through?" about — called from finishSuccess()/finishDeclined()/
        // finishUnresolved() the instant each fires, not just from hideModal(). Those three
        // don't always close the modal right away (mx51's certification-required Print/Done/
        // Retry buttons can sit on screen indefinitely, waiting on the operator), and the gap
        // between "we already know the outcome" and "the modal actually closes" was exactly
        // when this 1-second ticker kept running — with cancelRequestedAt still set from an
        // earlier Cancel click, it could cross the 20s mark and pop the override dialog on top
        // of an already-finalised result the operator was simply looking at.
        function stopOverrideWatch() {
            if (overrideCheckInterval) { clearInterval(overrideCheckInterval); overrideCheckInterval = null; }
        }

        function hideModal() {
            flowStarted = false;
            cfg.el.overlay.classList.remove('active');
            cfg.el.actionContainer.innerHTML = '';
            hideOverride();
            stopOverrideWatch();
            if (typeof cfg.onModalClosed === 'function') { cfg.onModalClosed(); }
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

        function handleBuiltinAction(action, isAuto) {
            if (action === 'PRINT_MERCHANT_RECEIPT') {
                printText('Merchant Receipt', merchantReceipt);
                if (isAuto) { merchantReceiptAutoPrinted = true; }
                return;
            }
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
            // TEST_ACTION has no submit_url — mx51's own cert guidance is that clicking it
            // should "call an internal function", and that displaying the JSON payload a real
            // Submit to API button would have sent (same input values, keyed by name) is an
            // acceptable stand-in for that function during certification. A popup rather than
            // appending to the form itself, since appending left it looking like a permanent,
            // growing part of the transaction UI instead of a one-off internal check.
            if (action === 'TEST_ACTION') {
                alert('TEST_ACTION — internal function payload:\n\n' + JSON.stringify(formValues, null, 2));
                return;
            }
        }

        // mx51's own documented contract for a "Submit to API" button: collect every input's
        // current value keyed by its own `name`, POST that JSON payload to the button's
        // submit_url, and use the API's response — which carries a fresh action_form — to
        // replace whatever's currently shown. The signed request itself has to happen server-
        // side (mx51's SCI auth needs the terminal's private signing secret, never exposed to
        // the browser), so this hands submit_url + formValues to our own backend and renders
        // whatever pos_instructions it hands back, rather than waiting on the next incidental
        // transaction-status poll — which has no guaranteed connection to this submission and,
        // for some dynamic submit_urls, may not reflect it at all.
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
                        setTimeout(poll, 300);
                        return;
                    }
                    if (data.message) { lastKnownMessage = data.message; }
                    if (data.pos_instructions && !overrideOfferedAt) {
                        updateWaitingStatus();
                        renderInstructions(data.pos_instructions);
                    }
                    // This response alone never carries a final result_financial_status/
                    // donation_id, only an interim action_form — the existing poll() loop is
                    // still what actually resolves the transaction once mx51 finalises it.
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
                var textValue = prop.text || '';
                // mx51's own reference rendering prefixes a labelled text element with its
                // label ("Text Label 1: Text 1") — a bare label with no text (or vice versa)
                // still reads fine shown alone.
                div.textContent = (label && textValue) ? (label + ': ' + textValue) : (textValue || label || '');
                return div;
            }
            if (type === 'button') {
                var btnLabel = label || (prop.action ? prop.action.replace(/_/g, ' ') : 'Continue');
                // Our own Cancel Payment button already covers this, correctly wired to the
                // shortened 20s post-cancel override deadline — mx51's own button has no
                // equivalent for that (its submit_url/action here is whatever mx51 sent, not
                // necessarily the real Cancel Transaction call), so rendering it too just showed
                // a second, differently-behaved "Cancel" alongside the one that actually works.
                if (/^cancel\b/i.test(btnLabel.trim())) { return null; }
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'sci-af-btn';
                btn.textContent = btnLabel;
                btn.addEventListener('click', function () {
                    if (prop.submit_url) { submitElementAction(prop.submit_url); return; }
                    if (prop.action) { handleBuiltinAction(prop.action); return; }
                });
                return btn;
            }
            if (type === 'input') {
                // A fading placeholder isn't good enough here — mx51's certification payload
                // uses the label to identify what each field actually is, and that identity
                // disappearing the moment the operator starts typing makes the form ambiguous.
                // The label now sits beside the input permanently instead. mx51's own live test
                // data already includes the technical field name inside the label text itself
                // (e.g. "Input 1 (input_name_1_...)") — rendering `label` as-is rather than
                // re-appending prop.name avoids showing that name twice.
                var wrap = document.createElement('div');
                wrap.className = 'sci-af-input-wrap';
                var name = prop.name || key;
                var labelSpan = document.createElement('span');
                labelSpan.className = 'sci-af-input-label';
                labelSpan.textContent = label || prop.name || '';
                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'sci-af-input';
                // mx51's own documented contract is "a JSON payload using each input's name
                // property as the key" — every rendered input must appear in formValues even
                // if the operator never touches it, not just whichever ones got typed into.
                if (!Object.prototype.hasOwnProperty.call(formValues, name)) { formValues[name] = ''; }
                input.value = formValues[name];
                input.addEventListener('input', function () { formValues[name] = input.value; });
                wrap.appendChild(labelSpan);
                wrap.appendChild(input);
                return wrap;
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
            // A fresh action_form is a fresh form, full stop — an input left over from the
            // PREVIOUS form (e.g. the same `name` reused across a multi-step flow) must never
            // pre-fill the new one with whatever the operator typed before. buildElementNode()
            // below only seeds a key the first time it sees it ("if (!hasOwnProperty) ..."),
            // so without this reset a stale value survives untouched across every re-render.
            formValues = {};

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

                // mx51's certification review (SCIREC03-adjacent feedback) was explicit: the
                // transaction details (Pairing ID, Transaction ID, TID, Transaction Version)
                // must stay visible both while a transaction is in progress AND once it's
                // finalised — previously this only rendered when there were NO buttons at all,
                // which in practice meant it almost never showed, since a real response nearly
                // always carries at least one button alongside the details. Rendered as one
                // flowing, comma-joined line (mx51's own reference layout), not stacked rows.
                if (details && Object.keys(details).length) {
                    var box = document.createElement('div');
                    box.className = 'sci-af-details';
                    box.textContent = Object.keys(details).map(function (k) { return k + ': ' + details[k]; }).join(', ');
                    cfg.el.actionContainer.appendChild(box);
                }
            }

            cfg.el.actionContainer.hidden = cfg.el.actionContainer.children.length === 0;
            // Cancel Payment used to step aside whenever mx51's own Action Framework had any
            // content at all — but mx51 doesn't always include an equivalent cancel affordance
            // of its own (an interim "enter tip amount" form, say, has none), which left the
            // operator with no way to reach the one button that actually sets cancelRequestedAt
            // and shortens the override deadline to 20s. It now stays put throughout — showOverride()/
            // hideOverride() are the only things that ever hide it.
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
            stopOverrideWatch();
            if (cfg.el.countdown) { cfg.el.countdown.textContent = ''; }
            setStatus([resultMessage || 'PAYMENT APPROVED', 'Saving…'], 'success');
            if (cfg.el.printNotice) { cfg.el.printNotice.hidden = !merchantReceiptAutoPrinted; }
            // The transaction is finished — Cancel Payment has nothing left to cancel. Only
            // start()/showModal() (a fresh attempt, including Retry) ever shows it again.
            cfg.el.cancelBtn.hidden = true;
            if (activeBtn) { activeBtn.disabled = false; }
            cfg.onApproved(donationId, resultAmounts, currentAttempt);
            if (cfg.el.actionContainer.hidden) { setTimeout(hideModal, 1200); }
        }

        function finishDeclined(message, resultStatus) {
            clearAttempt();
            stopOverrideWatch();
            // The countdown caption belongs to the override's "still watching" state — once a
            // final outcome is known, leaving its last value on screen read as a leftover,
            // unrelated warning sitting underneath an already-resolved result.
            if (cfg.el.countdown) { cfg.el.countdown.textContent = ''; }
            var headerLabel = DECLINE_HEADER_LABELS[resultStatus] || 'Payment Declined';
            if (cfg.el.headerTitleError) { cfg.el.headerTitleError.textContent = headerLabel; }
            cfg.el.statusBox.classList.toggle('status-cancelled', resultStatus === 'CANCELLED');
            // mx51 already supplies a complete, specific message (e.g. "(TRANSACTION_CANCELLED)
            // Transaction timed out") — showing it alone matches mx51's own reference UI
            // exactly, rather than appending an invented second line that just restates the
            // same outcome in different words. The fallback title only appears on the rare
            // response with no message at all, and reuses the same real-outcome label as the
            // header rather than a second, independently hardcoded string.
            setStatus(message ? [message] : [headerLabel.toUpperCase()], 'error');
            cfg.el.cancelBtn.hidden = true;
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
            stopOverrideWatch();
            if (cfg.el.countdown) { cfg.el.countdown.textContent = ''; }
            setStatus(['RESULT UNKNOWN', message || 'Check the terminal before retrying'], 'error');
            cfg.el.cancelBtn.hidden = true;
            setTimeout(hideModal, 2200);
            if (activeBtn) { activeBtn.disabled = false; }
            cfg.onUnresolved(message);
        }

        // mx51's own documented recovery flow calls for a shorter deadline once a cancel has
        // been requested than for an ordinary transaction — their reference POS (Espresso) uses
        // 1 minute since the last successful response / 20 seconds after a cancel, explicitly as
        // an indicative starting point rather than a mandated value, which is what these mirror.
        // Cancellation uses a fixed deadline from the moment cancel was requested (mx51's
        // wording: "no FINALISED response... within a defined period", not reset by interim
        // chatter); the ordinary case resets whenever the terminal actually reports something
        // new, per "no response for a defined period" — a genuinely progressing PENDING
        // transaction never trips it, but one stuck repeating the same message forever (e.g. the
        // terminal itself has gone offline) now correctly does.
        //
        // Runs every second from a dedicated setInterval (see start()), independent of poll()'s
        // own response cycle — relying on poll() alone to evaluate this meant the deadline was
        // only ever actually checked whenever the current long-poll request happened to resolve,
        // which could run well past the intended 20s if mx51 held that request open for its own
        // full long-poll duration.
        //
        // This is also the ONLY place that writes the operator-facing status line while a
        // transaction is merely pending (poll()'s own response handler no longer does — see
        // below) — having both poll() and this ticker write to the same line fought with each
        // other, whichever ran last winning, which is what produced the real message and a bare
        // "PENDING" word flipping back and forth with no obvious pattern. While counting down it
        // shows a live countdown instead of the raw status word, so the operator always knows
        // how much longer until a response is needed; once the override has actually been
        // offered, it leaves that message alone — a very late response from an already-abandoned
        // long-poll must never silently replace it with "pending" again a few seconds later.
        function updateWaitingStatus() {
            if (cancelled || overrideOfferedAt) { return; }
            var baseline = cancelRequestedAt || lastProgressAt || startedAt;
            if (!baseline) { return; }
            var deadline = cancelRequestedAt ? 20000 : 60000;
            var elapsed = Date.now() - baseline;
            if (elapsed >= deadline) {
                overrideOfferedAt = Date.now();
                // Clears whatever mx51's own Action Framework had last rendered (buttons, the
                // Pairing ID/TID details line, anything) — left in place, this is what put a
                // stale, now-meaningless "Cancel" button and transaction details directly above
                // the override's own prompt, none of which still means anything once the
                // outcome has become genuinely unknown.
                cfg.el.actionContainer.innerHTML = '';
                cfg.el.actionContainer.hidden = true;
                showOverride();
                return;
            }
            setStatus([lastKnownMessage || 'Please wait…'], 'pending');
            // A separate, small caption element — not a second line inside the main status
            // box, which made the countdown read as if it were part of the terminal's own
            // message rather than the app's own "still watching" indicator. Left blank for the
            // first stretch of an ORDINARY transaction on purpose: poll()'s own per-call
            // patience (POLL_ABORT_MS below) means a response is expected within that window on
            // any healthy link, so showing a ticking countdown from second one would read as a
            // constant low-level warning on every normal transaction. It only appears once
            // we're past that window with nothing back yet — the point where there's actually
            // something worth telling the operator about. Applies the same way after a Cancel
            // click — it used to show its countdown immediately there on the reasoning that a
            // cancel should resolve quickly, but that meant clicking Cancel always produced an
            // instant "confirming in 20s" message even when the terminal was about to confirm
            // within the next second or two, which read as a false alarm exactly like the
            // un-gated version did for an ordinary transaction.
            if (cfg.el.countdown) {
                if (elapsed < POLL_ABORT_MS) {
                    cfg.el.countdown.textContent = '';
                } else {
                    var remaining = Math.max(1, Math.ceil((deadline - elapsed) / 1000));
                    cfg.el.countdown.textContent = cancelRequestedAt
                        ? ('Cancelling — confirming in ' + remaining + 's if no response')
                        : ('Checking again in ' + remaining + 's if no response');
                }
            }
        }

        function poll() {
            if (cancelled || !transactionId) { return; }

            // If the terminal has never even acknowledged this transaction, there's nothing
            // ambiguous to resolve — no card was ever touched, so unlike a mid-transaction
            // stall this can fail fast and clean (like a real network failure) instead of
            // making the operator wait through the slower "did it go through?" override flow.
            if (lastKnownMessage === NEVER_ACCEPTED_MESSAGE
                && !cancelRequestedAt && Date.now() - startedAt > 20000) {
                finishDeclined('Please check network and terminal connections and try again.', 'NOT_REACHABLE');
                return;
            }

            // mx51's /status endpoint is a genuine long-poll that can legitimately hold the
            // connection open for a long time — on its own, that looks indistinguishable from
            // silence to the override clock below, since lastProgressAt only moves once a call
            // actually resolves. A single still-in-flight call, however slow, is not silence:
            // nothing has failed, it just hasn't answered yet. So each call gets its own patience
            // budget, well under either override deadline; if it runs past that we abandon THAT
            // call and immediately issue a fresh one — a bookkeeping move, not a sign of trouble,
            // so it skips nextDelay()'s backoff entirely and never touches lastProgressAt. Only
            // genuine silence — this abandon-and-retry cycle repeating because nothing is coming
            // back at all — ever accumulates toward an actual override.
            var abortController = new AbortController();
            var abortTimer = setTimeout(function () { abortController.abort(); }, POLL_ABORT_MS);
            fetch(cfg.statusUrlBase + '/' + encodeURIComponent(transactionId) + qs(), { headers: { 'Accept': 'application/json' }, signal: abortController.signal })
                .then(function (res) { clearTimeout(abortTimer); return res.json(); })
                .then(function (data) {
                    if (cancelled) { return; }

                    // Any real response from the SCI API — even a repeated, unchanged "still
                    // PENDING" — proves the POS-to-API link is up, which is all the override
                    // flow exists to question. mx51's own failure-mode guidance gives the
                    // terminal itself a distinct, explicit error (DEVICE_NOT_CONNECTED, handled
                    // separately below) rather than silently echoing stale status, so there's
                    // no need to second-guess an API response by requiring its content to
                    // change too. transient_error is the one exception: it means our OWN
                    // backend could not reach the SCI API at all within its own budget
                    // (CbaSciService::pollTransaction()'s ConnectionException path) — that's
                    // the literal "no response received from the SCI API" case the override
                    // exists to catch, not proof of anything, so it must not reset the clock.
                    if (!data.transient_error) {
                        lastProgressAt = Date.now();
                    }

                    merchantReceipt = data.merchant_receipt || merchantReceipt;
                    customerReceipt = data.customer_receipt || customerReceipt;

                    var pos = data.pos_instructions || null;
                    var autoActions = (pos && pos.auto_actions) || [];
                    autoActions.forEach(function (a) { handleBuiltinAction(typeof a === 'string' ? a : (a && a.action), true); });

                    if (data.status === 'DEVICE_NOT_CONNECTED') {
                        finishDeclined(data.message || 'Please check network and terminal connections and try again.', 'DEVICE_NOT_CONNECTED');
                        return;
                    }

                    // mx51's own reference UI leads with the specific message ("Waiting for
                    // customer to present card"), not the raw status word — and a poll that
                    // doesn't repeat the message (mx51 doesn't guarantee one on every single
                    // response, only on real transitions) must never blank out the last real
                    // one or fall back to a generic placeholder while nothing has actually
                    // changed. Only ever replace it with a genuinely new message.
                    if (data.message) { lastKnownMessage = data.message; }
                    // Once the override has actually been offered, leave it exactly as shown —
                    // updateWaitingStatus() owns the visible status line now (see its own
                    // docblock on why this must be the only writer), and the dynamic buttons it
                    // might render here have no useful place to go once the operator's already
                    // been asked to confirm the outcome manually. This is what used to show the
                    // override's own "no response" box sitting directly on top of a status box
                    // still saying "Waiting for customer…/PENDING" with its spinner still
                    // spinning — a very late response from an already-abandoned long-poll
                    // silently overwriting the override a few seconds after it appeared, with no
                    // visible cause.
                    if (!overrideOfferedAt) {
                        updateWaitingStatus();
                        renderInstructions(pos);
                    }

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
                .catch(function (err) {
                    clearTimeout(abortTimer);
                    if (cancelled) { return; }
                    // Our own abort is a deliberate retry, not evidence of a problem — go again
                    // right away. Anything else (a real network failure) still backs off.
                    var ourOwnAbort = err && err.name === 'AbortError';
                    setTimeout(poll, ourOwnAbort ? 150 : nextDelay(true));
                });
        }

        function start(btn, attempt) {
            flowStarted = true;
            cancelled = false;
            transactionId = null;
            consecutiveTransientErrors = 0;
            overrideOfferedAt = null;
            overrideSubmitted = false;
            // Reset together, deliberately — the override-check interval below is armed
            // synchronously, before the /start request has even been sent, let alone answered.
            // If startedAt were left holding its value from a PREVIOUS transaction in this same
            // page session (it was only ever reassigned once that response came back, never
            // reset here), an interval tick landing in that gap would fall through to it as
            // updateWaitingStatus()'s baseline and compute elapsed against a stale, possibly
            // very old timestamp — flashing a bogus countdown for one tick before the real
            // value from THIS attempt took over a moment later. With both null, baseline is
            // null too and updateWaitingStatus() does nothing until there's a real one to use.
            startedAt = null;
            lastProgressAt = null;
            cancelRequestedAt = null;
            formValues = {};
            cfg.el.cancelBtn.disabled = false;
            activeBtn = btn;
            currentAttempt = attempt;
            saveAttempt(attempt);
            showModal(attempt.amount);
            // Re-armed on every start() — including a Retry, which calls start() again without
            // going through hideModal() first — so there's never more than one of these running.
            if (overrideCheckInterval) { clearInterval(overrideCheckInterval); }
            overrideCheckInterval = setInterval(updateWaitingStatus, 1000);

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
                    // mx51's own create response carries a real message too (their docs'
                    // example: "Waiting for terminal to accept transaction") — show it now
                    // rather than leaving the generic "Starting…" placeholder up until the
                    // first poll response comes back. updateWaitingStatus() (not a direct
                    // setStatus() call) so the countdown appears immediately too, rather than
                    // leaving the raw status word up for the ~1s until the next interval tick.
                    if (result.data.message) { lastKnownMessage = result.data.message; }
                    updateWaitingStatus();
                    renderInstructions(result.data.pos_instructions || null);
                    poll();
                })
                .catch(function () {
                    btn.disabled = false;
                    hideModal();
                    showToastFallback('Could not reach the SCI service — please try again.');
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
                    showToastFallback('Could not reach the SCI service to cancel — still waiting for the terminal.');
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
            // Guards against a second click landing before the synchronous .disabled = true
            // below actually takes effect (disabled form controls don't fire click events, but
            // a click already queued the instant before this runs still would) — without this,
            // a fast double-tap on Yes/No could submit the outcome twice.
            if (overrideSubmitted) { return; }
            overrideSubmitted = true;
            cfg.el.overrideYesBtn.disabled = true;
            cfg.el.overrideNoBtn.disabled = true;
            // Swap the question out for a plain "Saving…" line immediately — disabling the
            // buttons alone gave no visible confirmation the tap had registered, which is
            // exactly what prompted a second tap on what looked like an unresponsive button.
            if (cfg.el.overrideQuestion) { cfg.el.overrideQuestion.hidden = true; }
            if (cfg.el.overrideSaving) { cfg.el.overrideSaving.hidden = false; }
            fetch(cfg.overrideUrlBase + '/' + encodeURIComponent(transactionId) + qs(), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'outcome=' + encodeURIComponent(outcome),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    overrideSubmitted = false;
                    cfg.el.overrideYesBtn.disabled = false;
                    cfg.el.overrideNoBtn.disabled = false;
                    if (!data.success) {
                        if (cfg.el.overrideQuestion) { cfg.el.overrideQuestion.hidden = false; }
                        if (cfg.el.overrideSaving) { cfg.el.overrideSaving.hidden = true; }
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
                    overrideSubmitted = false;
                    cfg.el.overrideYesBtn.disabled = false;
                    cfg.el.overrideNoBtn.disabled = false;
                    if (cfg.el.overrideQuestion) { cfg.el.overrideQuestion.hidden = false; }
                    if (cfg.el.overrideSaving) { cfg.el.overrideSaving.hidden = true; }
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
