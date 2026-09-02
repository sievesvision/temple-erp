<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DevoteeController;
use App\Http\Controllers\PriestController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\TrusteeController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AccountantController;
use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\EhundiController;
use App\Models\Setting;

// ============================================
// PUBLIC ROUTES
// ============================================
Route::get('/ehundi', [EhundiController::class, 'show'])->name('ehundi.show');
Route::post('/ehundi/offer', [EhundiController::class, 'offer'])->name('ehundi.offer');

Route::get('/events/{slug}', [\App\Http\Controllers\EventController::class, 'showPublic'])->name('events.show')->where('slug', '[A-Za-z0-9-]+');

Route::get('/qr-{slug}', [\App\Http\Controllers\QrLinkController::class, 'redirect'])->name('qr.redirect')->where('slug', '[A-Za-z0-9-]+');

Route::get('/', function () {
    $poojas = \Illuminate\Support\Facades\DB::table('poojas')->where('status', 'Active')->get();
    $events = \Illuminate\Support\Facades\DB::table('events')->where('status', 'Upcoming')->orderBy('event_date', 'asc')->get();

    $temple = Setting::templeBranding();
    $stripeEnabled = (bool) Setting::get('stripe_enabled', true);

    return view('frontend.index', compact('poojas', 'events', 'temple', 'stripeEnabled'));
})->name('home');

Route::post('/donate-without-login', [\App\Http\Controllers\DonationController::class, 'storePublic'])->name('donate.without.login');
Route::get('/donate/stripe/success', [\App\Http\Controllers\DonationController::class, 'stripeSuccess'])->name('donate.stripe.success');
Route::get('/donate/stripe/cancel', [\App\Http\Controllers\DonationController::class, 'stripeCancel'])->name('donate.stripe.cancel');
Route::post('/stripe/webhook', [\App\Http\Controllers\DonationController::class, 'stripeWebhook'])->name('stripe.webhook');

// ============================================
// AUTHENTICATION ROUTES
// ============================================
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::get('/register/verify-otp', [AuthController::class, 'showVerifyOtp'])->name('register.verify-otp');
Route::post('/register/verify-otp', [AuthController::class, 'verifyOtp'])->name('register.verify-otp.post');
Route::post('/register/resend-otp', [AuthController::class, 'resendOtp'])->name('register.resend-otp');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

// FORGOT PASSWORD SYSTEM ROUTES
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('forgot-password');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password.post');
Route::get('/forgot-password/verify', [AuthController::class, 'showVerifyForgotPasswordOtp'])->name('forgot-password.verify');
Route::post('/forgot-password/verify', [AuthController::class, 'verifyForgotPasswordOtp'])->name('forgot-password.verify.post');
Route::post('/forgot-password/resend', [AuthController::class, 'forgotPasswordResend'])->name('forgot-password.resend');
Route::get('/forgot-password/reset', [AuthController::class, 'showResetPassword'])->name('forgot-password.reset');
Route::post('/forgot-password/reset', [AuthController::class, 'resetPassword'])->name('forgot-password.reset.post');

