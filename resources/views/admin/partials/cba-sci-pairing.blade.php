{{-- CBA Smart Terminal (mx51 "Simple Cloud Integration") pairing block — shown in place of
     the Linkly pairing form for any terminal with provider='cba_sci'. Reused wherever a
     terminal's pairing controls appear (currently eft-terminal-settings.blade.php; the
     Event/Ticket console EFTPOS panes can @include this the same way). Branding, unpaired/
     paired states, Test/Cancel/Unpair, and error display all per mx51's own certification
     checklist. --}}
@php $isPaired = $terminal->isSciPaired(); @endphp
<div>
    {{-- SCIPAIRING02 — the pairing screen must clearly show mx51's own SCI branding: the
         "Simple Cloud Integration" name and the SCI logo they supply. Kept to one line — label,
         TID, provider and mode are already on the card's summary row above, so all that
         belongs here is the branding, the paired state, and the pairing actions. --}}
    @if($isPaired)
        <div class="d-flex align-items-center flex-wrap gap-2" style="font-size:0.88rem;">
            <img src="{{ asset('images/sci-logo.jpg') }}" alt="SCI" style="width:22px; height:22px; border-radius:5px; object-fit:cover;">
            <strong>Simple Cloud Integration</strong>
            <span class="badge-pill badge-ok"><i class="bi bi-check-circle-fill me-1"></i>Paired</span>
            <span class="text-muted">Pairing ID: {{ $terminal->sci_pairing_id }}</span>
            <div class="d-flex gap-2 ms-auto">
                <form action="{{ route('admin.cba-sci.test') }}" method="POST">
                    @csrf
                    <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Test</button>
                </form>
                <form action="{{ route('admin.cba-sci.unpair') }}" method="POST" onsubmit="return confirm('Unpair this terminal? The terminal will need a fresh pairing code to reconnect.');">
                    @csrf
                    <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Unpair</button>
                </form>
            </div>
        </div>
    @else
        <div class="d-flex align-items-center gap-2 mb-2">
            <img src="{{ asset('images/sci-logo.jpg') }}" alt="SCI" style="width:22px; height:22px; border-radius:5px; object-fit:cover;">
            <strong style="font-size:0.88rem;">Simple Cloud Integration</strong>
        </div>
        {{-- AJAX-driven (see js/eft-terminal-registry.js's initSciRepairWidgets()) so re-pairing
             an existing terminal gets the same interactive "confirm the code on the terminal,
             then press Test" moment the Add Terminal wizard has, instead of a full-page POST
             that landed straight on the "Paired successfully" view above — pairing itself was
             always already live on mx51's side at that point (there's no separate server-side
             "confirmed" state to persist), this was purely a missing UI step. --}}
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
             deliberately NOT repeated here — this widget is for RE-pairing a terminal already
             known to this registry, not setting one up for the first time. A terminal that's
             lost its pairing just needs a fresh code and a Pair button; the full walkthrough
             lives once, in eft-terminal-add-wizard.blade.php, for a genuinely new terminal. --}}
    @endif
</div>
