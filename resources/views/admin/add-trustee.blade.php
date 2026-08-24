@extends('admin.layouts.app')

@section('title', 'Add Trustee')

@section('page-css')
<style>
    .form-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        padding: 40px;
        max-width: 800px;
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
        <a href="{{ route('admin.trustees.index') }}" class="text-warning text-decoration-none fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Trustees List
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 p-3" style="background: #fee2e2; color: #991b1b;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        </div>
    @endif

    <div class="form-card">
        <h2><i class="bi bi-person-plus text-warning me-2"></i>Add New Trustee</h2>
        
        <form action="{{ route('admin.trustees.store') }}" method="POST">
            @csrf
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control rounded-3 @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Enter Full Name" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control rounded-3 @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="e.g. trustee@temple.com" required>
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
                    <label class="form-label fw-semibold">Designation <span class="text-danger">*</span></label>
                    <input type="text" name="designation" class="form-control rounded-3 @error('designation') is-invalid @enderror" value="{{ old('designation') }}" placeholder="e.g. Chairman / Board Member" required>
                    @error('designation')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select rounded-3 @error('gender') is-invalid @enderror">
                        <option value="">Select Gender</option>
                        <option value="Male" {{ old('gender') === 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ old('gender') === 'Female' ? 'selected' : '' }}>Female</option>
                        <option value="Other" {{ old('gender') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('gender')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Date of Birth</label>
                    <input type="date" name="dob" class="form-control rounded-3 @error('dob') is-invalid @enderror" value="{{ old('dob') }}">
                    @error('dob')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Address</label>
                    <textarea name="address" class="form-control rounded-3 @error('address') is-invalid @enderror" rows="4" placeholder="Enter residential address">{{ old('address') }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mt-4 text-end">
                <button type="submit" class="btn-submit text-white fw-bold">Create Trustee Account</button>
            </div>
        </form>
    </div>
</div>
@endsection
