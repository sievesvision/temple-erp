@extends('admin.layouts.app')

@section('title', 'Add Priest')

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
        <h1><i class="bi bi-person-plus-fill"></i> Add Priest</h1>
        <div class="subtitle">
            <i class="bi bi-info-circle me-1"></i> Fill in the details to add a new priest to the temple
        </div>
    </div>
    <a href="{{ url('/admin/manage-priests') }}" class="btn-cancel">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
</div>

<!-- ===== MESSAGES SECTION ===== -->
<!-- Success Message -->
@if(session('success'))
<div class="alert-success-custom mb-4 animate__animated animate__fadeIn">
    <i class="bi bi-check-circle-fill"></i>
    <div>
        <strong>Success!</strong> {{ session('success') }}
    </div>
</div>
@endif

<!-- Password Alert (when generated) -->
@if(session('generated_password'))
<div class="password-alert mb-4 animate__animated animate__fadeIn">
    <i class="bi bi-key-fill"></i>
    <div>
        <strong>User's 6-digit password is:</strong> 
        <span class="password-text">{{ session('generated_password') }}</span>
        <span class="help-text ms-2">
            <i class="bi bi-info-circle"></i> Please share this with the priest
        </span>
    </div>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Error Message -->
@if(session('error'))
<div class="alert-error-custom mb-4 animate__animated animate__fadeIn">
    <i class="bi bi-exclamation-circle-fill"></i>
    <div>
        <strong>Error!</strong> {{ session('error') }}
    </div>
</div>
@endif

<!-- Validation Errors -->
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
    <form method="POST" action="{{ url('/admin/priest/store') }}" id="priestForm">
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
                <label>Emergency Contact</label>
                <input type="text" 
                       name="emergency_contact" 
                       class="form-control @error('emergency_contact') is-invalid @enderror" 
                       value="{{ old('emergency_contact') }}"
                       placeholder="Emergency contact number"
                       maxlength="10">
                @error('emergency_contact')
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

        <!-- Professional Information -->
        <div class="section-title mt-4">
            <i class="bi bi-briefcase"></i> Professional Information
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="required-field">Specialization</label>
                <input type="text" 
                       name="specialization" 
                       class="form-control @error('specialization') is-invalid @enderror" 
                       value="{{ old('specialization') }}"
                       placeholder="e.g., Vedic, Homa, Astrology"
                       required>
                @error('specialization')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label>Experience (Years)</label>
                <input type="number" 
                       name="experience_years" 
                       class="form-control @error('experience_years') is-invalid @enderror" 
                       value="{{ old('experience_years') }}"
                       placeholder="Years of experience"
                       min="0"
                       max="50">
                @error('experience_years')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label>Qualification</label>
                <input type="text" 
                       name="qualification" 
                       class="form-control @error('qualification') is-invalid @enderror" 
                       value="{{ old('qualification') }}"
                       placeholder="e.g., Veda Vidya, M.A. Sanskrit">
                @error('qualification')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="required-field">Monthly Salary</label>
                <input type="number" 
                       name="monthly_salary" 
                       class="form-control @error('monthly_salary') is-invalid @enderror" 
                       value="{{ old('monthly_salary') }}"
                       placeholder="Enter monthly salary"
                       required
                       step="0.01"
                       min="0">
                @error('monthly_salary')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label>Employment Status</label>
                <select name="employment_status" class="form-select @error('employment_status') is-invalid @enderror">
                    <option value="Active" {{ old('employment_status') == 'Active' ? 'selected' : '' }}>Active</option>
                    <option value="On Leave" {{ old('employment_status') == 'On Leave' ? 'selected' : '' }}>On Leave</option>
                    <option value="Inactive" {{ old('employment_status') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="Retired" {{ old('employment_status') == 'Retired' ? 'selected' : '' }}>Retired</option>
                </select>
                @error('employment_status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label>Current Status</label>
                <select name="current_status" class="form-select @error('current_status') is-invalid @enderror">
                    <option value="Offline" {{ old('current_status') == 'Offline' ? 'selected' : '' }}>Offline</option>
                    <option value="Online" {{ old('current_status') == 'Online' ? 'selected' : '' }}>Online</option>
                    <option value="Busy" {{ old('current_status') == 'Busy' ? 'selected' : '' }}>Busy</option>
                </select>
                <div class="help-text">Default status is Offline</div>
                @error('current_status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="required-field">Joining Date</label>
                <input type="date" 
                       name="joining_date" 
                       class="form-control @error('joining_date') is-invalid @enderror" 
                       value="{{ old('joining_date', date('Y-m-d')) }}"
                       required>
                @error('joining_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <!-- Banking Information -->
        <div class="section-title mt-4">
            <i class="bi bi-bank"></i> Banking Information
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Account Holder Name</label>
                <input type="text" 
                       name="account_holder_name" 
                       class="form-control @error('account_holder_name') is-invalid @enderror" 
                       value="{{ old('account_holder_name') }}"
                       placeholder="Name as per bank account">
                @error('account_holder_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label>Account Number</label>
                <input type="text" 
                       name="account_number" 
                       class="form-control @error('account_number') is-invalid @enderror" 
                       value="{{ old('account_number') }}"
                       placeholder="Bank account number">
                @error('account_number')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label>Bank Name</label>
                <input type="text" 
                       name="bank_name" 
                       class="form-control @error('bank_name') is-invalid @enderror" 
                       value="{{ old('bank_name') }}"
                       placeholder="Name of the bank">
                @error('bank_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label>Branch Name</label>
                <input type="text" 
                       name="branch_name" 
                       class="form-control @error('branch_name') is-invalid @enderror" 
                       value="{{ old('branch_name') }}"
                       placeholder="Bank branch name">
                @error('branch_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label>IFSC Code</label>
                <input type="text" 
                       name="ifsc_code" 
                       class="form-control @error('ifsc_code') is-invalid @enderror" 
                       value="{{ old('ifsc_code') }}"
                       placeholder="IFSC Code"
                       maxlength="11">
                @error('ifsc_code')
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
                    <a href="{{ url('/admin/manage-priests') }}" class="btn-cancel">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="bi bi-check-circle"></i> Add Priest
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

        // Auto-hide error messages after 7 seconds
        setTimeout(function() {
            $('.alert-error-custom, .alert-danger').fadeOut('slow');
        }, 7000);

        // Mobile number validation (10 digits only)
        $('#mobile').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });

        // Emergency contact validation (10 digits only)
        $('input[name="emergency_contact"]').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });

        // Salary validation (positive numbers only)
        $('input[name="monthly_salary"]').on('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });

        // Form validation enhancement
        $('#priestForm').on('submit', function(e) {
            let isValid = true;
            
            // Check required fields
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

        // Remove invalid class on input
        $('.form-control, .form-select').on('input change', function() {
            $(this).removeClass('is-invalid');
        });
    });
</script>
@endsection