// ============================================
// ADMIN ROUTES (Restricted via RoleMiddleware)
// ============================================
Route::middleware(['auth', 'role.admin'])->group(function () {
    Route::get('/admin/dashboard', function () {
        $devoteesCount = \Illuminate\Support\Facades\DB::table('devotees')->count();
        $priestsCount = \Illuminate\Support\Facades\DB::table('priests')->count();
        
        // Priest status counts
        $onlinePriests = \Illuminate\Support\Facades\DB::table('priests')->where('current_status', 'Online')->count();
        $busyPriests = \Illuminate\Support\Facades\DB::table('priests')->where('current_status', 'Busy')->count();
        $offlinePriests = \Illuminate\Support\Facades\DB::table('priests')->where('current_status', 'Offline')->count();
        $today = date('Y-m-d');
        $leavePriests = \Illuminate\Support\Facades\DB::table('leave_requests')
            ->where('status', 'Approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->distinct('priest_id')
            ->count('priest_id');
        
        // Today's Poojas count
        $todayPoojasCount = \Illuminate\Support\Facades\DB::table('pooja_bookings')
            ->where('booking_date', date('Y-m-d'))
            ->where('booking_status', '!=', 'Cancelled')
            ->count();

        // Donations sum
        $totalDonationsSum = \Illuminate\Support\Facades\DB::table('donations')->where('payment_status', 'Paid')->sum('amount')
            + \Illuminate\Support\Facades\DB::table('donations_without_logins')->where('payment_status', 'Paid')->sum('amount');

        if ($totalDonationsSum >= 100000) {
            $donationsDisplay = Setting::get('currency_code', 'AUD') . ' ' . round($totalDonationsSum / 100000, 2) . 'L';
        } elseif ($totalDonationsSum >= 1000) {
            $donationsDisplay = Setting::get('currency_code', 'AUD') . ' ' . round($totalDonationsSum / 1000, 1) . 'K';
        } else {
            $donationsDisplay = Setting::get('currency_code', 'AUD') . ' ' . number_format($totalDonationsSum);
        }

        // Events count
        $eventsCount = \Illuminate\Support\Facades\DB::table('events')->where('status', 'Upcoming')->count();

        // Recent Devotees
        $recentDevotees = \Illuminate\Support\Facades\DB::table('devotees')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->select('users.name', 'users.mobile', 'devotees.created_at')
            ->orderBy('devotees.created_at', 'desc')
            ->limit(5)
            ->get();
            
        // Recent Donations from logged in devotees
        $loggedDonations = \Illuminate\Support\Facades\DB::table('donations')
            ->leftJoin('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
            ->leftJoin('users', 'devotees.user_id', '=', 'users.id')
            ->select('users.name as donor_name', 'donations.amount', 'donations.donation_date', 'donations.created_at')
            ->orderBy('donations.donation_date', 'desc')
            ->orderBy('donations.created_at', 'desc')
            ->limit(5)
            ->get();

        // Recent Donations from guest donors
        $guestDonations = \Illuminate\Support\Facades\DB::table('donations_without_logins')
            ->where('payment_status', 'Paid')
            ->select('donor_name', 'amount', 'donation_date', 'created_at')
            ->orderBy('donation_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentDonations = $loggedDonations->concat($guestDonations)
            ->sort(function ($a, $b) {
                $cmp = strcmp($b->donation_date, $a->donation_date);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return strcmp($b->created_at, $a->created_at);
            })
            ->take(5)
            ->values();
            
        return view('admin.dashboard', compact(
            'devoteesCount',
            'priestsCount',
            'onlinePriests',
            'busyPriests',
            'offlinePriests',
            'leavePriests',
            'todayPoojasCount',
            'donationsDisplay',
            'eventsCount',
            'recentDevotees',
            'recentDonations'
        ));
    })->name('admin.dashboard');

    // Priest Routes (Admin management)
    Route::get('/admin/manage-priests', [PriestController::class, 'managePriests'])->name('admin.priests.index');
    Route::get('/admin/add-priest', [PriestController::class, 'addPriestPage'])->name('admin.priests.create');
    Route::post('/admin/priest/store', [PriestController::class, 'storePriest'])->name('admin.priests.store');
    Route::get('/admin/priest/view/{id}', [PriestController::class, 'viewPriest'])->name('admin.priests.view');
    Route::get('/admin/priest/edit/{id}', [PriestController::class, 'editPriest'])->name('admin.priests.edit');
    Route::post('/admin/priest/update/{id}', [PriestController::class, 'updatePriest'])->name('admin.priests.update');
    Route::delete('/admin/priest/delete/{id}', [PriestController::class, 'deletePriest'])->name('admin.priests.delete');

    // Devotee Routes (Admin management)
    Route::get('/admin/manage-devotees', [DevoteeController::class, 'manageDevotees'])->name('admin.devotees.index');
    Route::get('/admin/add-devotee', [DevoteeController::class, 'addDevoteePage'])->name('admin.devotees.create');
    Route::post('/admin/devotee/store', [DevoteeController::class, 'storeDevotee'])->name('admin.devotees.store');
    Route::post('/admin/devotee/update/{id}', [DevoteeController::class, 'updateDevotee'])->name('admin.devotees.update');
    Route::delete('/admin/devotee/delete/{id}', [DevoteeController::class, 'deleteDevotee'])->name('admin.devotees.delete');

    // Trustee CRUD Routes (Admin management)
    Route::get('/admin/manage-trustees', [TrusteeController::class, 'manageTrustees'])->name('admin.trustees.index');
    Route::get('/admin/add-trustee', [TrusteeController::class, 'addTrusteePage'])->name('admin.trustees.create');
    Route::post('/admin/trustee/store', [TrusteeController::class, 'storeTrustee'])->name('admin.trustees.store');
    Route::post('/admin/trustee/update/{id}', [TrusteeController::class, 'updateTrustee'])->name('admin.trustees.update');
    Route::delete('/admin/trustee/delete/{id}', [TrusteeController::class, 'deleteTrustee'])->name('admin.trustees.delete');

    // Staff CRUD Routes (Admin management)
    Route::get('/admin/manage-staff', [StaffController::class, 'manageStaff'])->name('admin.staff.index');
    Route::get('/admin/add-staff', [StaffController::class, 'addStaffPage'])->name('admin.staff.create');
    Route::post('/admin/staff/store', [StaffController::class, 'storeStaff'])->name('admin.staff.store');
    Route::post('/admin/staff/update/{id}', [StaffController::class, 'updateStaff'])->name('admin.staff.update');
    Route::delete('/admin/staff/delete/{id}', [StaffController::class, 'deleteStaff'])->name('admin.staff.delete');

    // Accountant CRUD Routes (Admin management)
    Route::get('/admin/manage-accountants', [AccountantController::class, 'manageAccountants'])->name('admin.accountants.index');
    Route::get('/admin/add-accountant', [AccountantController::class, 'addAccountantPage'])->name('admin.accountants.create');
    Route::post('/admin/accountant/store', [AccountantController::class, 'storeAccountant'])->name('admin.accountants.store');
    Route::post('/admin/accountant/update/{id}', [AccountantController::class, 'updateAccountant'])->name('admin.accountants.update');
    Route::delete('/admin/accountant/delete/{id}', [AccountantController::class, 'deleteAccountant'])->name('admin.accountants.delete');

    // Committee CRUD Routes (Admin management)
    Route::get('/admin/manage-committee', [CommitteeController::class, 'manageCommittee'])->name('admin.committee.index');
    Route::get('/admin/add-committee', [CommitteeController::class, 'addCommitteePage'])->name('admin.committee.create');
    Route::post('/admin/committee/store', [CommitteeController::class, 'storeCommittee'])->name('admin.committee.store');
    Route::post('/admin/committee/update/{id}', [CommitteeController::class, 'updateCommittee'])->name('admin.committee.update');
    Route::delete('/admin/committee/delete/{id}', [CommitteeController::class, 'deleteCommittee'])->name('admin.committee.delete');

    // Inventory CRUD & Stock Adjustment Routes (Admin management)
    Route::get('/admin/manage-inventory', [\App\Http\Controllers\InventoryController::class, 'index'])->name('admin.inventory.index');
    Route::post('/admin/inventory/store', [\App\Http\Controllers\InventoryController::class, 'store'])->name('admin.inventory.store');
    Route::post('/admin/inventory/update/{id}', [\App\Http\Controllers\InventoryController::class, 'update'])->name('admin.inventory.update');
    Route::post('/admin/inventory/adjust/{id}', [\App\Http\Controllers\InventoryController::class, 'adjustStock'])->name('admin.inventory.adjust');
    Route::delete('/admin/inventory/delete/{id}', [\App\Http\Controllers\InventoryController::class, 'destroy'])->name('admin.inventory.delete');

    // Admin Settings Routes
    Route::get('/admin/settings', function () {
        $systemMode = \App\Models\Setting::get('system_mode', 'Testing Mode');
        $emailHandling = \App\Models\Setting::get('testing_email_handling', 'Do Not Send Emails');
        $templeName = \App\Models\Setting::get('temple_name', 'Golden Temple');
        $templeSubtitle = \App\Models\Setting::get('temple_subtitle', 'Ganesha Temple');
        $templeEyebrow = \App\Models\Setting::get('temple_eyebrow', 'A place for prayer, community and belonging');
        $templeDescription = \App\Models\Setting::get('temple_description', 'A Tamil Hindu temple in South Maclean, Queensland, welcoming devotees to seek the blessings of Sri Selva Vinayakar.');
        $templeAddress = \App\Models\Setting::get('temple_address', '4915-4923 Mount Lindesay Hwy, South Maclean QLD 4280');
        $templePhone = \App\Models\Setting::get('temple_phone', '+61 7 5547 8064');
        $templeEmail = \App\Models\Setting::get('temple_email', 'hasq.president@gmail.com');
        $donationAccountName = \App\Models\Setting::get('donation_account_name', 'HINDU AHLAYA SANGAM QLD INC');
        $donationBankName = \App\Models\Setting::get('donation_bank_name', 'Commonwealth Bank');
        $donationBsb = \App\Models\Setting::get('donation_bsb', '064 000');
        $donationAccountNumber = \App\Models\Setting::get('donation_account_number', '00906257');
        $donationReceiptEmail = \App\Models\Setting::get('donation_receipt_email', 'hasq.president@gmail.com');
        $donationCoordinatorEmails = \App\Models\Setting::get('donation_coordinator_emails', '');
        $currencyCode = \App\Models\Setting::get('currency_code', 'AUD');
        $templeLogo = \App\Models\Setting::get('temple_logo', asset('images/logo.gif'));
        $adminLogoIcon = \App\Models\Setting::get('admin_logo_icon', asset('images/logo.gif'));
        $adminLogoText = \App\Models\Setting::get('admin_logo_text', 'SSVK ERP');
        $templeHeroImage = \App\Models\Setting::get('temple_hero_image', asset('images/temple_landing.jpg'));
        $templeStoryImage = \App\Models\Setting::get('temple_story_image', asset('images/about/ssvk.jpg'));
        $templeWorshipImage = \App\Models\Setting::get('temple_worship_image', asset('images/about/SELVA VINAYAHAR TEMPLE.jpg'));
        $themePrimaryColor = \App\Models\Setting::get('theme_primary_color', '#c45b2c');
        $themeAccentColor = \App\Models\Setting::get('theme_accent_color', '#e5ad45');
        $themeDarkColor = \App\Models\Setting::get('theme_dark_color', '#24382f');
        $themePreset = \App\Models\Setting::get('theme_preset', 'saffron-garden');
        $brandTitle = \App\Models\Setting::get('brand_title', 'SSVK');
        $brandSubtitle = \App\Models\Setting::get('brand_subtitle', '');
        $hoursWeekdayMorning = \App\Models\Setting::get('hours_weekday_morning', '7:30 am - 12:00 noon');
        $hoursWeekdayMorningPooja = \App\Models\Setting::get('hours_weekday_morning_pooja', '9:00 am - 9:30 am');
        $hoursWeekdayEvening = \App\Models\Setting::get('hours_weekday_evening', '5:00 pm - 8:30 pm');
        $hoursWeekdayEveningPooja = \App\Models\Setting::get('hours_weekday_evening_pooja', '7:00 pm - 7:30 pm');
        $hoursWeekend = \App\Models\Setting::get('hours_weekend', '7:30 am - 1:00 pm');
        $hoursWeekendPooja = \App\Models\Setting::get('hours_weekend_pooja', '9:00 am - 9:30 am');
        $stripeEnabled = (bool) \App\Models\Setting::get('stripe_enabled', true);
        $stripeMode = \App\Models\Setting::get('stripe_mode', 'test');
        $templeOpeningTime = \App\Models\Setting::get('temple_opening_time', '06:00');
        $templeClosingTime = \App\Models\Setting::get('temple_closing_time', '21:00');
        $lowStockThreshold = \App\Models\Setting::get('low_stock_threshold', '10.00');
        $maxAdvanceBookingDays = \App\Models\Setting::get('max_advance_booking_days', '90');
        $onlinePoojaShippingCharge = \App\Models\Setting::get('online_pooja_shipping_charge', '50.00');

        return view('admin.settings', compact(
            'systemMode', 
            'emailHandling',
            'templeName',
            'templeSubtitle',
            'templeEyebrow',
            'templeDescription',
            'templeAddress',
            'templePhone',
            'templeEmail',
            'donationAccountName',
            'donationBankName',
            'donationBsb',
            'donationAccountNumber',
            'donationReceiptEmail',
            'donationCoordinatorEmails',
            'currencyCode',
            'templeLogo',
            'adminLogoIcon',
            'adminLogoText',
            'templeHeroImage',
            'templeStoryImage',
            'templeWorshipImage',
            'themePrimaryColor',
            'themeAccentColor',
            'themeDarkColor',
            'themePreset',
            'brandTitle',
            'brandSubtitle',
            'hoursWeekdayMorning',
            'hoursWeekdayMorningPooja',
            'hoursWeekdayEvening',
            'hoursWeekdayEveningPooja',
            'hoursWeekend',
            'hoursWeekendPooja',
            'stripeEnabled',
            'stripeMode',
            'templeOpeningTime',
            'templeClosingTime',
            'lowStockThreshold',
            'maxAdvanceBookingDays',
            'onlinePoojaShippingCharge'
        ));
    })->name('admin.settings');

    Route::post('/admin/settings', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'system_mode' => 'required|in:Testing Mode,Live Mode',
            'testing_email_handling' => 'required_if:system_mode,Testing Mode|nullable|in:Send Emails,Do Not Send Emails',
            'temple_name' => 'required|string|max:255',
            'temple_subtitle' => 'required|string|max:255',
            'temple_eyebrow' => 'required|string|max:255',
            'temple_description' => 'required|string|max:1000',
            'temple_address' => 'required|string|max:255',
            'temple_phone' => 'required|string|max:50',
            'temple_email' => 'required|email|max:255',
            'donation_account_name' => 'required|string|max:255',
            'donation_bank_name' => 'required|string|max:255',
            'donation_bsb' => 'required|string|max:20',
            'donation_account_number' => 'required|string|max:30',
            'donation_receipt_email' => 'required|email|max:255',
            'donation_coordinator_emails' => 'nullable|string|max:1000',
            'currency_code' => 'required|string|size:3',
            'temple_logo' => 'nullable|string|max:500',
            'admin_logo_icon' => 'nullable|string|max:500',
            'admin_logo_text' => 'nullable|string|max:100',
            'temple_hero_image' => 'required|string|max:500',
            'temple_story_image' => 'required|string|max:500',
            'temple_worship_image' => 'required|string|max:500',
            'theme_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_dark_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_preset' => 'required|in:saffron-garden,lotus-teal,marigold-night,rose-sandal,custom',
            'brand_title' => 'required|string|max:100',
            'brand_subtitle' => 'nullable|string|max:150',
            'hours_weekday_morning' => 'required|string|max:100',
            'hours_weekday_morning_pooja' => 'required|string|max:100',
            'hours_weekday_evening' => 'required|string|max:100',
            'hours_weekday_evening_pooja' => 'required|string|max:100',
            'hours_weekend' => 'required|string|max:100',
            'hours_weekend_pooja' => 'required|string|max:100',
            'stripe_enabled' => 'nullable|boolean',
            'stripe_mode' => 'nullable|string|in:test,live',
            'temple_opening_time' => 'required|string|max:10',
            'temple_closing_time' => 'required|string|max:10',
            'low_stock_threshold' => 'required|numeric|min:0',
            'max_advance_booking_days' => 'required|integer|min:1',
            'online_pooja_shipping_charge' => 'required|numeric|min:0',
        ]);

        // Image path settings should stay portable between environments (local vs production
        // have different domains). If someone pastes a full URL that happens to match THIS
        // request's own origin (an easy copy-paste-from-address-bar mistake), strip it down to
        // a relative path so the same database row works correctly wherever it's loaded —
        // a genuinely external URL (a real CDN/off-site image) is left untouched.
        $normalizeImagePath = function (?string $value) use ($request) {
            $value = trim((string) $value);
            if ($value === '') {
                return $value;
            }
            $ownOrigin = rtrim($request->getSchemeAndHttpHost(), '/');
            if (str_starts_with($value, $ownOrigin . '/')) {
                return substr($value, strlen($ownOrigin));
            }
            return $value;
        };

        \App\Models\Setting::set('system_mode', $request->system_mode);
        if ($request->has('testing_email_handling')) {
            \App\Models\Setting::set('testing_email_handling', $request->testing_email_handling);
        }
        \App\Models\Setting::set('temple_name', $request->temple_name);
        \App\Models\Setting::set('temple_subtitle', $request->temple_subtitle);
        \App\Models\Setting::set('temple_eyebrow', $request->temple_eyebrow);
        \App\Models\Setting::set('temple_description', $request->temple_description);
        \App\Models\Setting::set('temple_address', $request->temple_address);
        \App\Models\Setting::set('temple_phone', $request->temple_phone);
        \App\Models\Setting::set('temple_email', $request->temple_email);
        \App\Models\Setting::set('donation_account_name', $request->donation_account_name);
        \App\Models\Setting::set('donation_bank_name', $request->donation_bank_name);
        \App\Models\Setting::set('donation_bsb', $request->donation_bsb);
        \App\Models\Setting::set('donation_account_number', $request->donation_account_number);
        \App\Models\Setting::set('donation_receipt_email', $request->donation_receipt_email);
        \App\Models\Setting::set('donation_coordinator_emails', $request->donation_coordinator_emails ?? '');
        \App\Models\Setting::set('currency_code', strtoupper($request->currency_code));
        \App\Models\Setting::set('temple_logo', $normalizeImagePath($request->temple_logo));
        \App\Models\Setting::set('admin_logo_icon', $normalizeImagePath($request->admin_logo_icon));
        \App\Models\Setting::set('admin_logo_text', $request->admin_logo_text ?: 'SSVK ERP');
        \App\Models\Setting::set('temple_hero_image', $normalizeImagePath($request->temple_hero_image));
        \App\Models\Setting::set('temple_story_image', $normalizeImagePath($request->temple_story_image));
        \App\Models\Setting::set('temple_worship_image', $normalizeImagePath($request->temple_worship_image));
        $themeColors = [
            'saffron-garden' => ['#c45b2c', '#e5ad45', '#24382f'],
            'lotus-teal' => ['#087f8c', '#e7b85b', '#123f4a'],
            'marigold-night' => ['#e28a24', '#f6c85f', '#25213b'],
            'rose-sandal' => ['#b84c65', '#e9b678', '#432936'],
            'custom' => [$request->theme_primary_color, $request->theme_accent_color, $request->theme_dark_color],
        ];
        [$primaryColor, $accentColor, $darkColor] = $themeColors[$request->theme_preset];
        \App\Models\Setting::set('theme_preset', $request->theme_preset);
        \App\Models\Setting::set('theme_primary_color', $primaryColor);
        \App\Models\Setting::set('theme_accent_color', $accentColor);
        \App\Models\Setting::set('theme_dark_color', $darkColor);
        \App\Models\Setting::set('brand_title', $request->brand_title);
        \App\Models\Setting::set('brand_subtitle', $request->brand_subtitle ?? '');
        \App\Models\Setting::set('hours_weekday_morning', $request->hours_weekday_morning);
        \App\Models\Setting::set('hours_weekday_morning_pooja', $request->hours_weekday_morning_pooja);
        \App\Models\Setting::set('hours_weekday_evening', $request->hours_weekday_evening);
        \App\Models\Setting::set('hours_weekday_evening_pooja', $request->hours_weekday_evening_pooja);
        \App\Models\Setting::set('hours_weekend', $request->hours_weekend);
        \App\Models\Setting::set('hours_weekend_pooja', $request->hours_weekend_pooja);
        \App\Models\Setting::set('stripe_enabled', $request->boolean('stripe_enabled') ? '1' : '0');
        \App\Models\Setting::set('stripe_mode', $request->stripe_mode === 'live' ? 'live' : 'test');
        \App\Models\Setting::set('temple_opening_time', $request->temple_opening_time);
        \App\Models\Setting::set('temple_closing_time', $request->temple_closing_time);
        \App\Models\Setting::set('low_stock_threshold', $request->low_stock_threshold);
        \App\Models\Setting::set('max_advance_booking_days', $request->max_advance_booking_days);
        \App\Models\Setting::set('online_pooja_shipping_charge', $request->online_pooja_shipping_charge);

        return redirect()->back()->with('success', 'System settings updated successfully.');
    })->name('admin.settings.update');

    // Role Permissions (configurable access grid per role)
    Route::get('/admin/role-permissions', [\App\Http\Controllers\RolePermissionController::class, 'index'])->name('admin.role-permissions.index');
    Route::post('/admin/role-permissions/{role}', [\App\Http\Controllers\RolePermissionController::class, 'update'])->name('admin.role-permissions.update');

    // QR Links (short, printable URLs that can be repointed to a new target later)
    Route::get('/admin/manage-qr-links', [\App\Http\Controllers\QrLinkController::class, 'index'])->name('admin.qrlinks.index');
    Route::post('/admin/qr-link/store', [\App\Http\Controllers\QrLinkController::class, 'store'])->name('admin.qrlinks.store');
    Route::post('/admin/qr-link/update/{id}', [\App\Http\Controllers\QrLinkController::class, 'update'])->name('admin.qrlinks.update');
    Route::delete('/admin/qr-link/delete/{id}', [\App\Http\Controllers\QrLinkController::class, 'destroy'])->name('admin.qrlinks.delete');

    // Leave Requests Route (Admin management)
    Route::get('/admin/manage-leaves', [TrusteeController::class, 'manageLeaves'])->name('admin.leaves.index');
    Route::post('/admin/leaves/status/{id}', [PriestController::class, 'updateLeaveStatus'])->name('admin.leaves.status');

    // Admin Chat Support Routes
    Route::get('/admin/chats/active', [\App\Http\Controllers\ChatController::class, 'staffGetActiveSessions'])->name('admin.chats.active');
    Route::get('/admin/chats/history', [\App\Http\Controllers\ChatController::class, 'staffGetEndedSessions'])->name('admin.chats.history');
    Route::get('/admin/chats/{session}/messages', [\App\Http\Controllers\ChatController::class, 'staffGetMessages'])->name('admin.chats.messages');
    Route::post('/admin/chats/{session}/reply', [\App\Http\Controllers\ChatController::class, 'staffSendReply'])->name('admin.chats.reply');
    Route::post('/admin/chats/{session}/end', [\App\Http\Controllers\ChatController::class, 'staffEndSession'])->name('admin.chats.end');
});

