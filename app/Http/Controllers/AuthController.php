<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Devotee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|regex:/^[A-Za-z ]+$/',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|digits:10|unique:users,mobile',
            'gender' => 'required',
            'dob' => 'required|date',
            'password' => 'required|confirmed|min:6',
        ], [
            'name.required' => 'Name is required.',
            'name.regex' => 'Only letters and spaces allowed.',
            'email.required' => 'Email is required.',
            'email.email' => 'Enter a valid email.',
            'email.unique' => 'Email already registered.',
            'mobile.required' => 'Mobile number is required.',
            'mobile.digits' => 'Mobile number must be exactly 10 digits.',
            'mobile.unique' => 'Mobile number already registered.',
            'gender.required' => 'Please select gender.',
            'dob.required' => 'Date of birth is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        // Generate 6-digit OTP
        $otp = sprintf("%06d", mt_rand(100000, 999999));

        // Store registration data & OTP temporarily in session
        session([
            'registration_data' => [
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'password' => Hash::make($request->password), // Hash it here before saving
                'address' => $request->address,
                'gothra' => $request->gothra,
                'nakshatra' => $request->nakshatra,
                'gender' => $request->gender,
                'dob' => $request->dob,
            ],
            'registration_otp_hash' => Hash::make($otp),
            'registration_otp_expires_at' => now()->addMinutes(10),
            'registration_otp_attempts' => 0,
            'registration_email' => $request->email,
            'resend_cooldown_expires_at' => now()->addSeconds(60),
            'resend_attempts' => 0
        ]);

        // Send verification mail
        try {
            Mail::to($request->email)->send(new OtpMail($otp));
        } catch (\Exception $e) {
            // Clear session data if mail failed to send
            session()->forget([
                'registration_data',
                'registration_otp_hash',
                'registration_otp_expires_at',
                'registration_otp_attempts',
                'registration_email',
                'resend_cooldown_expires_at',
                'resend_attempts'
            ]);
            return back()->withErrors(['email' => 'Failed to send verification email: ' . $e->getMessage()])->withInput();
        }

        return redirect()->route('register.verify-otp')
            ->with('success', 'Verification code sent to ' . $request->email);
    }

    public function showVerifyOtp()
    {
        if (!session()->has('registration_data')) {
            return redirect()->route('register')->withErrors(['email' => 'Please register first.']);
        }

        $email = session('registration_email');
        return view('auth.verify_otp', compact('email'));
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|digits:6',
        ]);

        if (!session()->has('registration_data')) {
            return redirect()->route('register')->withErrors(['email' => 'Session expired. Please register again.']);
        }

        $expiresAt = session('registration_otp_expires_at');
        if (!$expiresAt || now()->greaterThan($expiresAt)) {
            return back()->withErrors(['otp' => 'OTP has expired. Please request a new one.']);
        }

        // Limit verify attempts to prevent brute force
        $attempts = session('registration_otp_attempts', 0);
        if ($attempts >= 5) {
            session()->forget([
                'registration_data',
                'registration_otp_hash',
                'registration_otp_expires_at',
                'registration_otp_attempts',
                'registration_email',
                'resend_cooldown_expires_at',
                'resend_attempts'
            ]);
            return redirect()->route('register')->withErrors(['email' => 'Too many failed verification attempts. Please register again.']);
        }
        session(['registration_otp_attempts' => $attempts + 1]);

        $otpHash = session('registration_otp_hash');
        if (!Hash::check($request->otp, $otpHash)) {
            return back()->withErrors(['otp' => 'Invalid OTP. Please check the code and try again.']);
        }

        // Successful verify
        $regData = session('registration_data');

        // Check unique constraints one more time
        if (User::where('email', $regData['email'])->exists() || User::where('mobile', $regData['mobile'])->exists()) {
            session()->forget([
                'registration_data',
                'registration_otp_hash',
                'registration_otp_expires_at',
                'registration_otp_attempts',
                'registration_email',
                'resend_cooldown_expires_at',
                'resend_attempts'
            ]);
            return redirect()->route('register')->withErrors(['email' => 'Account email or mobile was registered by another user.']);
        }

        // Create user
        $user = User::create([
            'name' => $regData['name'],
            'email' => $regData['email'],
            'mobile' => $regData['mobile'],
            'password' => $regData['password'],
            'role' => 'Devotee',
            'status' => 'Active',
        ]);
        $user->email_verified_at = now();
        $user->save();

        // Create devotee record
        Devotee::create([
            'user_id' => $user->id,
            'address' => $regData['address'] ?? 'Auto-created Devotee Profile',
            'gothra' => $regData['gothra'] ?? 'Not Specified',
            'nakshatra' => $regData['nakshatra'] ?? 'Not Specified',
            'gender' => $regData['gender'],
            'dob' => $regData['dob'],
            'verified' => 1,
        ]);

        // Clear session data
        session()->forget([
            'registration_data',
            'registration_otp_hash',
            'registration_otp_expires_at',
            'registration_otp_attempts',
            'registration_email',
            'resend_cooldown_expires_at',
            'resend_attempts'
        ]);

        return redirect()->route('login')
            ->with('success', 'Registration completed successfully. Please login.');
    }

    public function resendOtp(Request $request)
    {
        if (!session()->has('registration_data')) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please register again.'
            ], 400);
        }

        $cooldown = session('resend_cooldown_expires_at');
        if ($cooldown && now()->lessThan($cooldown)) {
            $remaining = $cooldown->diffInSeconds(now());
            return response()->json([
                'success' => false,
                'message' => "Please wait {$remaining} seconds before requesting a new OTP."
            ], 400);
        }

        $attempts = session('resend_attempts', 0);
        if ($attempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum resend attempts reached. Please register again.'
            ], 400);
        }

        $otp = sprintf("%06d", mt_rand(100000, 999999));

        session([
            'registration_otp_hash' => Hash::make($otp),
            'registration_otp_expires_at' => now()->addMinutes(10),
            'registration_otp_attempts' => 0,
            'resend_cooldown_expires_at' => now()->addSeconds(60),
            'resend_attempts' => $attempts + 1
        ]);

        try {
            $email = session('registration_email');
            Mail::to($email)->send(new OtpMail($otp));
            return response()->json([
                'success' => true,
                'message' => 'A new OTP has been sent to your email.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email: ' . $e->getMessage()
            ], 500);
        }
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'required'
        ]);

        // Find user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            return back()
                ->withErrors(['email' => 'No account found with this email.'])
                ->withInput();
        }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            return back()
                ->withErrors(['password' => 'Incorrect password.'])
                ->withInput();
        }

        // Check role matches (except for Devotee - anyone can login as Devotee)
        if ($request->role != 'Devotee') {
            if ($user->role !== $request->role) {
                return back()
                    ->withErrors(['role' => 'This account is not registered as ' . $request->role . '.'])
                    ->withInput();
            }
        }

        // Email Verification Protection
        if (is_null($user->email_verified_at)) {
            return back()
                ->withErrors(['email' => 'Your email address has not been verified. Please complete email verification before logging in.'])
                ->withInput();
        }

        // Set active role in session
        session(['active_role' => $request->role]);

        // Auto create devotee profile if they log in as Devotee and profile doesn't exist
        if ($request->role === 'Devotee') {
            $devoteeExists = \Illuminate\Support\Facades\DB::table('devotees')->where('user_id', $user->id)->exists();
            if (!$devoteeExists) {
                \Illuminate\Support\Facades\DB::table('devotees')->insert([
                    'user_id' => $user->id,
                    'address' => 'Auto-created Devotee Profile',
                    'gothra' => 'Not Specified',
                    'nakshatra' => 'Not Specified',
                    'gender' => 'Male',
                    'dob' => '2000-01-01',
                    'verified' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Login the user
        Auth::login($user);

        // Redirect based on selected role
        switch ($request->role) {
            case 'Admin':
                return redirect()->route('admin.dashboard');
            
            case 'Priest':
                return redirect()->route('priest.dashboard');
            
            case 'Trustee':
                return redirect()->route('trustee.dashboard');
            
            case 'Staff':
                return redirect()->route('staff.dashboard');
            
            case 'Accountant':
                return redirect()->route('accountant.dashboard');
            
            case 'Devotee':
            default:
                return redirect()->route('devotee.dashboard');
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Logged out successfully.');
    }

    public function showForgotPassword(Request $request)
    {
        if ($request->has('restart')) {
            session()->forget(['forgot_email', 'forgot_otp_hash', 'forgot_otp_expires_at', 'forgot_otp_attempts', 'forgot_step', 'forgot_otp_verified']);
        }
        
        return response()->view('auth.forgot_password', ['step' => 1])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return redirect()->route('forgot-password')->withInput()->withErrors(['email' => 'Account does not exist.']);
        }

        $otp = sprintf("%06d", mt_rand(100000, 999999));

        session([
            'forgot_email' => $request->email,
            'forgot_otp_hash' => Hash::make($otp),
            'forgot_otp_expires_at' => now()->addMinutes(10),
            'forgot_otp_attempts' => 0,
        ]);

        try {
            Mail::to($request->email)->send(new \App\Mail\ForgotPasswordMail($otp));
        } catch (\Exception $e) {
            return redirect()->route('forgot-password')->withInput()->withErrors(['email' => 'Failed to send OTP email: ' . $e->getMessage()]);
        }

        return redirect()->route('forgot-password.verify')->with('success', 'A 6-digit OTP code has been sent to ' . $request->email);
    }

    public function showVerifyForgotPasswordOtp(Request $request)
    {
        if (!session()->has('forgot_email')) {
            return redirect()->route('forgot-password');
        }

        return response()->view('auth.forgot_password', ['step' => 2])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    public function verifyForgotPasswordOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|digits:6',
        ]);

        if (!session()->has('forgot_email')) {
            return redirect()->route('forgot-password')->withErrors(['email' => 'Session expired. Please restart.']);
        }

        $expiresAt = session('forgot_otp_expires_at');
        if (!$expiresAt || now()->greaterThan($expiresAt)) {
            return redirect()->route('forgot-password.verify')->withErrors(['otp' => 'OTP has expired. Please request a new one.']);
        }

        $attempts = session('forgot_otp_attempts', 0);
        if ($attempts >= 5) {
            session()->forget(['forgot_email', 'forgot_otp_hash', 'forgot_otp_expires_at', 'forgot_otp_attempts', 'forgot_step', 'forgot_otp_verified']);
            return redirect()->route('forgot-password')->withErrors(['email' => 'Too many failed attempts. Reset restarted.']);
        }
        session(['forgot_otp_attempts' => $attempts + 1]);

        $hash = session('forgot_otp_hash');
        if (!Hash::check($request->otp, $hash)) {
            return redirect()->route('forgot-password.verify')->withErrors(['otp' => 'Invalid OTP. Please try again.']);
        }

        session([
            'forgot_otp_verified' => true,
        ]);

        return redirect()->route('forgot-password.reset')->with('success', 'OTP verified successfully. Please set a new password.');
    }

    public function showResetPassword(Request $request)
    {
        if (!session('forgot_otp_verified')) {
            return redirect()->route('forgot-password');
        }

        return response()->view('auth.forgot_password', ['step' => 3])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    public function resetPassword(Request $request)
    {
        if (!session('forgot_otp_verified')) {
            return redirect()->route('forgot-password');
        }

        $request->validate([
            'password' => 'required|confirmed|min:6',
        ], [
            'password.required' => 'New Password is required.',
            'password.confirmed' => 'Confirm Password does not match.',
            'password.min' => 'Password must be at least 6 characters.',
        ]);

        $email = session('forgot_email');
        $user = User::where('email', $email)->first();
        if (!$user) {
            return redirect()->route('forgot-password');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        try {
            Mail::to($user->email)->send(new \App\Mail\PasswordChangedMail($user->name));
        } catch (\Exception $e) {
            // Ignore mail errors
        }

        session()->forget(['forgot_email', 'forgot_otp_hash', 'forgot_otp_expires_at', 'forgot_otp_attempts', 'forgot_step', 'forgot_otp_verified']);

        return redirect()->route('login')->with('success', 'Password updated successfully. Please login.');
    }

    public function forgotPasswordResend(Request $request)
    {
        $email = session('forgot_email');
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Session expired. Reset restarted.'], 400);
        }

        $otp = sprintf("%06d", mt_rand(100000, 999999));

        session([
            'forgot_otp_hash' => Hash::make($otp),
            'forgot_otp_expires_at' => now()->addMinutes(10),
            'forgot_otp_attempts' => 0,
        ]);

        try {
            Mail::to($email)->send(new \App\Mail\ForgotPasswordMail($otp));
            return response()->json(['success' => true, 'message' => 'A new OTP has been sent to your email.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to send verification email: ' . $e->getMessage()], 500);
        }
    }
}