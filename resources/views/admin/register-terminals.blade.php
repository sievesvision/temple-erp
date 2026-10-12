@extends('admin.layouts.app')

@section('title', $register->name . ' — Default Terminals')

@section('page-css')
<style>
    .page-header {
        background: white; border-radius: 24px; padding: 24px 32px; margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06); box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .page-header h1 { font-weight: 700; font-size: 1.8rem; color: #2d1f0e; margin: 0; }
    .page-header h1 i { color: #b8863a; margin-right: 12px; }
    .page-header .subtitle { color: #7b6b5a; font-size: 0.95rem; margin-top: 4px; }
    .back-link { display: inline-flex; align-items: center; gap: 6px; color: #7a6e63; text-decoration: none; font-size: 0.85rem; margin-bottom: 1rem; }
    .table-card {
        background: white; border-radius: 24px; border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02); overflow: hidden;
    }
    .table-card .card-header { background: transparent; border-bottom: 1px solid #f0ece6; padding: 18px 24px; font-weight: 600; font-size: 1.05rem; color: #2d1f0e; }
    .table-card .table thead th { font-weight: 600; color: #5a4e3e; border-bottom: 2px solid #f0ece6; padding: 14px 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; background: #faf8f5; }
    .table-card .table tbody td { padding: 14px 16px; border-bottom: 1px solid #f5f0ea; color: #1e1e2a; font-weight: 500; vertical-align: middle; }
</style>
@endsection

@section('content')
<a href="{{ route('admin.registers.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Registers</a>

<div class="page-header">
    <h1><i class="bi bi-credit-card-2-front-fill"></i>{{ $register->name }} — Default Terminals</h1>
    <div class="subtitle">The same register can serve different events or Ticket Sales over time — set which EFT terminal it should use as default for each. Leave as "Use the overall default" to fall back to the normal per-operator/registry default.</div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert" style="background: #d1fae5; color: #065f46;">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="table-card">
    <div class="card-header"><span>Contexts</span></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Context</th>
                    <th style="width: 320px;">Default EFT Terminal</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><i class="bi bi-ticket-perforated-fill me-2 text-warning"></i>Ticket Sales</td>
                    <td>
                        <form method="POST" action="{{ route('admin.registers.setTerminal', $register->id) }}" class="d-flex gap-2">
                            @csrf
                            <input type="hidden" name="context_type" value="tickets">
                            <select name="eft_terminal_id" class="form-select rounded-3" onchange="this.form.submit()">
                                <option value="">Use the overall default</option>
                                @foreach($pairedTerminals as $t)
                                    <option value="{{ $t->id }}" {{ (int) ($defaults['tickets:']->eft_terminal_id ?? 0) === $t->id ? 'selected' : '' }}>{{ $t->label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                </tr>
                @foreach($events as $event)
                <tr>
                    <td><i class="bi bi-calendar-heart me-2 text-warning"></i>{{ $event->event_name }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.registers.setTerminal', $register->id) }}" class="d-flex gap-2">
                            @csrf
                            <input type="hidden" name="context_type" value="event">
                            <input type="hidden" name="context_id" value="{{ $event->event_id }}">
                            <select name="eft_terminal_id" class="form-select rounded-3" onchange="this.form.submit()">
                                <option value="">Use the overall default</option>
                                @foreach($pairedTerminals as $t)
                                    <option value="{{ $t->id }}" {{ (int) ($defaults['event:' . $event->event_id]->eft_terminal_id ?? 0) === $t->id ? 'selected' : '' }}>{{ $t->label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
