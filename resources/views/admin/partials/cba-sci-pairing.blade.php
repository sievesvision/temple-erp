{{-- CBA Smart Terminal (mx51 "Simple Cloud Integration") re-pairing widget — only ever
     @include'd for an UNPAIRED cba_sci terminal (see eft-terminal-card.blade.php's
     $expandable: a paired one has no expand/collapse at all, so this never renders for it).
     SCIPAIRING02's branding is shown once, directly on the card's summary row, not repeated
     here. AJAX-driven (see js/eft-terminal-registry.js's initSciRepairWidgets()) so re-pairing
     an existing terminal gets the same interactive "confirm the code on the terminal, then
     press Test" moment the Add Terminal wizard has, instead of a full-page POST that landed
     straight on a static "Paired successfully" view — pairing itself was always already live
     on mx51's side at that point (there's no separate server-side "confirmed" state to
     persist), this was purely a missing UI step. --}}
<div class="sci-repair-widget"
     data-terminal-id="{{ $terminal->id }}"
     data-pair-url="{{ route('admin.cba-sci.pair') }}"
     data-test-url="{{ route('admin.cba-sci.test') }}"
     data-unpair-url="{{ route('admin.cba-sci.unpair') }}"
     data-csrf="{{ csrf_token() }}">

    <div class="sci-repair-step-pair row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-1">Pairing Code</label>
            <input type="text" class="sci-repair-code-input form-control form-control-sm rounded-3" placeholder="Code from the terminal" maxlength="20">
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-1">Pairing Nickname <span class="text-muted">(optional)</span></label>
            <input type="text" class="sci-repair-nickname-input form-control form-control-sm rounded-3" placeholder="e.g. Front Counter" maxlength="255">
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="button" class="sci-repair-pair-btn btn btn-sm btn-outline-primary">Pair</button>
        </div>
        <div class="col-12 text-danger small sci-repair-error" hidden></div>
    </div>

    <div class="sci-repair-step-confirm" hidden>
        <div class="alert alert-warning py-2 px-3 mb-2" style="font-size:0.88rem;">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>Confirm that the following code is showing on the terminal, then press Test.
        </div>
        <div class="sci-repair-confirmation-code mb-2" style="font-size:1.7rem; font-weight:800; letter-spacing:0.05em;">&mdash;</div>
        <div class="text-danger small mb-2 sci-repair-confirm-error" hidden></div>
        <div class="d-flex gap-2">
            <button type="button" class="sci-repair-cancel-btn btn btn-sm btn-outline-secondary">Cancel</button>
            <button type="button" class="sci-repair-test-btn btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Test</button>
        </div>
    </div>
</div>
{{-- SCIPAIRING09's step-by-step instructions (mx51's own Espresso POS reference) are
     deliberately NOT repeated here — this widget is for RE-pairing a terminal already known to
     this registry, not setting one up for the first time. A terminal that's lost its pairing
     just needs a fresh code and a Pair button; the full walkthrough lives once, in
     eft-terminal-add-wizard.blade.php, for a genuinely new terminal. --}}
