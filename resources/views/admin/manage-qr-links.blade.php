@extends('admin.layouts.app')

@section('title', 'QR Links')

@section('page-css')
<style>
    .page-header {
        background: white;
        border-radius: 24px;
        padding: 24px 32px;
        margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }
    .page-header h1 {
        font-weight: 700;
        font-size: 1.8rem;
        color: #2d1f0e;
        margin: 0;
    }
    .page-header h1 i {
        color: #b8863a;
        margin-right: 12px;
    }
    .page-header .subtitle {
        color: #7b6b5a;
        font-size: 0.95rem;
        margin-top: 4px;
    }
    .btn-add {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 40px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn-add:hover { color: white; box-shadow: 0 8px 24px rgba(184, 134, 58, 0.3); }
    .table-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .table-card .table thead th {
        font-weight: 600;
        color: #5a4e3e;
        border-bottom: 2px solid #f0ece6;
        padding: 14px 16px;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #faf8f5;
    }
    .table-card .table tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #f5f0ea;
        vertical-align: middle;
    }
    .short-url-box { display: flex; align-items: center; gap: 8px; }
    .short-url-box code { background: #faf8f5; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; }
    .btn-copy { border: 0; background: none; color: #b8863a; font-size: 0.9rem; padding: 2px; }
    .btn-copy:hover { color: #2d1f0e; }
    .qr-thumb { width: 56px; height: 56px; border: 1px solid #f0ece6; border-radius: 8px; }
    .btn-action-edit {
        background: rgba(184, 134, 58, 0.1);
        color: #b8863a;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        text-decoration: none;
    }
    .btn-action-edit:hover { background: #b8863a; color: white; }
    .btn-action-delete {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
    }
    .btn-action-delete:hover { background: #dc3545; color: white; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1><i class="bi bi-qr-code"></i>QR Links</h1>
        <div class="subtitle">Print a short link once, then repoint it to a new target any time — the QR code never needs reprinting</div>
    </div>
    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addQrLinkModal">
        <i class="bi bi-plus-lg"></i> Add QR Link
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

@if ($errors->any())
    <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 p-3" style="background: #fee2e2; color: #991b1b;">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>QR</th>
                    <th>Label</th>
                    <th>Short URL</th>
                    <th>Currently Points To</th>
                    <th>Status</th>
                    <th>Scans</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($qrLinks as $link)
                <tr>
                    <td><img class="qr-thumb" src="{{ $link->qrImageUrl(120) }}" alt="QR code for {{ $link->slug }}"></td>
                    <td class="fw-semibold text-dark">{{ $link->label ?: '—' }}</td>
                    <td>
                        <div class="short-url-box">
                            <code>{{ $link->shortUrl() }}</code>
                            <button type="button" class="btn-copy copy-link-btn" data-copy="{{ $link->shortUrl() }}" title="Copy link"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </td>
                    <td class="text-muted small">{{ $link->target_url }}</td>
                    <td>
                        @if($link->is_active)
                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Active</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill">Inactive</span>
                        @endif
                    </td>
                    <td>{{ number_format($link->click_count) }}</td>
                    <td class="text-end">
                        <button class="btn-action-edit me-1" data-bs-toggle="modal" data-bs-target="#editQrLinkModal{{ $link->id }}">
                            <i class="bi bi-pencil-square"></i> Edit
                        </button>
                        <form action="{{ route('admin.qrlinks.delete', $link->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this QR link? Anyone who scans the printed code will get a 404.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-action-delete"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>

                <!-- EDIT QR LINK MODAL -->
                <div class="modal fade" id="editQrLinkModal{{ $link->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg rounded-4">
                            <form action="{{ route('admin.qrlinks.update', $link->id) }}" method="POST">
                                @csrf
                                <div class="modal-header border-0 pb-0">
                                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-qr-code text-warning me-2"></i>Edit QR Link</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body py-3">
                                    <div class="text-center mb-3">
                                        <img class="qr-thumb" style="width:100px;height:100px;" src="{{ $link->qrImageUrl(200) }}" alt="QR preview">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Label (for your reference)</label>
                                        <input type="text" name="label" class="form-control rounded-3" value="{{ $link->label }}" placeholder="e.g. 2026 Vinayagar Chathurthi Poster">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Short Link Slug</label>
                                        <div class="input-group">
                                            <span class="input-group-text">{{ url('/qr-') }}</span>
                                            <input type="text" name="slug" class="form-control rounded-end-3" value="{{ $link->slug }}" required>
                                        </div>
                                        <div class="form-text">Change this and the printed QR code stops working — only change it if you're printing a new one.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Link to an event (optional shortcut)</label>
                                        <select class="form-select rounded-3 qr-event-picker">
                                            <option value="">— Choose an event to auto-fill the target below —</option>
                                            @foreach($events as $event)
                                                <option value="/events/{{ $event->slug }}">{{ $event->event_name }} ({{ date('d M Y', strtotime($event->event_date)) }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Target URL</label>
                                        <input type="text" name="target_url" class="form-control rounded-3 qr-target-input" value="{{ $link->target_url }}" required placeholder="/events/my-event or https://...">
                                        <div class="form-text">Where this QR code redirects to right now. Update this each time you want to repoint it.</div>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" name="is_active" id="qr_active_{{ $link->id }}" class="form-check-input" value="1" {{ $link->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="qr_active_{{ $link->id }}">Active</label>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 pt-0">
                                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                                    <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-qr-code fs-1 d-block mb-2 text-warning"></i>
                        No QR links yet. Add one to get a printable short URL.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- ADD QR LINK MODAL -->
<div class="modal fade" id="addQrLinkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="{{ route('admin.qrlinks.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-qr-code text-warning me-2"></i>Add QR Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Label (for your reference)</label>
                        <input type="text" name="label" class="form-control rounded-3" placeholder="e.g. 2026 Vinayagar Chathurthi Poster">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Short Link Slug</label>
                        <div class="input-group">
                            <span class="input-group-text">{{ url('/qr-') }}</span>
                            <input type="text" name="slug" class="form-control rounded-end-3" placeholder="vinyagar-chathurthi" required>
                        </div>
                        <div class="form-text">Letters, numbers and hyphens only. This is the part that goes on the printed poster/banner.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Link to an event (optional shortcut)</label>
                        <select class="form-select rounded-3 qr-event-picker">
                            <option value="">— Choose an event to auto-fill the target below —</option>
                            @foreach($events as $event)
                                <option value="/events/{{ $event->slug }}">{{ $event->event_name }} ({{ date('d M Y', strtotime($event->event_date)) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target URL</label>
                        <input type="text" name="target_url" class="form-control rounded-3 qr-target-input" required placeholder="/events/my-event or https://...">
                        <div class="form-text">Where this QR code should redirect to.</div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" id="qr_active_new" class="form-check-input" value="1" checked>
                        <label class="form-check-label fw-semibold" for="qr_active_new">Active</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Create QR Link</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('page-js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.qr-event-picker').forEach(function (picker) {
            picker.addEventListener('change', function () {
                if (!this.value) { return; }
                var targetInput = this.closest('.modal-body').querySelector('.qr-target-input');
                if (targetInput) { targetInput.value = this.value; }
            });
        });

        document.querySelectorAll('.copy-link-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                navigator.clipboard.writeText(button.getAttribute('data-copy')).then(function () {
                    var icon = button.querySelector('i');
                    icon.className = 'bi bi-check2';
                    setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 1500);
                });
            });
        });
    });
</script>
@endsection