// ============================================
// BOOKINGS / EVENTS MANAGEMENT (Admin + Committee)
// ============================================
Route::middleware(['auth', 'role:Admin,Committee'])->group(function () {
    // Admin Booking Management Routes
    Route::get('/admin/manage-bookings', [BookingController::class, 'manageBookings'])->name('admin.bookings.index');
    Route::post('/admin/bookings/override-priest/{id}', [BookingController::class, 'overridePriest'])->name('admin.bookings.override-priest');
    Route::post('/admin/bookings/reschedule/{id}', [BookingController::class, 'reschedule'])->name('admin.bookings.reschedule');
    Route::post('/admin/bookings/status/{id}', [BookingController::class, 'updateStatus'])->name('admin.bookings.update-status');

    // Event CRUD & Scheduling Routes
    Route::get('/admin/manage-events', [\App\Http\Controllers\EventController::class, 'manageEvents'])->name('admin.events.index');
    Route::post('/admin/event/store', [\App\Http\Controllers\EventController::class, 'store'])->name('admin.events.store');
    Route::post('/admin/event/update/{id}', [\App\Http\Controllers\EventController::class, 'update'])->name('admin.events.update');
    Route::delete('/admin/event/delete/{id}', [\App\Http\Controllers\EventController::class, 'destroy'])->name('admin.events.delete');
});

