@extends('admin.layouts.app')

@section('title', 'Add Staff Member')

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
        <a href="{{ route('admin.staff.index') }}" class="text-warning text-decoration-none fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Staff List
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 p-3" style="background: #fee2e2; color: #991b1b;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        </div>
    @endif

    <div class="form-card">
        <h2><i class="bi bi-person-plus text-warning me-2"></i>Add New Staff Member</h2>
        
        <form action="{{ route('admin.staff.store') }}" method="POST">
            @csrf
            
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control rounded-3 @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Rohansserigar" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control rounded-3 @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="e.g. staff@temple.com" required>
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
                    <input type="text" name="designation" class="form-control rounded-3 @error('designation') is-invalid @enderror" value="{{ old('designation') }}" placeholder="e.g. Manager / Cook" required>
                    @error('designation')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Monthly Salary <span class="text-danger">*</span></label>
                    <input type="number" name="salary" class="form-control rounded-3 @error('salary') is-invalid @enderror" value="{{ old('salary') }}" placeholder="Salary amount in INR" required step="0.01">
                    @error('salary')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Employment Status <span class="text-danger">*</span></label>
                    <select name="employment_status" class="form-select rounded-3 @error('employment_status') is-invalid @enderror" required>
                        <option value="Active" {{ old('employment_status') === 'Active' ? 'selected' : '' }}>Active</option>
                        <option value="On Leave" {{ old('employment_status') === 'On Leave' ? 'selected' : '' }}>On Leave</option>
                        <option value="Inactive" {{ old('employment_status') === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('employment_status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Joining Date <span class="text-danger">*</span></label>
                    <input type="date" name="joining_date" class="form-control rounded-3 @error('joining_date') is-invalid @enderror" value="{{ old('joining_date') ?? date('Y-m-d') }}" required>
                    @error('joining_date')
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
                    <textarea name="address" class="form-control rounded-3 @error('address') is-invalid @enderror" rows="3" placeholder="Enter residential address">{{ old('address') }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <h3 class="fw-bold text-warning mb-3 mt-4" style="font-size: 1.15rem; border-bottom: 1px solid #f0ece6; padding-bottom: 8px;">Bank Account details</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Account Holder Name</label>
                    <input type="text" name="account_holder_name" class="form-control rounded-3 @error('account_holder_name') is-invalid @enderror" value="{{ old('account_holder_name') }}" placeholder="As per bank passbook">
                    @error('account_holder_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Account Number</label>
                    <input type="text" name="account_number" class="form-control rounded-3 @error('account_number') is-invalid @enderror" value="{{ old('account_number') }}" placeholder="Account Number">
                    @error('account_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control rounded-3 @error('bank_name') is-invalid @enderror" value="{{ old('bank_name') }}" placeholder="e.g. State Bank of India">
                    @error('bank_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">IFSC Code</label>
                    <input type="text" name="ifsc_code" class="form-control rounded-3 @error('ifsc_code') is-invalid @enderror" value="{{ old('ifsc_code') }}" placeholder="e.g. SBIN0001234">
                    @error('ifsc_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Branch Name</label>
                    <input type="text" name="branch_name" class="form-control rounded-3 @error('branch_name') is-invalid @enderror" value="{{ old('branch_name') }}" placeholder="e.g. Main Branch">
                    @error('branch_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mt-5 text-end">
                <button type="submit" class="btn-submit text-white fw-bold">Create Staff Account</button>
            </div>
        </form>
    </div>
</div>
@endsection
