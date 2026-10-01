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
     current host already does, as they all share the same temple-branding palette) and
     js/eft-terminal-registry.js once (drives the Add Terminal wizard — see
     eft-terminal-add-wizard.blade.php).
     Every per-terminal card action (pair/test/unpair/rename/etc.) still redirects with plain
     redirect()->back(), so it always lands back on whichever of those pages it was actually
     submitted from — nothing here needs to know which one that is. The Add Terminal wizard is
     the one exception: it's AJAX-driven end to end (see js/eft-terminal-registry.js) and never
     navigates away, refreshing only the #eftTerminalsList container above on success. --}}
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

@if($isSystemAdmin)
@include('admin.partials.eft-terminal-transaction-limits')
@include('admin.partials.eft-terminal-receipt-settings')
@endif

<div id="eftTerminalsList">
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
    @include('admin.partials.eft-terminal-card', ['terminal' => $terminal, 'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode, 'isSystemAdmin' => $isSystemAdmin, 'canManageRegistryLevel' => $canManageRegistryLevel])
@endforeach
@endif

@if($inactiveTerminals->isNotEmpty())
<div class="eft-section-heading-row">
    <div class="eft-section-heading">Inactive Terminals <span class="eft-section-count">{{ $inactiveTerminals->count() }}</span></div>
</div>
@foreach($inactiveTerminals as $terminal)
    @include('admin.partials.eft-terminal-card', ['terminal' => $terminal, 'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode, 'isSystemAdmin' => $isSystemAdmin, 'canManageRegistryLevel' => $canManageRegistryLevel])
@endforeach
@endif
</div>

@if($canManageRegistryLevel)
@include('admin.partials.eft-terminal-add-wizard')
@endif