// ============================================
// DONATIONS MANAGEMENT (Admin + Committee + Accountant)
// Route access is intentionally broader than the actual view/add/edit/delete
// capability — the Role Permissions grid ("Donations" resource) decides what
// each of these roles can actually do once inside; see DonationController.
// ============================================
Route::middleware(['auth', 'role:Admin,Committee,Accountant'])->group(function () {
    Route::get('/admin/manage-donations', [\App\Http\Controllers\DonationController::class, 'manageDonations'])->name('admin.donations.index');
    Route::post('/admin/donation/store-devotee', [\App\Http\Controllers\DonationController::class, 'storeDevoteeDonation'])->name('admin.donations.storeDevotee');
    Route::post('/admin/donation/store-guest', [\App\Http\Controllers\DonationController::class, 'storeGuestDonation'])->name('admin.donations.storeGuest');
    Route::post('/admin/donation/update-devotee/{id}', [\App\Http\Controllers\DonationController::class, 'updateDevoteeDonation'])->name('admin.donations.updateDevotee');
    Route::post('/admin/donation/update-guest/{id}', [\App\Http\Controllers\DonationController::class, 'updateGuestDonation'])->name('admin.donations.updateGuest');
    Route::delete('/admin/donation/delete-devotee/{id}', [\App\Http\Controllers\DonationController::class, 'deleteDevoteeDonation'])->name('admin.donations.deleteDevotee');
    Route::delete('/admin/donation/delete-guest/{id}', [\App\Http\Controllers\DonationController::class, 'deleteGuestDonation'])->name('admin.donations.deleteGuest');
    Route::post('/admin/donation/resend-receipt/{type}/{id}', [\App\Http\Controllers\DonationController::class, 'resendReceipt'])->name('admin.donations.resendReceipt');
    Route::post('/admin/donation/approve-guest/{id}', [\App\Http\Controllers\DonationController::class, 'approveGuestDonation'])->name('admin.donations.approveGuest');
    Route::post('/admin/donation/approve-devotee/{id}', [\App\Http\Controllers\DonationController::class, 'approveDevoteeDonation'])->name('admin.donations.approveDevotee');
});

