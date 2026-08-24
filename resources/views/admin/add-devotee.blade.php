@extends('admin.layouts.app')

@section('title', 'Add Devotee')

@section('page-css')
<style>
    /* Page Header */
    .page-header {
        background: white;
        border-radius: 24px;
        padding: 24px 32px;
        margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
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

    /* Form Card */
    .form-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        padding: 32px;
    }
    .form-card .section-title {
        font-weight: 700;
        color: #2d1f0e;
        border-bottom: 2px solid #b8863a;
        padding-bottom: 12px;
        margin-bottom: 24px;
        font-size: 1.1rem;
    }
    .form-card .section-title i {
        color: #b8863a;
        margin-right: 10px;
    }
    .form-card label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #5a4e3e;
        margin-bottom: 6px;
    }
    .form-card .form-control,
    .form-card .form-select {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 10px 14px;
        font-family: 'Inter', sans-serif;
        background: #faf8f5;
        transition: all 0.3s;
    }
    .form-card .form-control:focus,
    .form-card .form-select:focus {
        border-color: #b8863a;
        box-shadow: 0 0 0 3px rgba(184, 134, 58, 0.1);
        outline: none;
        background: white;
    }
    .form-card .form-control.is-invalid,
    .form-card .form-select.is-invalid {
        border-color: #dc3545;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
    }
    .form-card .invalid-feedback {
        font-size: 0.8rem;
        margin-top: 4px;
    }
    .form-card .required-field::after {
        content: ' *';
        color: #dc3545;
        font-weight: 700;
    }
    .form-card .help-text {
        font-size: 0.75rem;
        color: #7b6b5a;
        margin-top: 4px;
    }

    /* Buttons */
    .btn-submit {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        border: none;
        padding: 12px 40px;
        border-radius: 40px;
        font-weight: 600;
        transition: all 0.3s;
        font-size: 1rem;
    }
    .btn-submit:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 8px 24px rgba(184, 134, 58, 0.3);
        color: white;
    }
    .btn-submit i {
        margin-right: 8px;
    }
    .btn-cancel {
        background: #f0ece6;
        color: #1e1e2a;
        border: none;
        padding: 12px 40px;
        border-radius: 40px;
        font-weight: 600;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-cancel:hover {
        background: #e5ddd4;
        color: #1e1e2a;
    }

    /* Success Message */
    .alert-success-custom {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        border-radius: 16px;
        padding: 16px 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.5s ease-out;
    }
    .alert-success-custom i {
        font-size: 1.5rem;
    }
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Password Alert */
    .password-alert {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
        border-radius: 16px;
        padding: 16px 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.5s ease-out;
    }
    .password-alert i {
        font-size: 1.5rem;
    }
    .password-alert .password-text {
        font-weight: 700;
        font-family: monospace;
        font-size: 1.1rem;
        background: white;
        padding: 2px 12px;
        border-radius: 6px;
        border: 1px solid #fde68a;
    }

    /* Error Alert */
    .alert-error-custom {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
        border-radius: 16px;
        padding: 16px 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.5s ease-out;
    }
    .alert-error-custom i {
        font-size: 1.5rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .form-card {
            padding: 20px;
        }
        .page-header {
            padding: 20px;
        }
        .page-header h1 {
            font-size: 1.4rem;
        }
        .btn-submit,
        .btn-cancel {
            width: 100%;
            justify-content: center;
        }
        .form-actions {
            flex-direction: column;
        }
    }
</style>
@endsection

@section('content')
<!-- Page Header -->
<div class="page-header animate__animated animate__fadeIn">
    <div>
        <h1><i class="bi bi-person-plus-fill"></i> Add Devotee</h1>
        <div class="subtitle">
            <i class="bi bi-info-circle me-1"></i> Fill in the details to add a new devotee to the temple
        </div>
    </div>
    <a href="{{ route('admin.devotees.index') }}" class="btn-cancel">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
</div>

<!-- ===== MESSAGES SECTION ===== -->
@if(session('success'))
<div class="alert-success-custom mb-4 animate__animated animate__fadeIn">
    <i class="bi bi-check-circle-fill"></i>
    <div>
        <strong>Success!</strong> {{ session('success') }}
    </div>
</div>
@endif

@if(session('generated_password'))
<div class="password-alert mb-4 animate__animated animate__fadeIn">
    <i class="bi bi-key-fill"></i>
    <div>
        <strong>User's 6-digit password is:</strong> 
        <span class="password-text">{{ session('generated_password') }}</span>
        <span class="help-text ms-2">
            <i class="bi bi-info-circle"></i> Please share this with the devotee
        </span>
    </div>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert-error-custom mb-4 animate__animated animate__fadeIn">
    <i class="bi bi-exclamation-circle-fill"></i>
    <div>
        <strong>Error!</strong> {{ session('error') }}
    </div>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-4 animate__animated animate__fadeIn" 
     style="border-radius: 16px; border-left: 4px solid #dc3545;">
    <div class="d-flex align-items-start">
        <i class="bi bi-exclamation-triangle-fill me-2" style="font-size: 1.2rem; margin-top: 2px;"></i>
        <div>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1" style="padding-left: 20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
<!-- ===== END MESSAGES ===== -->

<!-- Form Card -->
<div class="form-card animate__animated animate__fadeInUp">
    <form method="POST" action="{{ route('admin.devotees.store') }}" id="devoteeForm">
        @csrf

        <!-- Personal Information -->
        <div class="section-title">
            <i class="bi bi-person"></i> Personal Information
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="required-field">Full Name</label>
                <input type="text" 
                       name="name" 
                       class="form-control @error('name') is-invalid @enderror" 
                       value="{{ old('name') }}"
                       placeholder="Enter full name"
                       required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="required-field">Email</label>
                <input type="email" 
                       name="email" 
                       class="form-control @error('email') is-invalid @enderror" 
                       value="{{ old('email') }}"
                       placeholder="Enter email address"
                       required>
                <div class="help-text">This will be used for login</div>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="required-field">Mobile Number</label>
                <input type="text" 
                       name="mobile" 
                       id="mobile"
                       class="form-control @error('mobile') is-invalid @enderror" 
                       value="{{ old('mobile') }}"
                       placeholder="Enter 10-digit mobile number"
                       maxlength="10"
                       required>
                @error('mobile')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label>Gender</label>
                <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                    <option value="">Select Gender</option>
                    <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                    <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>Other</option>
                </select>
                @error('gender')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label>Date of Birth</label>
                <input type="date" 
                       name="dob" 
                       class="form-control @error('dob') is-invalid @enderror" 
                       value="{{ old('dob') }}">
                @error('dob')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label>Gothra</label>
                <input type="text" 
                       name="gothra" 
                       class="form-control @error('gothra') is-invalid @enderror" 
                       value="{{ old('gothra') }}"
                       placeholder="e.g., Kashyapa, Bharadwaja">
                @error('gothra')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label>Nakshatra</label>
                <input type="text" 
                       name="nakshatra" 
                       class="form-control @error('nakshatra') is-invalid @enderror" 
                       value="{{ old('nakshatra') }}"
                       placeholder="e.g., Ashwini, Rohini">
                @error('nakshatra')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label>Membership Tier</label>
                <select name="membership_id" class="form-select @error('membership_id') is-invalid @enderror">
                    <option value="">None (Standard Devotee)</option>
                    @foreach($memberships as $membership)
                        <option value="{{ $membership->membership_id }}" {{ old('membership_id') == $membership->membership_id ? 'selected' : '' }}>
                            {{ $membership->membership_name }}
                        </option>
                    @endforeach
                </select>
                @error('membership_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="required-field">Verified Status</label>
                <select name="verified" class="form-select @error('verified') is-invalid @enderror" required>
                    <option value="0" {{ old('verified') == '0' ? 'selected' : '' }}>Not Verified</option>
                    <option value="1" {{ old('verified') == '1' ? 'selected' : '' }}>Verified</option>
                </select>
                @error('verified')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 mb-3">
                <label>Address</label>
                <textarea name="address" 
                          class="form-control @error('address') is-invalid @enderror" 
                          rows="2"
                          placeholder="Enter complete address">{{ old('address') }}</textarea>
                @error('address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <!-- Password Info -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-info" style="border-radius: 16px; background: #eff6ff; border-color: #bfdbfe; color: #1e40af;">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Password:</strong> A 6-digit password will be auto-generated and shown after successful submission.
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <hr>
                <div class="form-actions d-flex gap-3 justify-content-end">
                    <a href="{{ route('admin.devotees.index') }}" class="btn-cancel">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="bi bi-check-circle"></i> Add Devotee
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('page-js')
<script>
    $(document).ready(function() {
        // Auto-hide success message after 5 seconds
        setTimeout(function() {
            $('.alert-success-custom').fadeOut('slow');
        }, 5000);

        // Auto-hide password alert after 10 seconds
        setTimeout(function() {
            $('.password-alert').fadeOut('slow');
        }, 10000);

        // Mobile number validation (10 digits only)
        $('#mobile').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });

        // Form validation enhancement
        $('#devoteeForm').on('submit', function(e) {
            let isValid = true;
            
            $(this).find('.required-field').each(function() {
                const parent = $(this).closest('.row').find('.col-md-6, .col-md-4, .col-12');
                const input = parent.find('input, select, textarea');
                if (input.length && input.val().trim() === '') {
                    input.addClass('is-invalid');
                    isValid = false;
                } else {
                    input.removeClass('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $('.is-invalid:first').offset().top - 100
                }, 500);
                return false;
            }

            // Show loading state
            $('#submitBtn').html('<span class="spinner-border spinner-border-sm me-2"></span> Adding...');
            $('#submitBtn').prop('disabled', true);
        });

        $('.form-control, .form-select').on('input change', function() {
            $(this).removeClass('is-invalid');
        });
    });
</script>
@endsection
