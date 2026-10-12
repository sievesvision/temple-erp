@extends('admin.layouts.app')

@section('title', 'Portals')

@section('page-css')
<style>
    .page-header {
        background: white; border-radius: 24px; padding: 24px 32px; margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06); box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .page-header h1 { font-weight: 700; font-size: 1.8rem; color: #2d1f0e; margin: 0; }
    .page-header h1 i { color: #b8863a; margin-right: 12px; }
    .page-header .subtitle { color: #7b6b5a; font-size: 0.95rem; margin-top: 4px; }
    .portal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
    .portal-card {
        background: white; border-radius: 20px; border: 1px solid rgba(184, 134, 58, 0.08);
        box-shadow: 0 8px 24px rgba(0,0,0,0.03); padding: 24px; text-decoration: none; color: inherit;
        display: flex; flex-direction: column; gap: 10px; transition: transform .15s, box-shadow .15s;
    }
    .portal-card:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(0,0,0,0.08); color: inherit; }
    .portal-card-icon {
        width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; background: color-mix(in srgb, #b8863a 14%, white); color: #b8863a;
    }
    .portal-card.featured .portal-card-icon { background: #6B0F1A; color: #FFF9EE; }
    .portal-card-name { font-weight: 700; font-size: 1.1rem; color: #2d1f0e; }
    .portal-card-meta { font-size: 0.82rem; color: #7b6b5a; }
    .portal-card-open { margin-top: auto; font-size: 0.82rem; font-weight: 700; color: #b8863a; display: flex; align-items: center; gap: 6px; }
    .section-label { font-size: 0.78rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: #8A6A1E; margin: 28px 0 14px; }
    .section-label:first-of-type { margin-top: 0; }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1><i class="bi bi-grid-3x3-gap-fill"></i>Portals</h1>
    <div class="subtitle">Every portal in one place — open one to manage it directly, as an admin, from here.</div>
</div>

<div class="section-label">Pooja Portal</div>
<div class="portal-grid">
    <a href="{{ route('admin.tickets.index') }}" class="portal-card featured">
        <div class="portal-card-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
        <div class="portal-card-name">Pooja Portal</div>
        <div class="portal-card-meta">Archana ticket sales &amp; pooja bookings</div>
        <div class="portal-card-open">Open Portal <i class="bi bi-arrow-right"></i></div>
    </a>
</div>

<div class="section-label">Event Portals</div>
<div class="portal-grid">
    @forelse($events as $event)
    <a href="{{ route('admin.events.portal', $event->slug ?? $event->event_id) }}" class="portal-card">
        <div class="portal-card-icon"><i class="bi bi-stars"></i></div>
        <div class="portal-card-name">{{ $event->event_name }}</div>
        <div class="portal-card-meta">
            {{ $event->event_date ? \Illuminate\Support\Carbon::parse($event->event_date)->format('d M Y') : 'Date TBC' }}
            <span class="badge {{ $event->status === 'Completed' ? 'bg-secondary' : 'bg-success' }} ms-1">{{ $event->status }}</span>
        </div>
        <div class="portal-card-open">Open Portal <i class="bi bi-arrow-right"></i></div>
    </a>
    @empty
    <p class="text-muted">No events yet.</p>
    @endforelse
</div>
@endsection
