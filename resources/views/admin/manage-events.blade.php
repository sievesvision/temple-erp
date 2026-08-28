@extends('admin.layouts.app')

@section('title', 'Manage Events')

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
        padding: 12px 28px;
        border-radius: 40px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(184, 134, 58, 0.3);
        color: white;
    }
    .filter-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        overflow-x: auto;
        padding-bottom: 5px;
    }
    .filter-tab {
        background: white;
        border: 1px solid rgba(184, 134, 58, 0.1);
        color: #7b6b5a;
        padding: 8px 20px;
        border-radius: 40px;
        font-weight: 500;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .filter-tab:hover {
        background: #fdfbf9;
        border-color: #b8863a;
        color: #b8863a;
    }
    .filter-tab.active {
        background: #b8863a;
        color: white;
        border-color: #b8863a;
        box-shadow: 0 4px 12px rgba(184, 134, 58, 0.15);
    }
    .table-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .table-card .card-header {
        background: transparent;
        border-bottom: 1px solid #f0ece6;
        padding: 18px 24px;
        font-weight: 600;
        font-size: 1.05rem;
        color: #2d1f0e;
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
        color: #1e1e2a;
        font-weight: 500;
        vertical-align: middle;
    }
    .table-card .table tbody tr:hover {
        background: #faf8f5;
    }
    .btn-action-edit {
        background: rgba(184, 134, 58, 0.1);
        color: #b8863a;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action-edit:hover {
        background: #b8863a;
        color: white;
    }
    .btn-action-delete {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action-delete:hover {
        background: #dc3545;
        color: white;
    }
    .badge-status-upcoming {
        background: #e0f2fe;
        color: #0369a1;
    }
    .badge-status-ongoing {
        background: #fef3c7;
        color: #b45309;
    }
    .badge-status-completed {
        background: #d1fae5;
        color: #047857;
    }
    .badge-status-cancelled {
        background: #fee2e2;
        color: #b91c1c;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1><i class="bi bi-stars"></i>Manage Events</h1>
        <div class="subtitle">Create, reschedule, update status, and manage temple events</div>
    </div>
    <div>
        <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="bi bi-plus-lg"></i> Add Event
        </button>
    </div>
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

<!-- Filters -->
<div class="filter-tabs">
    <a href="{{ route('admin.events.index') }}" class="filter-tab {{ is_null($statusFilter) ? 'active' : '' }}">All Events</a>
    <a href="{{ route('admin.events.index', ['status' => 'Upcoming']) }}" class="filter-tab {{ $statusFilter === 'Upcoming' ? 'active' : '' }}">Upcoming</a>
    <a href="{{ route('admin.events.index', ['status' => 'Ongoing']) }}" class="filter-tab {{ $statusFilter === 'Ongoing' ? 'active' : '' }}">Ongoing</a>
    <a href="{{ route('admin.events.index', ['status' => 'Completed']) }}" class="filter-tab {{ $statusFilter === 'Completed' ? 'active' : '' }}">Completed</a>
    <a href="{{ route('admin.events.index', ['status' => 'Cancelled']) }}" class="filter-tab {{ $statusFilter === 'Cancelled' ? 'active' : '' }}">Cancelled</a>
</div>

<!-- Table Card -->
<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Events Schedule Directory</span>
        <span class="badge bg-secondary rounded-pill fw-medium">{{ $events->count() }} events shown</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Event Details</th>
                    <th>Date & Time</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $e)
                <tr>
                    <td><strong>EV{{ str_pad($e->event_id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $e->event_name }}</div>
                        <div class="text-muted small text-truncate" style="max-width: 250px;">{{ $e->description ?? 'No description provided' }}</div>
                    </td>
                    <td>
                        <div class="fw-semibold"><i class="bi bi-calendar-check text-warning me-1"></i>{{ date('d M Y', strtotime($e->event_date)) }}</div>
                        <div class="text-muted small"><i class="bi bi-clock me-1"></i>{{ date('g:i A', strtotime($e->start_time)) }} - {{ date('g:i A', strtotime($e->end_time)) }}</div>
                    </td>
                    <td>
                        <div class="text-dark"><i class="bi bi-geo-alt-fill text-muted me-1"></i>{{ $e->location }}</div>
                    </td>
                    <td>
                        <span class="badge px-3 py-2 rounded-pill fw-semibold badge-status-{{ strtolower($e->status) }}">
                            {{ $e->status }}
                        </span>
                    </td>
                    <td class="text-end">
                        <button class="btn-action-edit me-1" data-bs-toggle="modal" data-bs-target="#editEventModal{{ $e->event_id }}">
                            <i class="bi bi-pencil-square"></i> Edit
                        </button>
                        <form action="{{ route('admin.events.delete', $e->event_id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this event permanently?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-action-delete">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>

                <!-- EDIT EVENT MODAL -->
                <div class="modal fade" id="editEventModal{{ $e->event_id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg rounded-4">
                            <form action="{{ route('admin.events.update', $e->event_id) }}" method="POST">
                                @csrf
                                <div class="modal-header border-0 pb-0">
                                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit & Schedule Event</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body py-3">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Event Name</label>
                                        <input type="text" name="event_name" class="form-control rounded-3" value="{{ $e->event_name }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">URL Slug</label>
                                        <input type="text" name="slug" class="form-control rounded-3" value="{{ $e->slug }}">
                                        <div class="form-text">Public URL: <code>/events/{{ $e->slug }}</code>. Change this carefully — old links using the previous slug will stop working.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Description</label>
                                        <textarea name="description" class="form-control rounded-3" rows="3">{{ $e->description }}</textarea>
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label fw-semibold">Event Date</label>
                                            <input type="date" name="event_date" class="form-control rounded-3" value="{{ $e->event_date }}" required>
                                        </div>
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Start Time</label>
                                            <input type="time" name="start_time" class="form-control rounded-3" value="{{ date('H:i', strtotime($e->start_time)) }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">End Time</label>
                                            <input type="time" name="end_time" class="form-control rounded-3" value="{{ date('H:i', strtotime($e->end_time)) }}" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Location / Venue</label>
                                        <input type="text" name="location" class="form-control rounded-3" value="{{ $e->location }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Coordinator Emails (optional)</label>
                                        <input type="text" name="coordinator_emails" class="form-control rounded-3" placeholder="coordinator1@example.com, coordinator2@example.com" value="{{ $e->coordinator_emails }}">
                                        <div class="form-text">Comma-separated. These are CC'd on every donation receipt for this event.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Status</label>
                                        <select name="status" class="form-select rounded-3" required>
                                            <option value="Upcoming" {{ $e->status === 'Upcoming' ? 'selected' : '' }}>Upcoming</option>
                                            <option value="Ongoing" {{ $e->status === 'Ongoing' ? 'selected' : '' }}>Ongoing</option>
                                            <option value="Completed" {{ $e->status === 'Completed' ? 'selected' : '' }}>Completed</option>
                                            <option value="Cancelled" {{ $e->status === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Header Image Path (optional)</label>
                                        <input type="text" name="header_image" class="form-control rounded-3" placeholder="images/events/header_my-event.jpg" value="{{ $e->header_image }}">
                                        <div class="form-text">Upload the image to <code>public/images/events</code> and enter its relative path.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Flyer Image Path (optional)</label>
                                        <input type="text" name="flyer_image" class="form-control rounded-3" placeholder="images/events/my-event-flyer.png" value="{{ $e->flyer_image }}">
                                        <div class="form-text">Shown at the bottom of the event's public donation page.</div>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" name="show_donation_summary" id="show_donation_summary_{{ $e->event_id }}" class="form-check-input" value="1" {{ $e->show_donation_summary ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="show_donation_summary_{{ $e->event_id }}">Show "amount raised so far" on the public event page</label>
                                    </div>
                                    @include('admin.partials.event-donation-options-fields', ['options' => $e->donationOptions, 'formSuffix' => $e->event_id])
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
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-calendar2-x fs-1 d-block mb-2 text-warning"></i>
                        No events found matching this status filter.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- ADD EVENT MODAL -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="{{ route('admin.events.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-plus text-warning me-2"></i>Schedule New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Name</label>
                        <input type="text" name="event_name" class="form-control rounded-3" placeholder="e.g. Maha Shivaratri Utsav" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL Slug (optional)</label>
                        <input type="text" name="slug" class="form-control rounded-3" placeholder="Leave blank to auto-generate from name + date">
                        <div class="form-text">Shown in the public URL as <code>/events/&lt;slug&gt;</code>. Leave blank to auto-generate; set it yourself if you want a short, stable link that won't change even if you rename the event.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control rounded-3" rows="3" placeholder="Provide details about the pooja schedules, timings, and custom guidelines..."></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Event Date</label>
                            <input type="date" name="event_date" class="form-control rounded-3" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start Time</label>
                            <input type="time" name="start_time" class="form-control rounded-3" value="09:00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">End Time</label>
                            <input type="time" name="end_time" class="form-control rounded-3" value="12:00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Location / Venue</label>
                        <input type="text" name="location" class="form-control rounded-3" placeholder="e.g. Main Temple Hall" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Coordinator Emails (optional)</label>
                        <input type="text" name="coordinator_emails" class="form-control rounded-3" placeholder="coordinator1@example.com, coordinator2@example.com">
                        <div class="form-text">Comma-separated. These are CC'd on every donation receipt for this event.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Initial Status</label>
                        <select name="status" class="form-select rounded-3" required>
                            <option value="Upcoming" selected>Upcoming</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Header Image Path (optional)</label>
                        <input type="text" name="header_image" class="form-control rounded-3" placeholder="images/events/header_my-event.jpg">
                        <div class="form-text">Upload the image to <code>public/images/events</code> and enter its relative path.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Flyer Image Path (optional)</label>
                        <input type="text" name="flyer_image" class="form-control rounded-3" placeholder="images/events/my-event-flyer.png">
                        <div class="form-text">Shown at the bottom of the event's public donation page.</div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="show_donation_summary" id="show_donation_summary_new" class="form-check-input" value="1" checked>
                        <label class="form-check-label fw-semibold" for="show_donation_summary_new">Show "amount raised so far" on the public event page</label>
                    </div>
                    @include('admin.partials.event-donation-options-fields', ['options' => collect(), 'formSuffix' => 'new'])
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Schedule Event</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('page-js')
<script>
    $(document).ready(function() {
        console.log("Manage Events dashboard initialized");
    });
</script>
@endsection
