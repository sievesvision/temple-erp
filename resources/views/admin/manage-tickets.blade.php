@extends('admin.layouts.app')

@section('title', 'Manage Tickets')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-ticket-perforated-fill me-2 text-warning"></i>Manage Tickets</h1>
            <p class="text-muted mb-0">The ticket catalog sold from the Ticket Kiosk — a standalone module, not tied to any event.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.tickets.orders') }}" class="btn btn-outline-secondary"><i class="bi bi-receipt me-1"></i>Ticket Sales</a>
            <a href="{{ route('admin.tickets.pos') }}" class="btn btn-success" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Open Ticket Kiosk</a>
            @if($canAdd)
            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addTicketModal"><i class="bi bi-plus-lg me-1"></i>Add Ticket Type</button>
            @endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th class="text-end">Price</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                        <tr>
                            <td class="fw-semibold">{{ $ticket->name }}</td>
                            <td class="text-muted small">{{ $ticket->description ?: '—' }}</td>
                            <td class="text-end">{{ number_format($ticket->price, 2) }}</td>
                            <td><span class="badge bg-{{ $ticket->status === 'Active' ? 'success' : 'secondary' }}">{{ $ticket->status }}</span></td>
                            <td class="text-end">
                                @if($canEdit)
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTicketModal{{ $ticket->id }}"><i class="bi bi-pencil-square"></i></button>
                                @endif
                                @if($canDelete)
                                <form action="{{ route('admin.tickets.delete', $ticket->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this ticket type?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">No ticket types yet — add one to get started.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if($canAdd)
<div class="modal fade" id="addTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.tickets.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Add Ticket Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description (optional)</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="mb-3"><label class="form-label">Price</label><input type="number" step="0.01" min="0.01" name="price" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Active" selected>Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Sort Order (optional)</label><input type="number" name="sort_order" class="form-control" value="0"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-warning">Add</button></div>
        </form>
    </div>
</div>
@endif

@if($canEdit)
@foreach($tickets as $ticket)
<div class="modal fade" id="editTicketModal{{ $ticket->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.tickets.update', $ticket->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Edit Ticket Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ $ticket->name }}" required></div>
                <div class="mb-3"><label class="form-label">Description (optional)</label><textarea name="description" class="form-control" rows="2">{{ $ticket->description }}</textarea></div>
                <div class="mb-3"><label class="form-label">Price</label><input type="number" step="0.01" min="0.01" name="price" class="form-control" value="{{ $ticket->price }}" required></div>
                <div class="mb-3"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Active" {{ $ticket->status === 'Active' ? 'selected' : '' }}>Active</option>
                        <option value="Inactive" {{ $ticket->status === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="{{ $ticket->sort_order }}"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-warning">Save</button></div>
        </form>
    </div>
</div>
@endforeach
@endif
@endsection
