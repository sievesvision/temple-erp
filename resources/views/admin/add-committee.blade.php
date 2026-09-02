@extends('admin.layouts.app')

@section('title', 'Add Committee Member')

@section('page-css')
<style>
    .form-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        padding: 40px;
        max-width: 900px;
        margin: 0 auto;
    }
    .form-card h2 {
        font-weight: 700;
        font-size: 1.5rem;
        color: #2d1f0e;
        margin-bottom: 24px;
        border-bottom: 1px solid #f0ece6;
        padding-bottom: 16px;
    }
    .btn-submit {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        border: none;
        padding: 12px 36px;
        border-radius: 40px;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(184, 134, 58, 0.3);
    }
</style>
@endsection

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <a href="{{ route('admin.committee.index') }}" class="text-warning text-decoration-none fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Committee List
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 p-3" style="background: #fee2e2; color: #991b1b;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        </div>
    @endif

    <div class="form-card">
        <h2><i class="bi bi-person-plus text-warning me-2"></i>Add New Committee Member</h2>

        <p class="text-muted mb-4">Committee members can manage donations, pooja bookings and events. They do not have access to settings, salaries, reports or other user management.</p>

        <form action="{{ route('admin.committee.store') }}" method="POST">
            @csrf

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control rounded-3 @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Committee Member Name" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control rounded-3 @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="e.g. committee@temple.com" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                    <input type="text" name="mobile" class="form-control rounded-3 @error('mobile') is-invalid @enderror" value="{{ old('mobile') }}" placeholder="10-digit number" required>
                    @error('mobile')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Position <span class="text-danger">*</span></label>
                    <input type="text" name="position" class="form-control rounded-3 @error('position') is-invalid @enderror" value="{{ old('position') }}" placeholder="e.g. Secretary, Treasurer" required>
                    @error('position')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-submit text-white fw-bold">Add Committee Member</button>
            </div>
        </form>
    </div>
</div>
@endsection
