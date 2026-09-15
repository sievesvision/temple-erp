@extends('admin.layouts.app')

@section('title', 'My Events')

@section('page-css')
<style>
    .page-header {
        background: white;
        border-radius: 24px;
        padding: 24px 32px;
        margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
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
    .my-event-card {
        background: white;
        border-radius: 20px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        padding: 24px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        transition: transform 0.15s;
    }
    .my-event-card:hover {
        transform: translateY(-2px);
    }
    .my-event-card h5 {
        font-weight: 700;
        color: #2d1f0e;
        margin: 0 0 6px;
    }
    .my-event-card .meta {
        color: #7b6b5a;
        font-size: 0.9rem;
    }
    .btn-open-console {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        border: none;
        padding: 12px 28px;
        border-radius: 40px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-open-console:hover {
        color: white;
        transform: translateY(-2px);
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1><i class="bi bi-calendar-check"></i>My Events</h1>
    <div class="subtitle">Events you've been assigned to coordinate — open the console for a full-screen table, quick entry, and dashboard.</div>
</div>

@forelse($events as $event)
<div class="my-event-card">
    <div>
        <h5>{{ $event->event_name }}</h5>
        <div class="meta">
            <i class="bi bi-calendar-event me-1"></i>{{ date('d M Y', strtotime($event->event_date)) }}
            @if($event->location) &middot; <i class="bi bi-geo-alt me-1"></i>{{ $event->location }} @endif
        </div>
    </div>
    <a href="{{ route('admin.events.console', $event->event_id) }}" class="btn-open-console">
        <i class="bi bi-arrows-fullscreen"></i> Open Console
    </a>
</div>
@empty
<div class="my-event-card justify-content-center text-center">
    <div>
        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-warning"></i>
        <p class="mb-0 text-muted">You haven't been assigned to coordinate any events yet.</p>
    </div>
</div>
@endforelse
@endsection
