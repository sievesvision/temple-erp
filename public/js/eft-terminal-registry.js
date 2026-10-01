// Drives the "Add New Terminal" wizard (see admin/partials/eft-terminal-add-wizard.blade.php)
// — the one piece of the EFT Terminal registry that's AJAX end to end rather than a plain form
// POST. Every other action on this page (pair/test/unpair an EXISTING terminal, rename, set
// default, remove) is untouched full-page-POST, on purpose — see the registry partial's own
// docblock. Re-initialises itself after every #eftTerminalsList refresh is unnecessary here
// since the wizard card itself is never replaced, only the terminals list above it.
(function () {
    'use strict';

    const card = document.getElementById('addTerminalCard');
    if (!card) { return; }

    const ADD_URL = card.dataset.addUrl;
    const TEST_URL = card.dataset.testUrl;
    const CANCEL_NEW_URL_BASE = card.dataset.cancelNewUrlBase;
    const REFRESH_URL = card.dataset.refreshUrl;
    const CSRF = card.dataset.csrf;

    const toggleBtn = document.getElementById('addTerminalToggleBtn');
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
    const terminalKeyInput = document.getElementById('wizardTerminalKey');
    const pairingNicknameInput = document.getElementById('wizardPairingNickname');
    const step1Error = document.getElementById('wizardStep1Error');
    const pairBtn = document.getElementById('wizardPairBtn');
    const cancelStep1Btn = document.getElementById('wizardCancelStep1Btn');

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
        terminalKeyInput.value = '';
        pairingNicknameInput.value = '';
        hideError(step1Error);
        hideError(step2Error);
        confirmationCodeEl.textContent = '—';
        pendingTerminalId = null;
        providerSci.checked = true;
        syncIntegrationType();
        showStep(step1);
    }

    function hideError(el) { el.hidden = true; el.textContent = ''; }
    function showError(el, message) { el.textContent = message; el.hidden = false; }

    // A 422 from Laravel's own validate() (e.g. the Unique Terminal Code is already taken)
    // comes back as {message, errors: {field: [...]}} rather than this app's usual
    // {success:false, message} shape — pull the specific field error out when present instead
    // of falling back to the generic "The given data was invalid." message.
    function firstValidationError(data) {
        if (data && data.errors) {
            const firstKey = Object.keys(data.errors)[0];
            if (firstKey && data.errors[firstKey] && data.errors[firstKey][0]) { return data.errors[firstKey][0]; }
        }
        return null;
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

    toggleBtn.addEventListener('click', function () {
        const opening = wizard.hidden;
        wizard.hidden = !wizard.hidden;
        toggleBtn.hidden = !wizard.hidden ? false : true;
        if (opening) { resetWizard(); }
    });

    function closeWizard() {
        wizard.hidden = true;
        toggleBtn.hidden = false;
    }

    cancelStep1Btn.addEventListener('click', closeWizard);

    function setBusy(btn, busy, busyText) {
        btn.disabled = busy;
        if (busy) { btn.dataset.originalText = btn.innerHTML; btn.innerHTML = busyText; }
        else if (btn.dataset.originalText) { btn.innerHTML = btn.dataset.originalText; }
    }

    function postForm(url, params) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params).toString(),
        }).then(function (resp) { return resp.json(); });
    }

    pairBtn.addEventListener('click', function () {
        hideError(step1Error);

        const provider = providerSci.checked ? 'cba_sci' : 'linkly';
        const pairingCode = pairingCodeInput.value.trim();
        const terminalKey = terminalKeyInput.value.trim();
        const pairingNickname = pairingNicknameInput.value.trim();

        if (!pairingCode) { showError(step1Error, 'Enter the pairing code from the terminal.'); return; }
        if (!terminalKey) { showError(step1Error, 'Enter a unique terminal code.'); return; }

        setBusy(pairBtn, true, 'Pairing…');

        postForm(ADD_URL, { provider: provider, pairing_code: pairingCode, key: terminalKey, pairing_nickname: pairingNickname })
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

        postForm(TEST_URL, { terminal_id: pendingTerminalId })
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

    cancelStep2Btn.addEventListener('click', function () {
        if (!pendingTerminalId) { closeWizard(); return; }
        setBusy(cancelStep2Btn, true, 'Cancelling…');

        postForm(CANCEL_NEW_URL_BASE + '/' + pendingTerminalId + '/cancel-new', {})
            .then(function () {
                setBusy(cancelStep2Btn, false);
                closeWizard();
                refreshTerminalsList();
            })
            .catch(function () {
                setBusy(cancelStep2Btn, false);
                closeWizard();
            });
    });

    backToTerminalsBtn.addEventListener('click', function () {
        closeWizard();
        refreshTerminalsList();
    });

    function refreshTerminalsList() {
        fetch(REFRESH_URL, { headers: { 'Accept': 'application/json' } })
            .then(function (resp) { return resp.json(); })
            .then(function (data) {
                if (!data.html) { return; }
                const parser = new DOMParser();
                const doc = parser.parseFromString(data.html, 'text/html');
                const freshList = doc.getElementById('eftTerminalsList');
                const currentList = document.getElementById('eftTerminalsList');
                if (freshList && currentList) { currentList.innerHTML = freshList.innerHTML; }
            })
            .catch(function () { /* list simply stays as it was before the refresh attempt */ });
    }
})();
