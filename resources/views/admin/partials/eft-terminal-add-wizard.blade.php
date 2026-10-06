{{-- The "Add New Terminal" wizard — one inline, AJAX-driven step (integration type + pairing
     configuration -> Pair) instead of the old two-step "create a bare terminal, then separately
     find and fill in its pairing form" flow, mirroring mx51's own merchant-portal UX. Driven
     entirely by js/eft-terminal-registry.js (the host page must link that once, alongside
     css/eft-terminal-registry.css) — this file is pure markup, no inline script, so every host
     page that @include's eft-terminal-registry.blade.php picks it up the same way. Server
     endpoints: admin.eft-terminals.addAndPair (create + pair in one call), admin.cba-sci.test
     (mx51-only confirmation step), admin.eft-terminals.cancelNew (discards an unconfirmed
     mx51 pairing), and admin.eft-terminals.index (JSON mode, to refresh #eftTerminalsList).
     The toggle button itself (with the data-*-url attributes the JS reads) lives in the top
     row of eft-terminal-registry.blade.php, not here — this file is just the wizard body it
     reveals, which takes over the same boxed "card" look the old always-visible form used.
     `hidden` sits on this same element (not a separate wrapper around it) so the card's own
     border/padding disappears along with its content — a wrapper left visible around nothing
     would otherwise leave an empty bordered box sitting on the page while collapsed. --}}
<div class="add-terminal-card" id="addTerminalWizard" hidden>

        {{-- Step 1: integration type + pairing configuration --}}
        <div id="wizardStep1">
            <div class="wizard-heading">Integration type</div>
            <div class="integration-type-row">
                <label class="integration-type-card active" data-provider-card="cba_sci">
                    <input type="radio" name="wizard_provider" value="cba_sci" id="wizardProviderSci" checked>
                    <img src="{{ asset('images/sci-logo.jpg') }}" alt="" class="integration-type-icon-img">
                    <span>Simple Cloud Integration</span>
                </label>
                <label class="integration-type-card" data-provider-card="linkly">
                    <input type="radio" name="wizard_provider" value="linkly" id="wizardProviderLinkly">
                    <span class="integration-type-icon-badge">LNK</span>
                    <span>Linkly Cloud Integration</span>
                </label>
            </div>

            <div class="wizard-columns">
                <div class="wizard-main-col">
                    <div class="wizard-heading">Pairing configuration</div>
                    <div class="wizard-alert wizard-alert-error" id="wizardStep1Error" hidden></div>

                    <div class="wizard-field">
                        <label class="form-label small fw-semibold">Pairing Code</label>
                        <input type="text" id="wizardPairingCode" class="form-control rounded-3" placeholder="Code from the terminal" maxlength="20">
                        <div class="field-hint">You can get the pairing code from the terminal's own pairing setup.</div>
                    </div>
                    {{-- No "Unique Terminal Code" field any more — the physical terminal's own
                         TID (for SCI) becomes its identity once pairing confirms it, rather
                         than an admin-typed code (see EftTerminalController::addAndPair() /
                         CbaSciService::pair()). This nickname is the only thing an admin names
                         by hand, and it's optional even then. --}}
                    <div class="wizard-field">
                        <label class="form-label small fw-semibold">Pairing Nickname <span class="text-muted">(optional)</span></label>
                        <input type="text" id="wizardPairingNickname" class="form-control rounded-3" placeholder="e.g. Front Counter" maxlength="255">
                    </div>

                    <div class="wizard-actions">
                        <button type="button" class="btn-add-terminal" id="wizardPairBtn">Pair</button>
                        <button type="button" class="btn btn-outline-secondary rounded-3" id="wizardCancelStep1Btn">Cancel</button>
                    </div>
                </div>

                <div class="wizard-steps-col">
                    <div class="wizard-heading">Steps to pair for:</div>
                    <div class="wizard-steps-heading" id="wizardStepsHeading">Simple Cloud Integration</div>
                    <ol class="wizard-steps-list" id="wizardStepsListSci">
                        <li><em>On the terminal:</em> Go to &ldquo;Manage POS pairing&rdquo; and create a new POS pairing.</li>
                        <li><em>On the terminal:</em> Select &ldquo;Simple Cloud Integration&rdquo;.</li>
                        <li>Enter the pairing code provided from the terminal.</li>
                        <li>Create a pairing nickname for the terminal to identify.</li>
                        <li>Tap &ldquo;Pair&rdquo; to initiate the pairing on both devices.</li>
                    </ol>
                    <ol class="wizard-steps-list" id="wizardStepsListLinkly" hidden>
                        <li><em>On the terminal:</em> Open the Cloud Pairing / POS setup menu.</li>
                        <li>Generate a Cloud Pairing Code — it stays valid for about 3 minutes.</li>
                        <li>Enter that code here before it expires.</li>
                        <li>Tap &ldquo;Pair&rdquo; to connect the terminal.</li>
                    </ol>
                </div>
            </div>
        </div>

        {{-- Step 2: mx51-only interactive confirmation — Linkly pairs synchronously in Step 1
             and skips straight to the success panel instead. --}}
        <div id="wizardStep2" hidden>
            <div class="wizard-columns">
                <div class="wizard-main-col">
                    <div class="wizard-confirm-box">
                        <div class="wizard-confirm-icon"><i class="bi bi-three-dots"></i></div>
                        <div>
                            <div class="wizard-confirm-title">Pairing</div>
                            <div class="wizard-confirm-sub">Confirm that the following code is showing on the terminal</div>
                        </div>
                    </div>
                    <div class="wizard-code-label">Code:</div>
                    <div class="wizard-code" id="wizardConfirmationCode">&mdash;</div>
                    <div class="wizard-alert wizard-alert-error" id="wizardStep2Error" hidden></div>
                    <div class="wizard-actions">
                        <button type="button" class="btn btn-outline-secondary rounded-3" id="wizardCancelStep2Btn">Cancel</button>
                        <button type="button" class="btn-add-terminal" id="wizardTestBtn"><i class="bi bi-arrow-repeat me-1"></i>Test</button>
                    </div>
                </div>
                <div class="wizard-steps-col">
                    <div class="wizard-heading">Confirm the connection</div>
                    <ol class="wizard-steps-list">
                        <li><em>On the terminal:</em> Confirm that the pairing code matches.</li>
                        <li>Test the connection via the Test button.</li>
                    </ol>
                </div>
            </div>
        </div>

        {{-- Success --}}
        <div id="wizardSuccess" hidden>
            <div class="wizard-success-box">
                <i class="bi bi-check-circle-fill"></i>
                <span>Pairing is active.</span>
            </div>
            <button type="button" class="btn btn-outline-secondary rounded-3" id="wizardBackToTerminalsBtn"><i class="bi bi-arrow-left me-1"></i>Back to Terminals</button>
        </div>

</div>
