// Drives the two AJAX-driven pieces of the EFT Terminal registry:
//  1. The "Add New Terminal" wizard (admin/partials/eft-terminal-add-wizard.blade.php).
//  2. The mx51 re-pair widget on an already-registered, currently-unpaired terminal's own card
//     (admin/partials/cba-sci-pairing.blade.php) — one widget per terminal, so it's
//     initialised for every `.sci-repair-widget` found on the page, not a single fixed id.
// Every OTHER action on this page (rename, set default, remove, Linkly pair, mx51 Unpair) is
// untouched full-page-POST, on purpose — see the registry partial's own docblock.
(function () {
    'use strict';

    // Exported FIRST, before either init*() call below — both of those assume every element
    // they look up exists (no null-guards beyond the one top-of-function early return each
    // has), so a markup change or edge case that makes either throw must never also take this
    // export down with it; the console pages' own switchPane()/activatePane() depend on it
    // being there every time the "EFT Terminal Settings" pane is opened, independent of
    // whether the wizard/re-pair widgets themselves initialised cleanly.
    window.EftTerminalRegistry = { refresh: refreshTerminalsList };

    try { initAddTerminalWizard(); } catch (e) { console.error('EFT terminal add-wizard failed to initialise', e); }
    try { initSciRepairWidgets(); } catch (e) { console.error('EFT terminal re-pair widgets failed to initialise', e); }

    // Lives at the top level (not inside initAddTerminalWizard()) so it works even on a page
    // where the Add Terminal button itself is hidden (no canManageRegistryLevel) — reading its
    // URL from #eftTerminalsList's own data attribute, which is always rendered wherever this
    // registry partial is, rather than from that conditionally-rendered button.
    function refreshTerminalsList() {
        const currentList = document.getElementById('eftTerminalsList');
        if (!currentList) { return Promise.resolve(); }
        const refreshUrl = currentList.dataset.refreshUrl;
        if (!refreshUrl) { return Promise.resolve(); }

        return fetch(refreshUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (resp) { return resp.json(); })
            .then(function (data) {
                if (!data.html) { return; }
                const parser = new DOMParser();
                const doc = parser.parseFromString(data.html, 'text/html');
                const freshList = doc.getElementById('eftTerminalsList');
                if (freshList) {
                    currentList.innerHTML = freshList.innerHTML;
                    // The innerHTML swap just destroyed every .sci-repair-widget this page had
                    // bound listeners to and replaced them with inert new ones (Pair/Test/Cancel
                    // clicks silently doing nothing) — re-bind against the fresh set now that
                    // they actually exist in the DOM.
                    try { initSciRepairWidgets(); } catch (e) { console.error('EFT terminal re-pair widgets failed to initialise', e); }
                }
            })
            .catch(function () { /* list simply stays as it was before the refresh attempt */ });
    }

    function postForm(url, csrf, params) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params).toString(),
        }).then(function (resp) { return resp.json(); });
    }

    function setBusy(btn, busy, busyText) {
        btn.disabled = busy;
        if (busy) { btn.dataset.originalText = btn.innerHTML; btn.innerHTML = busyText; }
        else if (btn.dataset.originalText) { btn.innerHTML = btn.dataset.originalText; }
    }

    function hideError(el) { el.hidden = true; el.textContent = ''; }
    function showError(el, message) { el.textContent = message; el.hidden = false; }

    // A 422 from Laravel's own validate() comes back as {message, errors: {field: [...]}}
    // rather than this app's usual {success:false, message} shape — pull the specific field
    // error out when present instead of falling back to the generic "The given data was
    // invalid." message.
    function firstValidationError(data) {
        if (data && data.errors) {
            const firstKey = Object.keys(data.errors)[0];
            if (firstKey && data.errors[firstKey] && data.errors[firstKey][0]) { return data.errors[firstKey][0]; }
        }
        return null;
    }

    function initAddTerminalWizard() {
        const toggleBtn = document.getElementById('addTerminalToggleBtn');
        if (!toggleBtn) { return; }

        // The top-right "+ Add New Terminal" button itself carries the data-*-url attributes
        // (see eft-terminal-registry.blade.php) — it's the one element guaranteed to exist
        // everywhere this wizard appears, unlike a wrapping card that used to sit next to it.
        const ADD_URL = toggleBtn.dataset.addUrl;
        const TEST_URL = toggleBtn.dataset.testUrl;
        const CANCEL_NEW_URL_BASE = toggleBtn.dataset.cancelNewUrlBase;
        const CSRF = toggleBtn.dataset.csrf;

        const terminalsList = document.getElementById('eftTerminalsList');
        const bottomSettings = document.getElementById('eftRegistryBottomSettings');
        const wizard = document.getElementById('addTerminalWizard');
        const step1 = document.getElementById('wizardStep1');
        const step2 = document.getElementById('wizardStep2');
        const success = document.getElementById('wizardSuccess');

        const providerSci = document.getElementById('wizardProviderSci');
        const providerLinkly = document.getElementById('wizardProviderLinkly');
        const cardSci = document.querySelector('.integration-type-card[data-provider-card="cba_sci"]');
        const cardLinkly = document.querySelector('.integration-type-card[data-provider-card="linkly"]');
        const stepsHeading = document.getElementById('wizardStepsHeading');
        const stepsListSci = document.getElementById('wizardStepsListSci');
        const stepsListLinkly = document.getElementById('wizardStepsListLinkly');

        const pairingCodeInput = document.getElementById('wizardPairingCode');
        const pairingNicknameInput = document.getElementById('wizardPairingNickname');
        const step1Error = document.getElementById('wizardStep1Error');
        const pairBtn = document.getElementById('wizardPairBtn');

        const confirmationCodeEl = document.getElementById('wizardConfirmationCode');
        const step2Error = document.getElementById('wizardStep2Error');
        const testBtn = document.getElementById('wizardTestBtn');
        const cancelStep2Btn = document.getElementById('wizardCancelStep2Btn');
        const backToTerminalsBtn = document.getElementById('wizardBackToTerminalsBtn');

        let pendingTerminalId = null;

        function showStep(step) {
            [step1, step2, success].forEach(function (el) { el.hidden = (el !== step); });
        }

        function resetWizard() {
            pairingCodeInput.value = '';
            pairingNicknameInput.value = '';
            hideError(step1Error);
            hideError(step2Error);
            confirmationCodeEl.textContent = '—';
            pendingTerminalId = null;
            providerSci.checked = true;
            syncIntegrationType();
            showStep(step1);
        }

        function syncIntegrationType() {
            const isSci = providerSci.checked;
            cardSci.classList.toggle('active', isSci);
            cardLinkly.classList.toggle('active', !isSci);
            stepsHeading.textContent = isSci ? 'Simple Cloud Integration' : 'Linkly Cloud Integration';
            stepsListSci.hidden = !isSci;
            stepsListLinkly.hidden = isSci;
        }

        providerSci.addEventListener('change', syncIntegrationType);
        providerLinkly.addEventListener('change', syncIntegrationType);
        cardSci.addEventListener('click', function () { providerSci.checked = true; syncIntegrationType(); });
        cardLinkly.addEventListener('click', function () { providerLinkly.checked = true; syncIntegrationType(); });

        const ADD_LABEL = toggleBtn.innerHTML;
        const BACK_LABEL = '<i class="bi bi-arrow-left"></i> Back to Terminals';

        // The existing terminal cards (and the Transaction Limits / Receipt Printing settings
        // below them) step out of the way while the wizard is open — adding a terminal used to
        // just pile a form on top of everything else on the page, which got cluttered fast. The
        // button itself never disappears — it just relabels to "Back to Terminals" so there's
        // always exactly one obvious way back, matching whatever the wizard's current step needs
        // (nothing to undo in step 1, cancel an unconfirmed mx51 pairing in step 2, or just
        // return to a now-current list after a completed pairing).
        function openWizard() {
            wizard.hidden = false;
            if (terminalsList) { terminalsList.hidden = true; }
            if (bottomSettings) { bottomSettings.hidden = true; }
            toggleBtn.innerHTML = BACK_LABEL;
            resetWizard();
        }

        function closeWizard() {
            wizard.hidden = true;
            if (terminalsList) { terminalsList.hidden = false; }
            if (bottomSettings) { bottomSettings.hidden = false; }
            toggleBtn.innerHTML = ADD_LABEL;
        }

        function backToTerminals() {
            // Leaving the wizard entirely while an unconfirmed mx51 pairing is still pending —
            // cancel it first (same server call the dedicated Cancel button makes), then
            // actually leave; if the cancel itself fails, stay put so the error is visible
            // rather than leaving silently with the pairing still live.
            if (!step2.hidden) {
                cancelPendingPairing(function (cancelled) {
                    if (cancelled) { closeWizard(); refreshTerminalsList(); }
                });
                return;
            }
            if (!success.hidden) { closeWizard(); refreshTerminalsList(); return; }
            closeWizard();
        }

        toggleBtn.addEventListener('click', function () {
            if (wizard.hidden) { openWizard(); } else { backToTerminals(); }
        });

        pairBtn.addEventListener('click', function () {
            hideError(step1Error);

            const provider = providerSci.checked ? 'cba_sci' : 'linkly';
            const pairingCode = pairingCodeInput.value.trim();
            const pairingNickname = pairingNicknameInput.value.trim();

            if (!pairingCode) { showError(step1Error, 'Enter the pairing code from the terminal.'); return; }

            setBusy(pairBtn, true, 'Pairing…');

            postForm(ADD_URL, CSRF, { provider: provider, pairing_code: pairingCode, pairing_nickname: pairingNickname })
                .then(function (data) {
                    setBusy(pairBtn, false);
                    if (!data.success) {
                        showError(step1Error, firstValidationError(data) || data.message || 'Pairing failed — please try again.');
                        return;
                    }
                    pendingTerminalId = data.terminal_id;
                    if (data.requires_confirmation) {
                        confirmationCodeEl.textContent = data.confirmation_code || '—';
                        hideError(step2Error);
                        showStep(step2);
                    } else {
                        showStep(success);
                    }
                })
                .catch(function () {
                    setBusy(pairBtn, false);
                    showError(step1Error, 'Could not reach the server — check your connection and try again.');
                });
        });

        testBtn.addEventListener('click', function () {
            if (!pendingTerminalId) { return; }
            hideError(step2Error);
            setBusy(testBtn, true, 'Testing…');

            postForm(TEST_URL, CSRF, { terminal_id: pendingTerminalId })
                .then(function (data) {
                    setBusy(testBtn, false);
                    if (!data.success) {
                        showError(step2Error, data.message || 'Could not confirm the pairing — try again.');
                        return;
                    }
                    showStep(success);
                })
                .catch(function () {
                    setBusy(testBtn, false);
                    showError(step2Error, 'Could not reach the server — check your connection and try again.');
                });
        });

        // Actually cancels a still-pending mx51 pairing server-side (unpair + delete the row —
        // see EftTerminalController::cancelNewTerminal()), per mx51's own certification
        // checklist (SCIPAIRING07). Shared by the dedicated Step 2 Cancel button (which then
        // resets to a fresh Step 1, ready to try again) and the top "Back to Terminals" button
        // when Step 2 is showing (which then leaves the wizard entirely) — onDone(cancelled)
        // tells the caller which happened, since a failure here (e.g. this "pending" pairing
        // actually resolved to an existing, already-registered terminal with real history —
        // see CbaSciService::pair()'s TID matching — which correctly refuses to be deleted this
        // way) must leave the admin looking at *why*, not be silently treated as a success that
        // bounces them somewhere while the pairing is still live.
        function cancelPendingPairing(onDone) {
            if (!pendingTerminalId) { if (onDone) { onDone(true); } return; }
            hideError(step2Error);
            setBusy(cancelStep2Btn, true, 'Cancelling…');

            postForm(CANCEL_NEW_URL_BASE + '/' + pendingTerminalId + '/cancel-new', CSRF, {})
                .then(function (data) {
                    setBusy(cancelStep2Btn, false);
                    if (!data.success) {
                        showError(step2Error, data.message || 'Could not cancel this pairing.');
                        if (onDone) { onDone(false); }
                        return;
                    }
                    pendingTerminalId = null;
                    if (onDone) { onDone(true); }
                })
                .catch(function () {
                    setBusy(cancelStep2Btn, false);
                    showError(step2Error, 'Could not reach the server — check your connection and try again.');
                    if (onDone) { onDone(false); }
                });
        }

        cancelStep2Btn.addEventListener('click', function () {
            cancelPendingPairing(function (cancelled) {
                if (!cancelled) { return; }
                // Back to a fresh Step 1, ready to add another terminal — cancelling here means
                // "that attempt didn't work, let me try again," not "I'm done adding terminals."
                refreshTerminalsList();
                resetWizard();
            });
        });

        backToTerminalsBtn.addEventListener('click', function () {
            closeWizard();
            refreshTerminalsList();
        });
    }

    // Re-pairing an EXISTING mx51 terminal used to be a plain full-page POST that landed
    // straight on the static paired view — skipping the same interactive "confirm the code on
    // the terminal, then press Test" moment the Add Terminal wizard already has. This brings
    // that same moment to re-pairing too, scoped per terminal (a page can show more than one
    // unpaired mx51 terminal's card at once) via data attributes and querying only within each
    // widget's own subtree, never a fixed global id. Test/Cancel finish the same way the
    // wizard's own "Back to Terminals" does — a brief success message (Test only), then an AJAX
    // refreshTerminalsList(), never a full-page reload. A reload here used to dump the admin
    // back on the console's default "New Donation" pane instead of back on EFT Terminal
    // Settings, since these buttons are plain JS actions, not a <form> submit the console's own
    // "remember which pane was active" listener (see event-console.blade.php) could catch.
    function initSciRepairWidgets() {
        document.querySelectorAll('.sci-repair-widget').forEach(function (widget) {
            const PAIR_URL = widget.dataset.pairUrl;
            const TEST_URL = widget.dataset.testUrl;
            const UNPAIR_URL = widget.dataset.unpairUrl;
            const CSRF = widget.dataset.csrf;
            const terminalId = widget.dataset.terminalId;

            const pairStep = widget.querySelector('.sci-repair-step-pair');
            const confirmStep = widget.querySelector('.sci-repair-step-confirm');
            const codeInput = widget.querySelector('.sci-repair-code-input');
            const nicknameInput = widget.querySelector('.sci-repair-nickname-input');
            const pairBtn = widget.querySelector('.sci-repair-pair-btn');
            const pairError = widget.querySelector('.sci-repair-error');
            const confirmationCodeEl = widget.querySelector('.sci-repair-confirmation-code');
            const confirmError = widget.querySelector('.sci-repair-confirm-error');
            const testBtn = widget.querySelector('.sci-repair-test-btn');
            const cancelBtn = widget.querySelector('.sci-repair-cancel-btn');
            const confirmInstructions = widget.querySelector('.sci-repair-confirm-instructions');
            const confirmActions = widget.querySelector('.sci-repair-confirm-actions');
            const successStep = widget.querySelector('.sci-repair-step-success');

            pairBtn.addEventListener('click', function () {
                hideError(pairError);
                const pairingCode = codeInput.value.trim();
                const nickname = nicknameInput.value.trim();
                if (!pairingCode) { showError(pairError, 'Enter the pairing code from the terminal.'); return; }

                setBusy(pairBtn, true, 'Pairing…');
                postForm(PAIR_URL, CSRF, { terminal_id: terminalId, pairing_code: pairingCode, pairing_nickname: nickname })
                    .then(function (data) {
                        setBusy(pairBtn, false);
                        if (!data.success) {
                            showError(pairError, firstValidationError(data) || data.message || 'Pairing failed — please try again.');
                            return;
                        }
                        confirmationCodeEl.textContent = data.confirmation_code || '—';
                        hideError(confirmError);
                        pairStep.hidden = true;
                        confirmStep.hidden = false;
                    })
                    .catch(function () {
                        setBusy(pairBtn, false);
                        showError(pairError, 'Could not reach the server — check your connection and try again.');
                    });
            });

            testBtn.addEventListener('click', function () {
                hideError(confirmError);
                setBusy(testBtn, true, 'Testing…');
                postForm(TEST_URL, CSRF, { terminal_id: terminalId })
                    .then(function (data) {
                        if (!data.success) {
                            setBusy(testBtn, false);
                            showError(confirmError, data.message || 'Could not confirm the pairing — try again.');
                            return;
                        }
                        // Same "success message, then back to the list" moment the Add Terminal
                        // wizard already has — the refreshed list won't even include this widget
                        // any more (a paired cba_sci terminal has no expandable panel at all, see
                        // eft-terminal-card.blade.php's $expandable), so this naturally collapses
                        // back into the terminal's own now-Paired row.
                        if (confirmInstructions) { confirmInstructions.hidden = true; }
                        confirmationCodeEl.hidden = true;
                        if (confirmActions) { confirmActions.hidden = true; }
                        if (successStep) { successStep.hidden = false; }
                        setTimeout(refreshTerminalsList, 900);
                    })
                    .catch(function () {
                        setBusy(testBtn, false);
                        showError(confirmError, 'Could not reach the server — check your connection and try again.');
                    });
            });

            // Per mx51's own certification checklist (SCIPAIRING07), cancelling an in-progress
            // pairing must call Unpair too, same as the Add Terminal wizard's Step 2 Cancel.
            cancelBtn.addEventListener('click', function () {
                setBusy(cancelBtn, true, 'Cancelling…');
                postForm(UNPAIR_URL, CSRF, { terminal_id: terminalId })
                    .then(function () { refreshTerminalsList(); })
                    .catch(function () { refreshTerminalsList(); });
            });
        });
    }
})();
