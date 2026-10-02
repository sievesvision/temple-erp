{{-- CBA Smart Terminal (mx51 "Simple Cloud Integration") pairing block — shown in place of
     the Linkly pairing form for any terminal with provider='cba_sci'. Reused wherever a
     terminal's pairing controls appear (currently eft-terminal-settings.blade.php; the
     Event/Ticket console EFTPOS panes can @include this the same way). Branding, unpaired/
     paired states, Test/Cancel/Unpair, and error display all per mx51's own certification
     checklist. --}}
@php $isPaired = $terminal->isSciPaired(); @endphp
<div>
    {{-- SCIPAIRING02 — the pairing screen must clearly show mx51's own SCI branding: the
         "Simple Cloud Integration" name and the SCI logo they supply, not just our own
         generic terminal icon above. Shown in both the paired and unpaired states, since this
         is the pairing section's own identity, not something that should disappear once
         paired. --}}
    <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
        <img src="{{ asset('images/sci-logo.jpg') }}" alt="SCI" style="width:28px; height:28px; border-radius:6px; object-fit:cover;">
        <strong style="font-size:0.92rem;">Simple Cloud Integration</strong>
    </div>
    @if($isPaired)
        <div class="alert alert-success py-2 px-3 mb-2" style="font-size:0.88rem;">
            <i class="bi bi-check-circle-fill me-1"></i>Paired successfully.
        </div>
        <div class="row g-2 mb-3" style="font-size:0.88rem;">
            <div class="col-md-6"><span class="text-muted">Pairing Nickname:</span> <strong>{{ $terminal->sci_pairing_nickname ?: '—' }}</strong></div>
            <div class="col-md-6"><span class="text-muted">Pairing ID:</span> <strong>{{ $terminal->sci_pairing_id }}</strong></div>
            @if($terminal->sci_tid)
            <div class="col-md-6"><span class="text-muted">TID:</span> <strong>{{ $terminal->sci_tid }}</strong></div>
            @endif
            @if($terminal->sci_terminal_nickname)
            <div class="col-md-6"><span class="text-muted">Terminal Nickname:</span> <strong>{{ $terminal->sci_terminal_nickname }}</strong></div>
            @endif
            @if($terminal->sci_confirmation_code)
            <div class="col-md-6"><span class="text-muted">Confirmation Code:</span> <strong>{{ $terminal->sci_confirmation_code }}</strong></div>
            @endif
        </div>
        @if($terminal->sci_confirmation_code)
        <p class="text-muted small mt-n2 mb-3" style="font-size:0.78rem;">Check this matches the confirmation code shown on the terminal itself.</p>
        @endif
        <div class="d-flex gap-2">
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
    @else
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