// ============================================
// COMMITTEE ROUTES (Restricted via RoleMiddleware)
// ============================================
Route::middleware(['auth', 'role.committee'])->group(function () {
    Route::get('/committee/dashboard', [CommitteeController::class, 'dashboard'])->name('committee.dashboard');
});

// ============================================
// DEVOTEE ROUTES (Restricted via RoleMiddleware)
// ============================================
Route::middleware(['auth', 'role.devotee'])->group(function () {
    Route::get('/devotee/dashboard', [DevoteeController::class, 'dashboard'])->name('devotee.dashboard');

    // Devotee Pooja Booking Routes
    Route::get('/devotee/book-pooja', [BookingController::class, 'bookPoojaPage'])->name('devotee.book-pooja');
    Route::get('/devotee/book_pooja', [BookingController::class, 'bookPoojaPage']);
    Route::post('/devotee/book-pooja', [BookingController::class, 'storeBooking'])->name('devotee.book-pooja.post');
    Route::post('/devotee/book_pooja', [BookingController::class, 'storeBooking']);
    Route::get('/devotee/booking/receipt/{id}', [BookingController::class, 'downloadReceipt'])->name('devotee.bookings.receipt');

    // Devotee Payment Routes
    Route::get('/devotee/payment', [DevoteeController::class, 'showPaymentPage'])->name('devotee.payment');
    Route::post('/devotee/payment/process', [DevoteeController::class, 'processPayment'])->name('devotee.payment.process');

    // Devotee Donation Routes (same Bank/Cash/Online flow as the public donation pages)
    Route::get('/devotee/donate', [\App\Http\Controllers\DonationController::class, 'showDevoteeDonatePage'])->name('devotee.donate');
    Route::post('/devotee/donate', [\App\Http\Controllers\DonationController::class, 'storeDevoteeSelfDonation'])->name('devotee.donate.post');

    // Devotee Chatbot Routes
    Route::get('/devotee/chat/session', [\App\Http\Controllers\ChatController::class, 'getSession'])->name('devotee.chat.session');
    Route::get('/devotee/chat/messages', [\App\Http\Controllers\ChatController::class, 'getMessages'])->name('devotee.chat.messages');
    Route::post('/devotee/chat/send', [\App\Http\Controllers\ChatController::class, 'sendMessage'])->name('devotee.chat.send');
    Route::post('/devotee/chat/end', [\App\Http\Controllers\ChatController::class, 'endSession'])->name('devotee.chat.end');
});

