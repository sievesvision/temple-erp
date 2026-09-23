<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Devotee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
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

        // See login()'s own comment: a manual check, not a validate() rule, since a Closure
        // rule is silently skipped when the field is entirely absent from the request.
        if (!\App\Services\RecaptchaService::verify($request->input('g-recaptcha-response'), $request->ip())) {
            return back()->withErrors(['g-recaptcha-response' => 'Please complete the reCAPTCHA verification.'])->withInput();
        }

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
            Mail::to($request->email)->send(new OtpMail($otp, '10 minutes', $request->name));
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
            Mail::to($email)->send(new OtpMail($otp, '10 minutes', session('registration_data.name')));
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
        return response()->view('auth.login', ['step' => 1])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    /**
     * The POS-only kiosk login landing page — same step-1 form as showLogin() but a
     * dedicated, kiosk-appropriate view (no registration/forgot-password links, no public
     * navbar). Posts to the same login.post route, so completeLogin() below (unchanged)
     * still decides the post-login destination purely from the account's role/level.
     */
    public function showKioskLogin()
    {
        return response()->view('auth.kiosk-login', ['step' => 1])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    /**
     * Step 2 of login for a 2FA-enabled account — "Enter your OTP". Requires the pending
     * login state startLoginOtp() stashed in session; falls back to the plain login form if
     * that's missing (e.g. the user navigated here directly or the session already expired).
     */
    public function showLoginVerifyOtp(Request $request)
    {
        if (!session()->has('login_otp_user_id')) {
            return redirect()->route('login');
        }

        return response()->view('auth.login', ['step' => 2])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    /**
     * Verifies the login OTP and, on success, runs the same completeLogin() tail the
     * non-2FA path uses — this is the only place a 2FA account's session actually gets
     * established.
     */
    public function verifyLoginOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|digits:6',
        ]);

        if (!session()->has('login_otp_user_id')) {
            return redirect()->route('login')->withErrors(['email' => 'Session expired. Please login again.']);
        }

        $expiresAt = session('login_otp_expires_at');
        if (!$expiresAt || now()->greaterThan($expiresAt)) {
            return redirect()->route('login.verify-otp')->withErrors(['otp' => 'OTP has expired. Please request a new one.']);
        }

        $attempts = session('login_otp_attempts', 0);
        if ($attempts >= 5) {
            session()->forget(['login_otp_user_id', 'login_otp_hash', 'login_otp_expires_at', 'login_otp_attempts', 'login_otp_resend_cooldown_expires_at', 'login_otp_resend_attempts']);
            return redirect()->route('login')->withErrors(['email' => 'Too many failed verification attempts. Please login again.']);
        }
        session(['login_otp_attempts' => $attempts + 1]);

        $hash = session('login_otp_hash');
        if (!Hash::check($request->otp, $hash)) {
            return redirect()->route('login.verify-otp')->withErrors(['otp' => 'Invalid OTP. Please check the code and try again.']);
        }

        $user = User::find(session('login_otp_user_id'));
        session()->forget(['login_otp_user_id', 'login_otp_hash', 'login_otp_expires_at', 'login_otp_attempts', 'login_otp_resend_cooldown_expires_at', 'login_otp_resend_attempts']);

        if (!$user) {
            return redirect()->route('login')->withErrors(['email' => 'Account no longer exists.']);
        }

        return $this->completeLogin($user);
    }

    /**
     * Resend the login OTP — mirrors forgotPasswordResend()'s cooldown/attempt-cap shape.
     */
    public function loginOtpResend(Request $request)
    {
        $userId = session('login_otp_user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Session expired. Please login again.'], 400);
        }

        $cooldown = session('login_otp_resend_cooldown_expires_at');
        if ($cooldown && now()->lessThan($cooldown)) {
            $remaining = $cooldown->diffInSeconds(now());
            return response()->json(['success' => false, 'message' => "Please wait {$remaining} seconds before requesting a new OTP."], 400);
        }

        $attempts = session('login_otp_resend_attempts', 0);
        if ($attempts >= 5) {
            return response()->json(['success' => false, 'message' => 'Maximum resend attempts reached. Please login again.'], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Account no longer exists.'], 400);
        }

        $otp = sprintf("%06d", mt_rand(100000, 999999));

        session([
            'login_otp_hash' => Hash::make($otp),
            'login_otp_expires_at' => now()->addMinutes(10),
            'login_otp_attempts' => 0,
            'login_otp_resend_cooldown_expires_at' => now()->addSeconds(60),
            'login_otp_resend_attempts' => $attempts + 1,
        ]);

        try {
            Mail::to($user->email)->send(new \App\Mail\LoginOtpMail($otp, '10 minutes', $user->name));
            return response()->json(['success' => true, 'message' => 'A new OTP has been sent to your email.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to send verification email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * The dashboard route for a given active role — shared by login() (always lands on the
     * account's stored/default role) and switchRole() (lands on whichever role was just
     * switched to).
     */
    private function dashboardRouteForRole(string $role): string
    {
        return match ($role) {
            'Admin' => 'admin.dashboard',
            'Priest' => 'priest.dashboard',
            'Trustee' => 'trustee.dashboard',
            'Staff' => 'staff.dashboard',
            'Accountant' => 'accountant.dashboard',
            'Committee' => 'committee.dashboard',
            'Event Coordinator' => 'event-coordinator.my-events',
            // Same landing route as Admin/Committee reaching the Ticket Console
            // (admin.tickets.index) — TicketController::manageTickets() itself redirects a
            // view/entry-level Ticket Controller straight to the Ticket POS, the same way
            // EventConsoleController::show() redirects a pos-level Event Coordinator.
            'Ticket Controller' => 'admin.tickets.index',
            default => 'devotee.dashboard',
        };
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // A plain manual check (not a validate() rule) because a Closure validation rule is
        // silently skipped by Laravel when the field is entirely absent from the request —
        // exactly the case for a request with no g-recaptcha-response at all, which must
        // still be rejected once reCAPTCHA is enabled, not pass through undetected.
        if (!\App\Services\RecaptchaService::verify($request->input('g-recaptcha-response'), $request->ip())) {
            return back()->withErrors(['g-recaptcha-response' => 'Please complete the reCAPTCHA verification.'])->withInput();
        }

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

        // Email Verification Protection
        if (is_null($user->email_verified_at)) {
            return back()
                ->withErrors(['email' => 'Your email address has not been verified. Please complete email verification before logging in.'])
                ->withInput();
        }

        // A locked account (e.g. via the Event Coordinators "Lock" action) can't sign in at
        // all, regardless of role — 'status' otherwise defaults to 'Active' for everyone.
        if ($user->status !== 'Active') {
            return back()
                ->withErrors(['email' => 'This account has been locked. Please contact the temple office.'])
                ->withInput();
        }

        // A 2FA-enabled account doesn't get a session yet — credentials are correct, but
        // login only completes once the emailed OTP is verified (see verifyLoginOtp()).
        if ($user->two_factor_enabled) {
            return $this->startLoginOtp($user);
        }

        return $this->completeLogin($user);
    }

    /**
     * Generates and emails a login OTP, stashes the pending user id in session (mirrors
     * forgot-password's session-based OTP state), and sends the user to the "Enter OTP"
     * step of the login view instead of completing authentication immediately.
     */
    private function startLoginOtp(User $user)
    {
        // If a still-valid OTP was already emailed to this same account very recently — e.g.
        // they resubmitted the login form because the first email hadn't arrived yet — don't
        // fire another one. Just send them back to the same pending verification step instead
        // of re-sending; repeated logins used to email the same recipient again on every
        // single attempt with no cooldown at all, which is exactly the kind of burst pattern
        // that gets a sending mailbox flagged as abusive.
        $alreadyPending = session('login_otp_user_id') === $user->id
            && session('login_otp_expires_at')
            && now()->lessThan(session('login_otp_expires_at'))
            && session('login_otp_resend_cooldown_expires_at')
            && now()->lessThan(session('login_otp_resend_cooldown_expires_at'));

        if ($alreadyPending) {
            return redirect()->route('login.verify-otp');
        }

        $otp = sprintf("%06d", mt_rand(100000, 999999));

        session([
            'login_otp_user_id' => $user->id,
            'login_otp_hash' => Hash::make($otp),
            'login_otp_expires_at' => now()->addMinutes(10),
            'login_otp_attempts' => 0,
            'login_otp_resend_cooldown_expires_at' => now()->addSeconds(60),
            'login_otp_resend_attempts' => 0,
        ]);

        try {
            Mail::to($user->email)->send(new \App\Mail\LoginOtpMail($otp, '10 minutes', $user->name));
        } catch (\Exception $e) {
            session()->forget(['login_otp_user_id', 'login_otp_hash', 'login_otp_expires_at', 'login_otp_attempts', 'login_otp_resend_cooldown_expires_at', 'login_otp_resend_attempts']);
            return back()->withErrors(['email' => 'Failed to send login verification email: ' . $e->getMessage()])->withInput();
        }

        return redirect()->route('login.verify-otp');
    }

    /**
     * Every "no-navigation kiosk page" this specific account can reach, regardless of which
     * role is primary — reused by completeLogin() (decide where to land) and
     * showKioskSelect() (render the picker, recomputed fresh rather than trusting anything
     * stashed at login time). Returns [] the instant EITHER side of the account isn't purely
     * kiosk-only — a coordinator managing even one event's console, or a ticket controller at
     * 'admin' level — rather than a partial list, since a non-kiosk capability anywhere means
     * this account already has real navigation elsewhere and should keep using today's
     * existing (unmodified) redirect for that side, not be funnelled into this picker.
     *
     * @return array<int, array{type: 'event', event_id: int, label: string, date: string}|array{type: 'tickets', label: string}>
     */
    private function possibleKioskPosDestinations(User $user): array
    {
        $coordinatorRows = \Illuminate\Support\Facades\DB::table('event_coordinators')
            ->join('events', 'event_coordinators.event_id', '=', 'events.event_id')
            ->where('event_coordinators.user_id', $user->id)
            ->orderBy('events.event_date')
            ->select('events.event_id', 'events.event_name', 'events.event_date', 'event_coordinators.level')
            ->get();

        if ($coordinatorRows->isNotEmpty() && !$coordinatorRows->every(fn ($row) => $row->level === 'pos')) {
            return [];
        }

        $ticketRow = \Illuminate\Support\Facades\DB::table('ticket_controllers')->where('user_id', $user->id)->first();
        if ($ticketRow && !in_array($ticketRow->level, ['view', 'entry'], true)) {
            return [];
        }

        $destinations = [];
        foreach ($coordinatorRows as $row) {
            $destinations[] = ['type' => 'event', 'event_id' => $row->event_id, 'label' => $row->event_name, 'date' => $row->event_date];
        }
        if ($ticketRow) {
            $destinations[] = ['type' => 'tickets', 'label' => 'Ticket Sales'];
        }

        return $destinations;
    }

    /**
     * Also sets active_role to match the destination's own required role — necessary because
     * completeLogin() has already set active_role to the account's PRIMARY role before this
     * ever runs, and both PosDonationController::show() and TicketController::posShow()
     * check the *active* role literally ('Event Coordinator'/'Ticket Controller') to look up
     * the coordinator level / ticket controller level, not just whichever role the route's own
     * middleware happened to allow through. Without this, a primary Event Coordinator whose
     * only reachable destination is their secondary Ticket Controller grant (or vice versa)
     * would 403 inside the destination page even though they legitimately hold that access.
     */
    private function redirectToPosDestination(array $destination)
    {
        session(['active_role' => $destination['type'] === 'event' ? 'Event Coordinator' : 'Ticket Controller']);

        return $destination['type'] === 'event'
            ? redirect()->route('admin.events.pos', $destination['event_id'])
            : redirect()->route('admin.tickets.pos');
    }

    /**
     * The "choose your counter" grid for an account that reached completeLogin() with more
     * than one kiosk-only destination — recomputes fresh from the DB rather than trusting
     * anything stashed at login time, so a stale bookmark or a since-changed assignment never
     * shows a degenerate one-tile (or empty) grid: it just redirects straight past this page.
     */
    public function showKioskSelect()
    {
        $user = Auth::user();
        $destinations = $this->possibleKioskPosDestinations($user);

        if (count($destinations) > 1) {
            return view('auth.kiosk-select', ['destinations' => $destinations]);
        }
        if (count($destinations) === 1) {
            return $this->redirectToPosDestination($destinations[0]);
        }

        $role = session('active_role', $user->role);
        return redirect()->route($this->dashboardRouteForRole($role));
    }

    /**
     * Handles a tile tap on the "choose your counter" grid. Re-validates the chosen
     * destination against possibleKioskPosDestinations() rather than trusting the posted
     * type/event_id at face value — a POST is the only way to reach this (not a plain link),
     * specifically so redirectToPosDestination() can switch active_role to match before
     * redirecting (see its own docblock for why that's required).
     */
    public function selectKioskPosDestination(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'type' => 'required|in:event,tickets',
            'event_id' => 'required_if:type,event|nullable|integer',
        ]);

        $destinations = $this->possibleKioskPosDestinations($user);
        $match = collect($destinations)->first(function ($d) use ($validated) {
            if ($d['type'] !== $validated['type']) {
                return false;
            }
            return $d['type'] !== 'event' || (int) $d['event_id'] === (int) $validated['event_id'];
        });

        if (!$match) {
            abort(403, 'Unauthorized access.');
        }

        return $this->redirectToPosDestination($match);
    }

    /**
     * The shared "credentials/OTP are both good, finish signing them in" tail — used by both
     * the plain login() path (no 2FA) and verifyLoginOtp() (2FA verified) so the actual
     * session/role/redirect logic only exists once.
     */
    private function completeLogin(User $user)
    {
        // Login always lands on the account's stored/default role — a user holding
        // additional roles (Committee, Event Coordinator, etc. via grant tables) switches
        // to them afterwards from the topbar menu (see switchRole()), rather than picking
        // at the login screen.
        $role = $user->role;
        session(['active_role' => $role]);

        // Auto create devotee profile if their default role is Devotee and one doesn't exist
        if ($role === 'Devotee') {
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
        $user->update(['last_login_at' => now()]);

        // An account that is PURELY kiosk-only (every event assignment is 'pos' level, or a
        // 'view'/'entry' Ticket Controller) may hold more than one such destination at once
        // (two events, or an event plus ticket access) — neither kiosk page has any
        // navigation to switch between them, so with more than one reachable destination the
        // account picks at login instead of one being silently guessed. Only engages for
        // these two roles — an Admin/Staff/etc. account holding the same grants as a
        // secondary role keeps using the topbar's "Switch Role" as today, since it already
        // has full navigation available.
        if (in_array($role, ['Event Coordinator', 'Ticket Controller'], true)) {
            $destinations = $this->possibleKioskPosDestinations($user);
            if (count($destinations) > 1) {
                return redirect()->route('kiosk.select');
            }
            if (count($destinations) === 1) {
                return $this->redirectToPosDestination($destinations[0]);
            }
            // Zero kiosk-only destinations for this role — a console-level coordinator, an
            // admin-level ticket controller, or a coordinator with a non-pos assignment mixed
            // in — falls through to the existing role-specific logic below, unchanged.
        }

        // An Event Coordinator always lands straight on their workspace — the console for
        // view/entry/admin level, or the kiosk-style POS page for pos level — rather than a
        // list to click through first. With more than one assigned event, the earliest (by
        // event date) is the default; both pages have their own "Switch Event" menu for the
        // rest. A pos-level coordinator never reaches the console at all — this is their
        // only page.
        if ($role === 'Event Coordinator') {
            $firstAssignment = \Illuminate\Support\Facades\DB::table('event_coordinators')
                ->join('events', 'event_coordinators.event_id', '=', 'events.event_id')
                ->where('event_coordinators.user_id', $user->id)
                ->orderBy('events.event_date')
                ->select('events.event_id', 'event_coordinators.level')
                ->first();
            if ($firstAssignment) {
                return $firstAssignment->level === 'pos'
                    ? redirect()->route('admin.events.pos', $firstAssignment->event_id)
                    : redirect()->route('admin.events.console', $firstAssignment->event_id);
            }
            return redirect()->route('event-coordinator.my-events');
        }

        return redirect()->route($this->dashboardRouteForRole($role));
    }

    /**
     * Switches the session's active role without re-authenticating — reachable from the
     * topbar's "Switch Role" menu for anyone holding more than one granted role. Still
     * enforces the same eligibility (grantedRoles()) and authority-ceiling checks login()
     * used to apply to its role choice, so this can't be used to escalate beyond what the
     * account actually holds.
     */
    public function switchRole(Request $request)
    {
        $request->validate(['role' => 'required|string']);

        $user = Auth::user();
        $grantedRoles = $user->grantedRoles();

        if (!in_array($request->role, $grantedRoles, true)) {
            return redirect()->back()->with('error', 'You are not authorised for the ' . $request->role . ' role.');
        }

        $requestedLevel = \App\Models\RolePermission::levels()[$request->role] ?? PHP_INT_MAX;
        if ($requestedLevel < $user->authorisedLevel()) {
            return redirect()->back()->with('error', 'You are not authorised for that role.');
        }

        session(['active_role' => $request->role]);

        return redirect()->route($this->dashboardRouteForRole($request->role));
    }

    public function logout(Request $request)
    {
        $fromKiosk = $request->query('from') === 'kiosk';

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($fromKiosk ? 'kiosk.login' : 'login')
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
            Mail::to($request->email)->send(new \App\Mail\ForgotPasswordMail($otp, '10 minutes', $user->name));
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

        // Receiving and confirming this OTP already proves ownership of the email,
        // so an account that was never verified (e.g. created by an admin) becomes
        // verified here too — otherwise it would be locked out of login permanently
        // with no way to complete verification.
        $user = User::where('email', session('forgot_email'))->first();
        if ($user && is_null($user->email_verified_at)) {
            $user->email_verified_at = now();
            $user->save();
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
        $user->password_changed_at = now();
        $user->save();

        try {
            Mail::to($user->email)->send(new \App\Mail\PasswordChangedMail($user->name));
        } catch (\Exception $e) {
            // Ignore mail errors
        }

        session()->forget(['forgot_email', 'forgot_otp_hash', 'forgot_otp_expires_at', 'forgot_otp_attempts', 'forgot_step', 'forgot_otp_verified']);

        return redirect()->route('login')->with('success', 'Password updated successfully. Please login.');
    }

    /**
     * Landing page for the link an admin sends via SystemUserController::sendResetLink() —
     * distinct from the self-service OTP flow above. Uses Laravel's built-in password
     * broker (token hashed/expired/throttled/single-use for free), not the OTP session flow.
     */
    public function showResetPasswordLink(Request $request, string $token)
    {
        return view('auth.set-new-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPasswordLink(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6',
        ], [
            'password.required' => 'New Password is required.',
            'password.confirmed' => 'Confirm Password does not match.',
            'password.min' => 'Password must be at least 6 characters.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->password_changed_at = now();
                $user->save();

                try {
                    Mail::to($user->email)->send(new \App\Mail\PasswordChangedMail($user->name));
                } catch (\Exception $e) {
                    // Ignore mail errors
                }
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password updated successfully. Please login.')
            : back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
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
            $user = User::where('email', $email)->first();
            Mail::to($email)->send(new \App\Mail\ForgotPasswordMail($otp, '10 minutes', $user->name ?? null));
            return response()->json(['success' => true, 'message' => 'A new OTP has been sent to your email.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to send verification email: ' . $e->getMessage()], 500);
        }
    }
}