{{-- The EFT Terminal registry — @include'd verbatim by the standalone EFT Terminal Settings
     page, both consoles' own EFT Terminal Settings pane, and Admin Settings' EFT Terminal
     panel, so pairing/adding/removing a terminal is implemented exactly once no matter where
     it's reached from. Expects: $activeTerminals, $inactiveTerminals, $linklyMode,
     $cbaSciMode, $canManageRegistryLevel, $isSystemAdmin, $allOperational — the first five
     mirror EftTerminalController::index()'s own variables exactly; $isSystemAdmin (literal
     Admin role, stricter than $canManageRegistryLevel) gates Set Default/Remove Terminal the
     same way eft-terminal-card.blade.php always has. The host page must link
     css/eft-terminal-registry.css once and already define the --maroon/--gold/--cream/
     --white/--border/--text-primary/--text-secondary/--serif tokens this relies on (every
     current host already does, as they all share the same temple-branding palette).
     Every form redirects with plain redirect()->back(), so it always lands back on whichever
     of those pages it was actually submitted from — nothing here needs to know which one
     that is. --}}
<div class="eft-info-banner">
    <span class="eft-info-banner-icon"><i class="bi bi-info-lg"></i></span>
    <div>
        <div class="eft-info-banner-title">Multiple EFT terminals can be used at the same time</div>
        <div class="eft-info-banner-text">
            Each terminal is independently paired via Linkly Cloud or mx51 Cloud, allowing multiple physical or virtual PIN pads to operate simultaneously — for example, one for the Ticket Kiosk and another for the Donation POS.
        </div>
    </div>
    <a href="{{ route('eft.pairing-guide') }}" target="_blank" class="btn btn-outline-secondary btn-sm eft-info-banner-help"><i class="bi bi-question-circle me-1"></i>Pairing Guide</a>
</div>

@if($activeTerminals->isEmpty() && $inactiveTerminals->isEmpty())
<p class="text-muted">No terminals registered yet — add one below.</p>
@endif

@if($activeTerminals->isNotEmpty())
<div class="eft-section-heading-row">
    <div class="eft-section-heading">Active Terminals <span class="eft-section-count count-ok">{{ $activeTerminals->count() }}</span></div>
    @if($allOperational)
    <span class="eft-operational-flag"><span class="dot"></span> All systems operational</span>
    @endif
</div>
@foreach($activeTerminals as $terminal)
    @include('admin.partials.eft-terminal-card', ['terminal' => $terminal, 'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode, 'isSystemAdmin' => $isSystemAdmin])
@endforeach
@endif

@if($inactiveTerminals->isNotEmpty())
<div class="eft-section-heading-row">
    <div class="eft-section-heading">Inactive Terminals <span class="eft-section-count">{{ $inactiveTerminals->count() }}</span></div>
</div>
@foreach($inactiveTerminals as $terminal)
    @include('admin.partials.eft-terminal-card', ['terminal' => $terminal, 'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode, 'isSystemAdmin' => $isSystemAdmin])
@endforeach
@endif

@if($canManageRegistryLevel)
<div class="add-terminal-card">
    <div class="add-terminal-header"><i class="bi bi-plus-circle-fill"></i> Add New Terminal</div>
    <div class="add-terminal-sub">Add and configure a new EFT terminal. Each terminal requires a unique key and a label for easy identification.</div>
    <form action="{{ route('admin.eft-terminals.store') }}" method="POST" class="row g-3 align-items-start">
        @csrf
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Terminal Key</label>
            <input type="text" name="key" class="form-control rounded-3" placeholder="e.g. ticket-counter-2" maxlength="40" required>
            <div class="field-hint">Unique identifier. No spaces.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Label</label>
            <input type="text" name="label" class="form-control rounded-3" placeholder="e.g. Ticket Counter 2" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Provider</label>
            <select name="provider" class="form-select rounded-3">
                <option value="linkly">Linkly Cloud (PIN pad)</option>
                <option value="cba_sci">mx51 Cloud</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold d-none d-md-block">&nbsp;</label>
            <button type="submit" class="btn-add-terminal w-100 justify-content-center"><i class="bi bi-plus-lg"></i> Add Terminal</button>
        </div>
    </form>
</div>
@endif