// ============================================
// PRIEST ROUTES (Restricted via RoleMiddleware)
// ============================================
Route::middleware(['auth', 'role.priest'])->group(function () {
    Route::get('/priest/dashboard', [PriestController::class, 'dashboard'])->name('priest.dashboard');
    Route::post('/priest/attendance/toggle', [PriestController::class, 'toggleOnlineStatus'])->name('priest.attendance.toggle');
    Route::post('/priest/attendance/present', [PriestController::class, 'markPresent'])->name('priest.attendance.present');
    Route::post('/priest/attendance/end', [PriestController::class, 'endWork'])->name('priest.attendance.end');
    Route::post('/priest/pooja/complete/{id}', [PriestController::class, 'completePooja'])->name('priest.pooja.complete');
    Route::post('/priest/leave/request', [PriestController::class, 'requestLeave'])->name('priest.leave.request');
});

// ============================================
// TRUSTEE ROUTES (Restricted via RoleMiddleware)
// ============================================
Route::middleware(['auth', 'role.trustee'])->group(function () {
    Route::get('/trustee/dashboard', [TrusteeController::class, 'dashboard'])->name('trustee.dashboard');
});

// ============================================
// STAFF ROUTES (Restricted via RoleMiddleware)
// ============================================
Route::middleware(['auth', 'role.staff'])->group(function () {
    Route::get('/staff/dashboard', [StaffController::class, 'dashboard'])->name('staff.dashboard');
    Route::post('/staff/attendance/toggle', [StaffController::class, 'toggleOnlineStatus'])->name('staff.attendance.toggle');
    Route::post('/staff/attendance/present', [StaffController::class, 'markPresent'])->name('staff.attendance.present');
    Route::post('/staff/attendance/end', [StaffController::class, 'endWork'])->name('staff.attendance.end');

    // Staff Chat Support Routes
    Route::get('/staff/chats/active', [\App\Http\Controllers\ChatController::class, 'staffGetActiveSessions'])->name('staff.chats.active');
    Route::get('/staff/chats/history', [\App\Http\Controllers\ChatController::class, 'staffGetEndedSessions'])->name('staff.chats.history');
    Route::get('/staff/chats/{session}/messages', [\App\Http\Controllers\ChatController::class, 'staffGetMessages'])->name('staff.chats.messages');
    Route::post('/staff/chats/{session}/reply', [\App\Http\Controllers\ChatController::class, 'staffSendReply'])->name('staff.chats.reply');
    Route::post('/staff/chats/{session}/end', [\App\Http\Controllers\ChatController::class, 'staffEndSession'])->name('staff.chats.end');

    // Staff Offline Counter Routes
    Route::post('/staff/counter/book-pooja', [StaffController::class, 'counterBookPooja'])->name('staff.counter.book-pooja');
    Route::post('/staff/counter/record-donation', [StaffController::class, 'counterRecordDonation'])->name('staff.counter.record-donation');
});

