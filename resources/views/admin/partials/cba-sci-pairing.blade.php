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
        <form action="{{ route('admin.cba-sci.pair') }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
            <div class="col-md-4">
                <label class="form-label small mb-1">Pairing Code</label>
                <input type="text" name="pairing_code" class="form-control form-control-sm rounded-3" placeholder="Code from the terminal" maxlength="20" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Pairing Nickname <span class="text-muted">(optional)</span></label>
                <input type="text" name="pairing_nickname" class="form-control form-control-sm rounded-3" placeholder="e.g. Front Counter" maxlength="255">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary">Pair</button>
                <button type="submit" form="{{ 'cancel-pairing-' . $terminal->id }}" class="btn btn-sm btn-outline-secondary">Cancel</button>
            </div>
        </form>
        {{-- Cancel is its own form (submitting the Unpair endpoint), not a plain reset — per
             mx51's own certification checklist (SCIPAIRING07), cancelling a pairing attempt
             must call POST Unpair too, so no incomplete pairing record is ever left behind
             even if one happened to exist. Harmless when there was nothing to cancel: see
             CbaSciService::unpair()'s own docblock. --}}
        <form id="{{ 'cancel-pairing-' . $terminal->id }}" action="{{ route('admin.cba-sci.unpair') }}" method="POST" class="d-none">
            @csrf
            <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
        </form>
        {{-- SCIPAIRING09 — the pairing screen should show the same kind of step-by-step
             instructions as mx51's own Espresso POS reference, not just a one-line hint. --}}
        <div class="mt-3 p-3 rounded-3" style="background:#F9FAFB; border:1px solid #E5E7EB; font-size:0.84rem;">
            <div class="fw-bold mb-2">Steps to pair for: Simple Cloud Integration</div>
            <ol class="mb-0 ps-3" style="line-height:1.6;">
                <li>On the terminal: Go to &ldquo;Manage POS pairing&rdquo; and create a new POS pairing.</li>
                <li>On the terminal: Select &ldquo;Simple Cloud Integration&rdquo;.</li>
                <li>Enter the pairing code provided from the terminal.</li>
                <li>Create a pairing nickname for the terminal to identify.</li>
                <li>Tap &ldquo;Pair&rdquo; to initiate the pairing on both devices.</li>
            </ol>
        </div>
    @endif
</div>
