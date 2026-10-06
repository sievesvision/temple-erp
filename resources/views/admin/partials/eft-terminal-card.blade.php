@php
    $detailId = 'terminal-detail-' . $terminal->id;
    $isMx51 = $terminal->provider === 'cba_sci';
    $paired = $terminal->isPairedFor($linklyMode);
    $mode = $isMx51 ? $cbaSciMode : $linklyMode;
    // Expanded by default right after you've just added, paired, tested or unpaired THIS
    // specific terminal — see the various controller actions' ->with('expandTerminalId', ...)
    // — so the result of what you just did is immediately visible instead of collapsed away.
    $startExpanded = (int) session('expandTerminalId') === $terminal->id;
    $canAct = $canManageRegistryLevel ?? false;
    $canUnpair = $isMx51 && $paired;
    // A paired SCI terminal has nothing left to configure here — Unpair lives in the "…" menu —
    // so it gets no expand/collapse at all. Only an unpaired SCI terminal (still needs a
    // pairing code) or a Linkly terminal (pairing code + Check Connection, always) keep it.
    $expandable = !($isMx51 && $paired);
@endphp
<div class="terminal-card{{ $paired ? '' : ' terminal-card-inactive' }}">
    <div class="terminal-summary-row"
         @if($expandable) role="button" data-bs-toggle="collapse" data-bs-target="#{{ $detailId }}" aria-expanded="{{ $startExpanded ? 'true' : 'false' }}" aria-controls="{{ $detailId }}" @endif>
        <span class="terminal-icon"><img src="{{ asset('images/eft_terminal_icon.png') }}" alt=""></span>

        <div class="terminal-summary-main">
            <div class="terminal-summary-name">
                {{-- CbaSciService::pair() already writes the TID straight into `label` when no
                     pairing nickname was ever given, so there's nothing to fall back to here —
                     only skip the "(TID: ...)" annotation when it would just repeat the label. --}}
                <strong>{{ $terminal->label }}</strong>
                @if($isMx51)
                @if($terminal->sci_tid && (string) $terminal->sci_tid !== (string) $terminal->label)<span class="text-muted small">(TID: {{ $terminal->sci_tid }})</span>@endif
                @else
                <span class="text-muted small">({{ $terminal->key }})</span>
                @endif
            </div>
            <div class="terminal-summary-meta">{{ $isMx51 ? 'TID: ' . ($terminal->sci_tid ?: '—') : 'Key: ' . $terminal->key }} &nbsp;|&nbsp; Provider: {{ $isMx51 ? 'SCI' : 'Linkly Cloud' }} &nbsp;|&nbsp; Mode: {{ strtoupper($mode) }}</div>
            <div class="terminal-summary-badges">
                <span class="badge-pill badge-provider">{{ $isMx51 ? 'SCI' : 'LINKLY CLOUD' }}</span>
                @if($terminal->is_default)<span class="badge-pill badge-info">Default</span>@endif
                <span class="badge-pill {{ $paired ? 'badge-ok' : 'badge-bad' }}">{{ $paired ? 'Paired' : 'Not Paired' }}</span>
            </div>
            {{-- SCIPAIRING02's branding requirement, shown directly here rather than behind a
                 click — a paired terminal has no expandable panel at all any more (see
                 $expandable above), so this is the only place left for it to appear. --}}
            @if($isMx51)
            <div class="d-flex align-items-center gap-2 mt-1" style="font-size:0.82rem;">
                <img src="{{ asset('images/sci-logo.jpg') }}" alt="SCI" style="width:18px; height:18px; border-radius:4px; object-fit:cover;">
                <span class="text-muted">Simple Cloud Integration</span>
            </div>
            @endif
        </div>

        {{-- No separate Settings button, and no separate Connection/Status column either: the
             Paired/Not Paired badge above already reflects a live mx51 check on every page load
             (see EftTerminalRegistryView::selfHealSciPairings()) — a second "Status" pill next
             to it only ever repeated the exact same true/false. "Set as default"/Unpair/
             "Remove" live in the "…" menu; anyone trusted to add/pair a terminal is trusted to
             change or remove any terminal in the registry too, since it's still one shared,
             global list, not scoped per event/Tickets (see EftTerminalAccess's own docblock). --}}
        @if($canAct && (!$terminal->is_default || $canUnpair))
        <div class="terminal-summary-actions" onclick="event.stopPropagation();">
            <div class="dropdown">
                <button type="button" class="btn-terminal-more" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if(!$terminal->is_default)
                    <li>
                        <form action="{{ route('admin.eft-terminals.setDefault', $terminal) }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item">Set as default</button>
                        </form>
                    </li>
                    @endif
                    @if($canUnpair)
                    <li>
                        <form action="{{ route('admin.cba-sci.unpair') }}" method="POST" onsubmit="return confirm('Unpair this terminal? The terminal will need a fresh pairing code to reconnect.');">
                            @csrf
                            <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                            <button type="submit" class="dropdown-item">Unpair</button>
                        </form>
                    </li>
                    @endif
                    @if(!$terminal->is_default)
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('admin.eft-terminals.destroy', $terminal) }}" method="POST" onsubmit="return confirm('Remove this terminal?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="dropdown-item text-danger">Remove</button>
                        </form>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
        @endif
    </div>

    {{-- Just the pairing controls — everything else here (label, TID/key, provider, mode) is
         already shown on the summary row above, and Set default/Unpair/Remove live in its "…"
         menu. Only rendered at all when $expandable — a paired SCI terminal has nothing left to
         show here. --}}
    @if($expandable)
    <div class="collapse{{ $startExpanded ? ' show' : '' }}" id="{{ $detailId }}">
        <div class="terminal-detail-body">
            @if($isMx51)
            @include('admin.partials.cba-sci-pairing', ['terminal' => $terminal])
            @else
            <div class="d-flex flex-wrap align-items-end gap-2">
                <form action="{{ route('admin.eft.pair') }}" method="POST" class="d-flex align-items-end gap-2">
                    @csrf
                    <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                    <div>
                        <label class="form-label small mb-1">Pairing Code</label>
                        <input type="text" name="pair_code" class="form-control rounded-3" placeholder="6-digit code from the terminal" maxlength="10" required style="max-width:220px;">
                    </div>
                    <button type="submit" class="btn btn-outline-primary">{{ $paired ? 'Re-pair' : 'Pair' }}</button>
                </form>
                @if($paired)
                <form action="{{ route('admin.eft-terminals.checkConnection', $terminal) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Check Connection</button>
                </form>
                @endif
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
