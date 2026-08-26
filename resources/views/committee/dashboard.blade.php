@extends('admin.layouts.app')

@section('title', 'Committee Dashboard')

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
    .stat-card {
        background: white;
        border-radius: 20px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        padding: 28px;
        text-decoration: none;
        display: block;
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
    }
    .stat-card i {
        font-size: 1.8rem;
        color: #b8863a;
    }
    .stat-card .value {
        font-size: 1.6rem;
        font-weight: 700;
        color: #2d1f0e;
        margin: 10px 0 4px;
    }
    .stat-card .label {
        color: #7b6b5a;
        font-size: 0.9rem;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1><i class="bi bi-people-fill"></i>Committee Dashboard</h1>
    <div class="subtitle">Manage donations, pooja bookings and temple events.</div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <a href="{{ route('admin.donations.index') }}" class="stat-card">
            <i class="bi bi-wallet2"></i>
            <div class="value">{{ $temple['currency'] }} {{ number_format($donationsTotal, 2) }}</div>
            <div class="label">Total Donations Received</div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('admin.bookings.index') }}" class="stat-card">
            <i class="bi bi-calendar-event"></i>
            <div class="value">{{ $bookingsCount }}</div>
            <div class="label">Pooja Bookings</div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('admin.events.index') }}" class="stat-card">
            <i class="bi bi-stars"></i>
            <div class="value">{{ $upcomingEventsCount }}</div>
            <div class="label">Upcoming Events</div>
        </a>
    </div>
</div>
@endsection