// ============================================
// ACCOUNTANT ROUTES (Restricted via RoleMiddleware)
// ============================================
Route::middleware(['auth', 'role.accountant'])->group(function () {
    Route::get('/accountant/dashboard', [AccountantController::class, 'dashboard'])->name('accountant.dashboard');
});

// ============================================
// COMMON AUTHORIZED AJAX ENDPOINTS
// ============================================
Route::middleware(['auth'])->group(function () {
    // Profile Update Route
    Route::post('/profile/update', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');

    // Availability AJAX Endpoints
    Route::get('/booking/check-availability', [BookingController::class, 'checkAvailability'])->name('booking.check-availability');
    Route::get('/booking/check-date-status', [BookingController::class, 'checkDateStatus'])->name('booking.check-date-status');

    // Role Switcher Route removed (role switching inside dashboard is deprecated)

    // Admin - Notifications AJAX mark as read
    Route::post('/admin/notifications/mark-read', function () {
        $user = Auth::user();
        if ($user) {
            DB::table('notifications')
                ->where('user_id', $user->id)
                ->update(['is_read' => true, 'updated_at' => now()]);
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 401);
    })->name('admin.notifications.mark-read');
});

// Admin & Accountant only - Salary Management, Payouts and System Reports.
// Previously sat under the plain 'auth' group above with no role check, meaning any
// authenticated user (including a Devotee) could reach these URLs directly.
Route::middleware(['auth', 'role:Admin,Accountant'])->group(function () {
    Route::get('/admin/salaries', [\App\Http\Controllers\SalaryController::class, 'index'])->name('admin.salaries.index');
    Route::post('/admin/salaries/sanction', [\App\Http\Controllers\SalaryController::class, 'sanction'])->name('admin.salaries.sanction');
    Route::get('/admin/reports', [\App\Http\Controllers\SalaryController::class, 'reports'])->name('admin.reports.index');
});