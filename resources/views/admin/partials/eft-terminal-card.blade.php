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
                <strong>{{ $terminal->label }}</strong>
                {{-- SCIPAIRING02's branding requirement — shown right next to the name rather
                     than behind a click, since a paired terminal has no expandable panel at all
                     any more (see $expandable below). Linkly gets its provider badge here
                     instead, same spot either way. --}}
                @if($isMx51)
                <img src="{{ asset('images/sci-logo.jpg') }}" alt="SCI" style="width:16px; height:16px; border-radius:3px; object-fit:cover; vertical-align:-2px;">
                <span class="text-muted small">Simple Cloud Integration</span>
                @else
                <span class="badge-pill badge-provider">LINKLY CLOUD</span>
                @endif
            </div>
            {{-- Everything a terminal is currently doing, one line, nothing repeated from the
                 name line above: TID/Mode (+ Pairing ID/Paired time once paired) for SCI, just
                 Mode for Linkly (it has none of those SCI-only fields) — then status, then
                 Default if applicable. --}}
            <div class="terminal-summary-meta">
                @if($isMx51)
                TID: {{ $terminal->sci_tid ?: '—' }} &nbsp;|&nbsp; Mode: {{ strtoupper($mode) }}
                @if($paired)
                &nbsp;|&nbsp; Pairing ID: {{ $terminal->sci_pairing_id }} &nbsp;|&nbsp; Paired: {{ $terminal->sci_paired_at ? $terminal->sci_paired_at->diffForHumans() : '—' }}
                @endif
                @else
                Mode: {{ strtoupper($mode) }}
                @endif
                &nbsp;|&nbsp; <span class="badge-pill {{ $paired ? 'badge-ok' : 'badge-bad' }}">{{ $paired ? 'Paired' : 'Not Paired' }}</span>
                @if($terminal->is_default)<span class="badge-pill badge-info">Default</span>@endif
            </div>
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
            <div class="d-flex flex-column gap-2">
                <form action="{{ route('admin.eft.pair') }}" method="POST" class="d-flex flex-wrap align-items-end gap-2">
                    @csrf
                    <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                    <div>
                        <label class="form-label small mb-1">Pairing Code</label>
                        <input type="text" name="pair_code" class="form-control rounded-3" placeholder="6-digit code from the terminal" maxlength="10" required style="max-width:200px;">
                    </div>
                    <div>
                        <label class="form-label small mb-1">Label <span class="text-muted">(optional)</span></label>
                        <input type="text" name="label" class="form-control rounded-3" placeholder="e.g. Front Counter" maxlength="255" style="max-width:220px;">
                    </div>
                    <button type="submit" class="btn btn-outline-primary">{{ $paired ? 'Re-pair' : 'Pair' }}</button>
                </form>
                @if($paired)
                <div class="d-flex gap-2">
                    <form action="{{ route('admin.eft.unpair') }}" method="POST" onsubmit="return confirm('Unpair this terminal? The terminal will need a fresh pairing code to reconnect.');">
                        @csrf
                        <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                        <button type="submit" class="btn btn-outline-danger">Unpair</button>
                    </form>
                    <form action="{{ route('admin.eft-terminals.checkConnection', $terminal) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Check Connection</button>
                    </form>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
