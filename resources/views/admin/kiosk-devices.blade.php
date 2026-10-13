@extends('admin.layouts.app')

@section('title', 'Kiosk Devices')

@section('page-css')
<style>
    .page-header {
        background: white; border-radius: 24px; padding: 24px 32px; margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06); box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .page-header h1 { font-weight: 700; font-size: 1.8rem; color: #2d1f0e; margin: 0; }
    .page-header h1 i { color: #b8863a; margin-right: 12px; }
    .page-header .subtitle { color: #7b6b5a; font-size: 0.95rem; margin-top: 4px; }

    {{-- One rich card per device rather than a dense table — unlike a register (name/status/
         last-seen), a kiosk also carries a module+event scope, a terminal, a payment-method
         set and two access URLs, which read far better stacked than squeezed into columns. --}}
    .kiosk-card {
        background: white; border-radius: 20px; border: 1px solid rgba(184, 134, 58, 0.08);
        box-shadow: 0 8px 24px rgba(0,0,0,0.03); padding: 22px 26px; margin-bottom: 16px;
    }
    .kiosk-card-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px; margin-bottom: 16px; }
    .kiosk-card-name { font-weight: 700; font-size: 1.15rem; color: #2d1f0e; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .kiosk-card-label { font-size: 0.82rem; color: #7b6b5a; margin-top: 2px; }
    .status-badge { font-size: 0.7rem; font-weight: 700; padding: 4px 11px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.03em; }
    .status-pending { background: #FEF3C7; color: #92400E; }
    .status-active { background: #D1FAE5; color: #065F46; }
    .status-disabled { background: #E5E7EB; color: #374151; }
    .status-revoked { background: #FEE2E2; color: #991B1B; }
    .kiosk-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-action-reset {
        background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: none; padding: 6px 14px;
        border-radius: 40px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-action-reset:hover { background: #8b5cf6; color: white; }
    .btn-action-success { background: rgba(16, 185, 129, 0.1); color: #0f9d6a; border: none; padding: 6px 14px; border-radius: 40px; font-weight: 600; font-size: 0.75rem; }
    .btn-action-success:hover { background: #0f9d6a; color: white; }
    .btn-action-danger {
        background: rgba(220, 38, 38, 0.1); color: #dc2626; border: none; padding: 6px 14px;
        border-radius: 40px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-action-danger:hover { background: #dc2626; color: white; }

    .kiosk-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px 16px; background: #faf8f5; border: 1px solid #f0ece6; border-radius: 10px; padding: 12px 16px; margin-bottom: 14px; }
    .kiosk-ddl { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.4px; color: #7b6b5a; margin-bottom: 2px; }
    .kiosk-ddv { font-weight: 600; color: #1e1e2a; font-size: 0.88rem; }
    .kiosk-pm-chip { display: inline-block; font-size: 0.68rem; font-weight: 700; background: rgba(184,134,58,0.1); color: #b8863a; border-radius: 20px; padding: 2px 9px; margin: 1px 3px 1px 0; }

    {{-- The kiosk isn't addressed by its own URL — every device shares the same /kiosk/pair
         and /kiosk/welcome paths, and a paired browser's own cookie is what distinguishes
         one kiosk from another — so this is the same two URLs on every card, shown plainly
         rather than implying a per-device link. --}}
    .kiosk-url-row { display: flex; flex-wrap: wrap; gap: 10px 20px; align-items: center; }
    .kiosk-url-item { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; }
    .kiosk-url-item .lbl { color: #7b6b5a; font-weight: 600; }
    .kiosk-url-item code { background: #faf8f5; border: 1px solid #f0ece6; border-radius: 6px; padding: 3px 9px; color: #2d1f0e; }
    .btn-copy-url { background: none; border: none; color: #b8863a; font-size: 0.78rem; font-weight: 600; padding: 2px 6px; }
    .btn-copy-url:hover { color: #8A6A1E; }

    code.pairing-code { font-size: 1.3rem; letter-spacing: 0.2em; background: #faf8f5; padding: 4px 12px; border-radius: 8px; }
</style>
@endsection

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="bi bi-tablet"></i>Kiosk Devices</h1>
        <div class="subtitle">Self-service kiosks — each one pairs to its own browser once, then stays scoped to whatever module/event/terminal it was configured for.</div>
    </div>
    <button type="button" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;" data-bs-toggle="modal" data-bs-target="#registerModal">
        <i class="bi bi-plus-lg me-1"></i>Register New Device
    </button>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert" style="background: #d1fae5; color: #065f46;">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert" style="background: #fee2e2; color: #991b1b;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@forelse($devices as $device)
<div class="kiosk-card">
    <div class="kiosk-card-top">
        <div>
            <div class="kiosk-card-name">
                {{ $device->name }}
                <span class="status-badge status-{{ $device->status }}">{{ $device->status }}</span>
            </div>
            <div class="kiosk-card-label">{{ $device->label ?: 'No label' }}</div>
        </div>
        <div class="kiosk-actions">
            <button type="button" class="btn-action-reset" onclick="generateCode('{{ route('admin.kiosk-devices.pairingCode', $device) }}', '{{ $device->name }}')"><i class="bi bi-qr-code"></i> Pairing Code</button>
            @if($device->status === 'active')
            <form method="POST" action="{{ route('admin.kiosk-devices.deactivate', $device) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn-action-reset"><i class="bi bi-pause-circle"></i> Deactivate</button>
            </form>
            @elseif($device->status === 'disabled')
            <form method="POST" action="{{ route('admin.kiosk-devices.activate', $device) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn-action-success"><i class="bi bi-play-circle"></i> Activate</button>
            </form>
            @endif
            @if($device->status !== 'revoked')
            <form method="POST" action="{{ route('admin.kiosk-devices.revoke', $device) }}" class="d-inline" onsubmit="return confirm('Revoke \'{{ $device->name }}\'? It will immediately lose access to every kiosk route.');">
                @csrf
                <button type="submit" class="btn-action-danger"><i class="bi bi-shield-x"></i> Revoke</button>
            </form>
            @endif
            <form method="POST" action="{{ route('admin.kiosk-devices.destroy', $device) }}" class="d-inline" onsubmit="return confirm('Permanently delete \'{{ $device->name }}\'?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-action-danger"><i class="bi bi-trash"></i> Delete</button>
            </form>
        </div>
    </div>

    <div class="kiosk-detail-grid">
        <div>
            <div class="kiosk-ddl">Module</div>
            <div class="kiosk-ddv">
                {{ $device->module ? ucfirst($device->module) : 'Not set' }}
                @if($device->module === 'donations' && $device->event)
                    &middot; {{ $device->event->event_name }}
                @endif
            </div>
        </div>
        <div>
            <div class="kiosk-ddl">EFT Terminal</div>
            <div class="kiosk-ddv">{{ $device->eftTerminal->label ?? 'Registry default' }}</div>
        </div>
        <div>
            <div class="kiosk-ddl">Last Active</div>
            <div class="kiosk-ddv">{{ $device->last_activity_at ? $device->last_activity_at->diffForHumans() : 'Never' }}</div>
        </div>
        <div>
            <div class="kiosk-ddl">Payment Methods</div>
            <div class="kiosk-ddv">
                @forelse($device->enabled_payment_methods ?? [] as $method)
                    <span class="kiosk-pm-chip">{{ $method }}</span>
                @empty
                    <span class="text-muted" style="font-weight:500;">Uses global default</span>
                @endforelse
            </div>
        </div>
    </div>

    <div class="kiosk-url-row">
        <div class="kiosk-url-item">
            <span class="lbl">Pairing URL</span>
            <code id="pairUrl-{{ $device->id }}">{{ url('/kiosk/pair') }}</code>
            <button type="button" class="btn-copy-url" onclick="copyKioskUrl('pairUrl-{{ $device->id }}', this)"><i class="bi bi-clipboard"></i></button>
        </div>
        <div class="kiosk-url-item">
            <span class="lbl">Kiosk URL (once paired)</span>
            <code id="welcomeUrl-{{ $device->id }}">{{ url('/kiosk/welcome') }}</code>
            <button type="button" class="btn-copy-url" onclick="copyKioskUrl('welcomeUrl-{{ $device->id }}', this)"><i class="bi bi-clipboard"></i></button>
        </div>
    </div>
</div>
@empty
<div class="kiosk-card text-center text-muted py-5">
    <i class="bi bi-tablet fs-1 d-block mb-2 text-warning"></i>
    No kiosk devices registered yet.
</div>
@endforelse

{{-- Register New Device --}}
<div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="{{ route('admin.kiosk-devices.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-tablet text-warning me-2"></i>Register New Kiosk Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name</label>
                        <input type="text" name="name" class="form-control rounded-3" required placeholder="e.g. Front Lobby Kiosk 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Label <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="label" class="form-control rounded-3" placeholder="e.g. Entrance">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Module</label>
                        <select name="module" class="form-select rounded-3" id="moduleSelect" onchange="document.getElementById('eventField').hidden = this.value !== 'donations';">
                            <option value="">Not set yet (admin-only until assigned)</option>
                            <option value="tickets">Tickets</option>
                            <option value="donations">Donations</option>
                        </select>
                    </div>
                    <div class="mb-3" id="eventField" hidden>
                        <label class="form-label fw-semibold">Event</label>
                        <select name="event_id" class="form-select rounded-3">
                            <option value="">— Choose an event —</option>
                            @foreach($events as $event)
                            <option value="{{ $event->event_id }}">{{ $event->event_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">EFT Terminal <span class="text-muted fw-normal">(optional — defaults to the registry default)</span></label>
                        <select name="eft_terminal_id" class="form-select rounded-3">
                            <option value="">Use registry default</option>
                            @foreach($eftTerminals as $terminal)
                            <option value="{{ $terminal->id }}">{{ $terminal->label }}{{ $terminal->is_default ? ' (default)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Payment Methods <span class="text-muted fw-normal">(optional — defaults to the global enabled methods)</span></label>
                        @foreach(['Cash','UPI','Bank Transfer','Cheque','EFT Terminal'] as $method)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="enabled_payment_methods[]" value="{{ $method }}" id="pm-{{ Str::slug($method) }}">
                            <label class="form-check-label" for="pm-{{ Str::slug($method) }}">{{ $method }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Register</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Pairing Code result --}}
<div class="modal fade" id="pairingCodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="pairingCodeDeviceName">Pairing Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div id="pairingCodeDisplay" style="font-size:2.2rem; letter-spacing:0.3em; font-weight:800; font-family:'Courier New',monospace; margin-bottom:12px; color:#2d1f0e;"></div>
                <p class="text-muted small mb-0">Valid for 10 minutes and shown only once — open <code>{{ url('/kiosk/pair') }}</code> on the physical device and enter this code.</p>
            </div>
        </div>
    </div>
</div>

<script>
    function generateCode(url, deviceName) {
        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) { alert(data.message || 'Could not generate a pairing code.'); return; }
                document.getElementById('pairingCodeDeviceName').textContent = 'Pairing Code — ' + deviceName;
                document.getElementById('pairingCodeDisplay').textContent = data.code;
                new bootstrap.Modal(document.getElementById('pairingCodeModal')).show();
            })
            .catch(function () { alert('Network error — please try again.'); });
    }

    function copyKioskUrl(elId, btn) {
        var text = document.getElementById(elId).textContent;
        navigator.clipboard.writeText(text).then(function () {
            var original = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            setTimeout(function () { btn.innerHTML = original; }, 1200);
        });
    }
</script>
@endsection
