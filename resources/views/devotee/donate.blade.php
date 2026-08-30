@extends('devotee.layouts.app')

@section('title', 'Make a Donation')

@section('header-title')
  <i class="bi bi-heart-pulse-fill text-warning"></i> Make a Donation
@endsection

@section('page-css')
<style>
  .donate-page-card {
    background: white;
    border-radius: 24px;
    border: 1px solid rgba(184, 134, 58, 0.15);
    box-shadow: 0 10px 30px rgba(184, 134, 58, 0.05);
    max-width: 640px;
    margin: 0 auto;
    padding: 2rem;
  }
  .donate-tabs-card { background: transparent; }
  .donate-method-tabs .nav-link {
    border-radius: 999px;
    color: #2d1f0e;
    font-weight: 600;
    font-size: .85rem;
    padding: .6rem 1.2rem;
    border: 1px solid #ebdcc5;
    margin-right: .5rem;
    background: #fff;
  }
  .donate-method-tabs .nav-link.active {
    background: {{ $temple['primary_color'] }};
    color: #fff;
    border-color: {{ $temple['primary_color'] }};
  }
  .donation-bank-card {
    background: #faf7f2;
    border-top: 5px solid {{ $temple['primary_color'] }};
    border-radius: 8px;
    padding: 1.5rem;
  }
  .bank-label { display: block; color: #7b6b5a; font-size: .74rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; margin-bottom: .35rem; }
  .bank-value { display: block; color: #2d1f0e; font-size: 1.1rem; }
  .donation-form label { font-size: .82rem; font-weight: 700; color: #2d1f0e; margin-bottom: .3rem; display: block; }
  .donation-form .form-control, .donation-form .form-select {
    border-color: #ebdcc5;
    border-radius: 10px;
    padding: .65rem .8rem;
  }
  .donation-form .btn {
    background: {{ $temple['primary_color'] }};
    border-color: {{ $temple['primary_color'] }};
    color: #fff;
    font-weight: 700;
    border-radius: 40px;
  }
  .donation-form .btn:hover {
    background: {{ $temple['dark_color'] }};
    border-color: {{ $temple['dark_color'] }};
    color: #fff;
  }
</style>
@endsection

@section('content')
<div class="donate-page-card">
  <h4 class="fw-bold mb-1"><i class="bi bi-heart-pulse-fill text-danger me-2"></i>Make a Donation</h4>
  <p class="text-muted small mb-4">Contribute towards the temple's maintenance, festivals, and community programs. This donation will be recorded against your devotee account.</p>

  @include('frontend.partials.donate-form', [
      'temple' => $temple,
      'events' => $events,
      'stripeEnabled' => $stripeEnabled,
      'formAction' => route('devotee.donate.post'),
      'formId' => 'devotee-donate-form',
      'prefillName' => $user->name,
      'prefillEmail' => $user->email,
      'lockContactFields' => true,
  ])
</div>
@endsection
