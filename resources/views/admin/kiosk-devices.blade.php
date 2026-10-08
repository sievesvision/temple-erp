<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kiosk Devices</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <style>
        :root {
            --maroon: #6B0F1A; --maroon-dark: #4A0A12; --gold: #C89B3C; --gold-hover: #A67C2B;
            --cream: #F9F3E7; --border: #E5E7EB; --text-primary: #1F2A37; --text-secondary: #6B7280;
            --success: #10B981; --error: #EF4444;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #FAFAF8; color: var(--text-primary); }
        .topbar { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 16px 24px; display: flex; align-items: center; gap: 14px; }
        .topbar h1 { font-size: 1.3rem; font-weight: 800; color: var(--gold); margin: 0; flex: 1; }
        .topbar-btn { background: rgba(255,255,255,0.12); border: none; color: white; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; text-decoration: none; }
        .topbar-btn:hover { background: rgba(255,255,255,0.22); color: white; }
        .body-wrap { padding: 24px clamp(16px, 3vw, 40px) 60px; max-width: 1200px; margin: 0 auto; }
        .status-badge { font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.03em; }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-active { background: #D1FAE5; color: #065F46; }
        .status-disabled { background: #E5E7EB; color: #374151; }
        .status-revoked { background: #FEE2E2; color: #991B1B; }
        .device-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; margin-bottom: 12px; }
        .device-meta { font-size: 0.82rem; color: var(--text-secondary); }
        .btn-maroon { background: var(--maroon); color: #fff; border: none; }
        .btn-maroon:hover { background: var(--maroon-dark); color: #fff; }
    </style>
</head>
<body>
    <header class="topbar">
        <h1><i class="bi bi-tablet me-2"></i>Kiosk Devices</h1>
        <button type="button" class="topbar-btn" data-bs-toggle="modal" data-bs-target="#registerModal"><i class="bi bi-plus-lg me-1"></i>Register New Device</button>
    </header>

    <div class="body-wrap">
        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @forelse($devices as $device)
        <div class="device-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <strong>{{ $device->name }}</strong>
                        <span class="status-badge status-{{ $device->status }}">{{ $device->status }}</span>
                    </div>
                    <div class="device-meta mt-1">
                        {{ $device->label ?: 'No label' }} &middot;
                        Module: {{ $device->module ? ucfirst($device->module) : 'Not set' }}
                        @if($device->module === 'donations' && $device->event)
                            ({{ $device->event->event_name }})
                        @endif
                        &middot;
                        Terminal: {{ $device->eftTerminal->label ?? 'Registry default' }}
                        &middot;
                        Last active: {{ $device->last_activity_at ? $device->last_activity_at->diffForHumans() : 'Never' }}
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="generateCode({{ $device->id }}, '{{ $device->name }}')"><i class="bi bi-qr-code me-1"></i>Pairing Code</button>
                    @if($device->status === 'active')
                    <form method="POST" action="{{ route('admin.kiosk-devices.deactivate', $device) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Deactivate</button>
                    </form>
                    @elseif($device->status === 'disabled')
                    <form method="POST" action="{{ route('admin.kiosk-devices.activate', $device) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                    </form>
                    @endif
                    @if($device->status !== 'revoked')
                    <form method="POST" action="{{ route('admin.kiosk-devices.revoke', $device) }}" class="d-inline" onsubmit="return confirm('Revoke \'{{ $device->name }}\'? It will immediately lose access to every kiosk route.');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('admin.kiosk-devices.destroy', $device) }}" class="d-inline" onsubmit="return confirm('Permanently delete \'{{ $device->name }}\'?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <p class="text-muted">No kiosk devices registered yet.</p>
        @endforelse
    </div>

    {{-- Register New Device --}}
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.kiosk-devices.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Register New Kiosk Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Front Lobby Kiosk 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Label <span class="text-muted">(optional)</span></label>
                        <input type="text" name="label" class="form-control" placeholder="e.g. Entrance">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Module</label>
                        <select name="module" class="form-select" id="moduleSelect" onchange="document.getElementById('eventField').hidden = this.value !== 'donations';">
                            <option value="">Not set yet (admin-only until assigned)</option>
                            <option value="tickets">Tickets</option>
                            <option value="donations">Donations</option>
                        </select>
                    </div>
                    <div class="mb-3" id="eventField" hidden>
                        <label class="form-label">Event</label>
                        <select name="event_id" class="form-select">
                            <option value="">— Choose an event —</option>
                            @foreach($events as $event)
                            <option value="{{ $event->event_id }}">{{ $event->event_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">EFT Terminal <span class="text-muted">(optional — defaults to the registry default)</span></label>
                        <select name="eft_terminal_id" class="form-select">
                            <option value="">Use registry default</option>
                            @foreach($eftTerminals as $terminal)
                            <option value="{{ $terminal->id }}">{{ $terminal->label }}{{ $terminal->is_default ? ' (default)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Payment Methods <span class="text-muted">(optional — defaults to the global enabled methods)</span></label>
                        @foreach(['Cash','UPI','Bank Transfer','Cheque','EFT Terminal'] as $method)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="enabled_payment_methods[]" value="{{ $method }}" id="pm-{{ Str::slug($method) }}">
                            <label class="form-check-label" for="pm-{{ Str::slug($method) }}">{{ $method }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-maroon">Register</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Pairing Code result --}}
    <div class="modal fade" id="pairingCodeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pairingCodeDeviceName">Pairing Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="pairingCodeDisplay" style="font-size:2.2rem; letter-spacing:0.3em; font-weight:800; font-family:'Courier New',monospace; margin-bottom:12px;"></div>
                    <p class="text-muted small mb-0">Valid for 10 minutes and shown only once — enter it at <code>/kiosk/pair</code> on the physical device.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        function generateCode(deviceId, deviceName) {
            fetch('/admin/kiosk-devices/' + deviceId + '/pairing-code', {
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
    </script>
</body>
</html>
