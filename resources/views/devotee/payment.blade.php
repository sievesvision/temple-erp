@extends('devotee.layouts.app')

@section('title', $title)

@section('header-title')
  <i class="bi bi-shield-check text-warning"></i> Secure Payment Gateway
@endsection

@section('page-css')
<style>
  .payment-card {
    background: white;
    border-radius: 24px !important;
    border: 1px solid rgba(184, 134, 58, 0.15) !important;
    box-shadow: 0 10px 30px rgba(184, 134, 58, 0.05);
    max-width: 500px;
    margin: 0 auto;
    overflow: hidden;
  }
  .payment-header {
    background: linear-gradient(135deg, #2d1f0e, #1e1e2a);
    color: white;
    padding: 32px 24px;
    text-align: center;
    border-bottom: 3px solid #b8863a;
  }
  .payment-body {
    padding: 32px;
    text-align: center;
  }
  .qr-frame {
    background: #faf6f0;
    border: 2px dashed rgba(184, 134, 58, 0.25);
    border-radius: 20px;
    padding: 24px;
    margin: 24px 0;
    display: inline-block;
  }
  .payment-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px dashed #eeece7;
    font-size: 0.95rem;
  }
  .payment-detail-row:last-child {
    border-bottom: none;
  }
  .payment-detail-label {
    color: #7b6b5a;
    font-weight: 500;
  }
  .payment-detail-value {
    color: #2d1f0e;
    font-weight: 700;
  }
</style>
@endsection

@section('content')
<div class="container py-4">
  <div class="payment-card">
    <div class="payment-header">
      <h4 class="fw-bold mb-1"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Secure UPI Payment</h4>
      <p class="small text-white-50 mb-0">Shree Mandir Temple Management Portal</p>
    </div>
    <div class="payment-body">
      <h5 class="fw-semibold text-muted mb-3">{{ $title }}</h5>
      
      <div class="payment-details mb-4 text-start">
        <div class="payment-detail-row">
          <span class="payment-detail-label">Payment Type</span>
          <span class="payment-detail-value text-uppercase">{{ $type }}</span>
        </div>
        <div class="payment-detail-row">
          <span class="payment-detail-label">Description</span>
          <span class="payment-detail-value">{{ $remarks }}</span>
        </div>
        <div class="payment-detail-row">
          <span class="payment-detail-label">Total Amount</span>
          <span class="payment-detail-value text-warning fs-5">₹{{ number_format($amount, 2) }}</span>
        </div>
      </div>

      <p class="text-muted small mb-0">Scan the QR code using any UPI App (GPay, PhonePe, Paytm, BHIM, etc.) to pay securely.</p>

      <div class="qr-frame">
        <img src="{{ $qrCodeUrl }}" alt="UPI QR Code" class="img-fluid bg-white p-2 rounded-3 shadow-sm" style="width: 200px; height: 200px;">
        <div class="mt-2 small fw-bold text-dark"><i class="bi bi-qr-code-scan me-1 text-warning"></i> rohandevadigapithrodi-1@oksbi</div>
      </div>

      <div class="payment-actions">
        <form action="{{ route('devotee.payment.process') }}" method="POST">
          @csrf
          <input type="hidden" name="type" value="{{ $type }}">
          <input type="hidden" name="booking_ids" value="{{ $request->get('booking_ids') }}">
          <input type="hidden" name="amount" value="{{ $amount }}">
          <input type="hidden" name="purpose" value="{{ $request->get('purpose') }}">
          <input type="hidden" name="membership_id" value="{{ $request->get('membership_id') }}">

          <button type="submit" class="btn btn-warning w-100 rounded-pill fw-semibold py-2 mb-2 shadow-sm" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none; color: white;">
            <i class="bi bi-check-circle-fill me-1"></i> Payment Success
          </button>
        </form>

        <a href="{{ route('devotee.dashboard') }}" class="btn btn-light w-100 rounded-pill fw-semibold py-2 text-muted" style="background: #fdfaf2; border: 1px solid rgba(184, 134, 58, 0.15);">
          Cancel Payment
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
