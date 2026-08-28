@extends('admin.layouts.app')

@section('title', 'System Settings')

@section('page-css')
<style>
    .settings-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        padding: 32px;
        max-width: 1180px;
        margin: 0 auto;
    }
    .settings-card h2 {
        font-weight: 700;
        font-size: 1.6rem;
        color: #2d1f0e;
        margin-bottom: 24px;
        border-bottom: 1px solid #f0ece6;
        padding-bottom: 16px;
    }
    .settings-nav {
        display: flex;
        flex-direction: column;
        gap: 4px;
        position: sticky;
        top: 20px;
    }
    .settings-nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        text-align: left;
        background: transparent;
        border: none;
        border-radius: 12px;
        padding: 12px 16px;
        font-weight: 600;
        font-size: 0.9rem;
        color: #7b6b5a;
        transition: all 0.2s;
    }
    .settings-nav-link:hover {
        background: #faf8f5;
        color: #b8863a;
    }
    .settings-nav-link.active {
        background: #b8863a;
        color: white;
        box-shadow: 0 4px 12px rgba(184, 134, 58, 0.2);
    }
    .settings-panel { display: none; }
    .settings-panel.active { display: block; }
    .settings-section {
        background: #faf8f5;
        border: 1px solid rgba(184, 134, 58, 0.08);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .settings-section h5 {
        font-weight: 600;
        color: #b8863a;
        margin-bottom: 16px;
    }
    .option-card {
        background: white;
        border: 1.5px solid #ebdcc5;
        border-radius: 12px;
        padding: 16px 20px;
        transition: all 0.3s;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .option-card:hover {
        border-color: #b8863a;
        background: #fffdfb;
    }
    .option-card input[type="radio"] {
        accent-color: #ff6f00;
        width: 18px;
        height: 18px;
    }
    .option-title {
        font-weight: 600;
        color: #2d1f0e;
        margin: 0;
    }
    .option-desc {
        font-size: 0.85rem;
        color: #7b6b5a;
        margin: 4px 0 0 0;
    }
    .logo-preview {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        border: 1px solid #ebdcc5;
        object-fit: contain;
        background: white;
        padding: 6px;
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
    @media (max-width: 991.98px) {
        .settings-nav { flex-direction: row; overflow-x: auto; position: static; }
        .settings-nav-link { white-space: nowrap; }
    }
</style>
@endsection

@section('content')
<div class="container py-4">
    @if(session('success'))
        <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4 p-3 d-flex align-items-center" style="background: #d1fae5; color: #065f46;">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 p-3" style="background: #fee2e2; color: #991b1b;">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="settings-card">
        <h2><i class="bi bi-gear-fill text-warning me-2"></i>System Settings</h2>

        <form action="{{ route('admin.settings.update') }}" method="POST">
            @csrf

            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="settings-nav">
                        <button type="button" class="settings-nav-link active" data-panel="general"><i class="bi bi-cpu"></i> General</button>
                        <button type="button" class="settings-nav-link" data-panel="temple-info"><i class="bi bi-building"></i> Temple Information</button>
                        <button type="button" class="settings-nav-link" data-panel="branding"><i class="bi bi-palette"></i> Branding & Theme</button>
                        <button type="button" class="settings-nav-link" data-panel="admin-branding"><i class="bi bi-shield-lock"></i> Admin Panel Branding</button>
                        <button type="button" class="settings-nav-link" data-panel="donations"><i class="bi bi-wallet2"></i> Donations & Payments</button>
                        <button type="button" class="settings-nav-link" data-panel="hours"><i class="bi bi-clock"></i> Temple Hours</button>
                        <button type="button" class="settings-nav-link" data-panel="inventory"><i class="bi bi-sliders"></i> Inventory & Booking</button>
                    </div>
                </div>

                <div class="col-lg-9">
                    <!-- GENERAL -->
                    <div class="settings-panel active" data-panel-content="general">
                        <div class="settings-section">
                            <h5><i class="bi bi-cpu me-2"></i>System Mode</h5>
                            <p class="text-muted small mb-3">Configure how the application processes user registration and credential dispatches.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="option-card" for="mode_testing">
                                        <input type="radio" name="system_mode" id="mode_testing" value="Testing Mode" {{ $systemMode === 'Testing Mode' ? 'checked' : '' }}>
                                        <div>
                                            <p class="option-title">Testing Mode</p>
                                            <p class="option-desc">Display generated user passwords on-screen. Emails are optional.</p>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <label class="option-card" for="mode_live">
                                        <input type="radio" name="system_mode" id="mode_live" value="Live Mode" {{ $systemMode === 'Live Mode' ? 'checked' : '' }}>
                                        <div>
                                            <p class="option-title">Live Mode</p>
                                            <p class="option-desc">Automate credentials email transfer. Never display passwords on-screen.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="settings-section" id="email_handling_section">
                            <h5><i class="bi bi-envelope-paper me-2"></i>Testing Mode Email Handling</h5>
                            <p class="text-muted small mb-3">Determine if credentials are sent by email when the system runs in Testing Mode.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="option-card" for="email_send">
                                        <input type="radio" name="testing_email_handling" id="email_send" value="Send Emails" {{ $emailHandling === 'Send Emails' ? 'checked' : '' }}>
                                        <div>
                                            <p class="option-title">Send Emails</p>
                                            <p class="option-desc">Display password on screen and send credentials by email.</p>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <label class="option-card" for="email_no_send">
                                        <input type="radio" name="testing_email_handling" id="email_no_send" value="Do Not Send Emails" {{ $emailHandling === 'Do Not Send Emails' ? 'checked' : '' }}>
                                        <div>
                                            <p class="option-title">Do Not Send Emails</p>
                                            <p class="option-desc">Password shown only on screen. No emails will be sent.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TEMPLE INFORMATION -->
                    <div class="settings-panel" data-panel-content="temple-info">
                        <div class="settings-section">
                            <h5><i class="bi bi-building me-2"></i>Temple Information</h5>
                            <p class="text-muted small mb-3">Identification details shown across the public site.</p>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark">Temple Display Name</label>
                                    <input type="text" name="temple_name" class="form-control rounded-3" value="{{ $templeName }}" placeholder="e.g. Golden Temple" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Subtitle</label>
                                    <input type="text" name="temple_subtitle" class="form-control rounded-3" value="{{ $templeSubtitle }}" placeholder="e.g. Ganesha Temple" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Brand Title</label>
                                    <input type="text" name="brand_title" class="form-control rounded-3" value="{{ $brandTitle }}" placeholder="e.g. SSVK" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Brand Subtitle</label>
                                    <input type="text" name="brand_subtitle" class="form-control rounded-3" value="{{ $brandSubtitle }}" placeholder="Optional short brand line">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Brand Eyebrow</label>
                                    <input type="text" name="temple_eyebrow" class="form-control rounded-3" value="{{ $templeEyebrow }}" placeholder="Short welcome line" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark">Homepage Introduction</label>
                                    <textarea name="temple_description" class="form-control rounded-3" rows="3" required>{{ $templeDescription }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Public Address</label>
                                    <input type="text" name="temple_address" class="form-control rounded-3" value="{{ $templeAddress }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Public Phone</label>
                                    <input type="text" name="temple_phone" class="form-control rounded-3" value="{{ $templePhone }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Public Email</label>
                                    <input type="email" name="temple_email" class="form-control rounded-3" value="{{ $templeEmail }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BRANDING & THEME -->
                    <div class="settings-panel" data-panel-content="branding">
                        <div class="settings-section">
                            <h5><i class="bi bi-palette me-2"></i>Branding & Theme</h5>
                            <p class="text-muted small mb-3">Logo, imagery and colour palette used on the public homepage.</p>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark">Logo URL or public path</label>
                                    <input type="text" name="temple_logo" class="form-control rounded-3" value="{{ $templeLogo }}" placeholder="/images/temple-logo.png or https://..."><div class="form-text">Leave blank to use the Om symbol. Upload the image to <code>public/images</code> and enter its path.</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark">Homepage Hero Photo URL or public path</label>
                                    <input type="text" name="temple_hero_image" class="form-control rounded-3" value="{{ $templeHeroImage }}" placeholder="/images/hero.jpg" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Story Section Image</label>
                                    <input type="text" name="temple_story_image" class="form-control rounded-3" value="{{ $templeStoryImage }}" placeholder="/images/story.jpg" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Worship Section Image</label>
                                    <input type="text" name="temple_worship_image" class="form-control rounded-3" value="{{ $templeWorshipImage }}" placeholder="/images/worship.jpg" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark">Homepage Colour Theme</label>
                                    <select name="theme_preset" id="theme_preset" class="form-select rounded-3 mb-3" required>
                                        <option value="saffron-garden" data-primary="#c45b2c" data-accent="#e5ad45" data-dark="#24382f" {{ $themePreset === 'saffron-garden' ? 'selected' : '' }}>Saffron Garden</option>
                                        <option value="lotus-teal" data-primary="#087f8c" data-accent="#e7b85b" data-dark="#123f4a" {{ $themePreset === 'lotus-teal' ? 'selected' : '' }}>Lotus Teal</option>
                                        <option value="marigold-night" data-primary="#e28a24" data-accent="#f6c85f" data-dark="#25213b" {{ $themePreset === 'marigold-night' ? 'selected' : '' }}>Marigold Night</option>
                                        <option value="rose-sandal" data-primary="#b84c65" data-accent="#e9b678" data-dark="#432936" {{ $themePreset === 'rose-sandal' ? 'selected' : '' }}>Rose Sandal</option>
                                        <option value="custom" {{ $themePreset === 'custom' ? 'selected' : '' }}>Custom Colours</option>
                                    </select>
                                    <div class="row g-3">
                                        <div class="col-md-4"><label class="form-label small text-muted">Primary</label><input type="color" name="theme_primary_color" class="form-control form-control-color w-100" value="{{ $themePrimaryColor }}" title="Primary colour"></div>
                                        <div class="col-md-4"><label class="form-label small text-muted">Accent</label><input type="color" name="theme_accent_color" class="form-control form-control-color w-100" value="{{ $themeAccentColor }}" title="Accent colour"></div>
                                        <div class="col-md-4"><label class="form-label small text-muted">Dark</label><input type="color" name="theme_dark_color" class="form-control form-control-color w-100" value="{{ $themeDarkColor }}" title="Dark colour"></div>
                                    </div>
                                    <div class="form-text">These colours update the homepage buttons, highlights, navigation, footer and feature bands.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ADMIN PANEL BRANDING -->
                    <div class="settings-panel" data-panel-content="admin-branding">
                        <div class="settings-section">
                            <h5><i class="bi bi-shield-lock me-2"></i>Admin Panel Branding</h5>
                            <p class="text-muted small mb-3">The logo icon and text shown in the top-left of every internal dashboard (Admin, Devotee, Priest, Staff, Trustee, Accountant) — independent of the public homepage branding above.</p>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <img class="logo-preview" src="{{ $adminLogoIcon }}" alt="Current admin logo icon">
                                </div>
                                <div class="col-md-10">
                                    <label class="form-label fw-semibold text-dark">Admin Logo Icon (image path or URL)</label>
                                    <input type="text" name="admin_logo_icon" class="form-control rounded-3" value="{{ $adminLogoIcon }}" placeholder="/images/logo.gif or https://...">
                                    <div class="form-text">Defaults to <code>images/logo.gif</code>. Upload the image to <code>public/images</code> and enter its path.</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark">Admin Logo Text</label>
                                    <input type="text" name="admin_logo_text" class="form-control rounded-3" value="{{ $adminLogoText }}" placeholder="SSVK ERP">
                                    <div class="form-text">The last word is highlighted in the theme's accent colour, e.g. "SSVK <strong>ERP</strong>".</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DONATIONS & PAYMENTS -->
                    <div class="settings-panel" data-panel-content="donations">
                        <div class="settings-section">
                            <h5><i class="bi bi-bank2 me-2"></i>Temple Donation Account</h5>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">Account Name</label><input type="text" name="donation_account_name" class="form-control rounded-3" value="{{ $donationAccountName }}" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">Bank Name</label><input type="text" name="donation_bank_name" class="form-control rounded-3" value="{{ $donationBankName }}" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">BSB</label><input type="text" name="donation_bsb" class="form-control rounded-3" value="{{ $donationBsb }}" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">Account Number</label><input type="text" name="donation_account_number" class="form-control rounded-3" value="{{ $donationAccountNumber }}" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">Donation Receipt Email</label><input type="email" name="donation_receipt_email" class="form-control rounded-3" value="{{ $donationReceiptEmail }}" required></div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Currency Code</label>
                                    <input type="text" name="currency_code" class="form-control rounded-3" value="{{ $currencyCode }}" maxlength="3" required><div class="form-text">Use a three-letter code such as AUD.</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark">Donation Coordinator Email(s)</label>
                                    <input type="text" name="donation_coordinator_emails" class="form-control rounded-3" placeholder="coordinator1@example.com, coordinator2@example.com" value="{{ $donationCoordinatorEmails }}">
                                    <div class="form-text">Comma-separated. CC'd on donation receipts that aren't tied to an event with its own coordinator emails (set per-event on the Events page).</div>
                                </div>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h5><i class="bi bi-credit-card me-2"></i>Online Donations</h5>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="stripe_enabled" value="1" id="stripe_enabled" {{ $stripeEnabled ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="stripe_enabled">Enable Stripe donations</label>
                            </div>
                            <div class="form-text">The Stripe keys and Checkout integration still need to be configured in the application environment before accepting live payments.</div>
                        </div>
                    </div>

                    <!-- TEMPLE HOURS -->
                    <div class="settings-panel" data-panel-content="hours">
                        <div class="settings-section">
                            <h5><i class="bi bi-clock me-2"></i>Daily Operating Hours</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Daily Opening Time</label>
                                    <input type="time" name="temple_opening_time" class="form-control rounded-3" value="{{ $templeOpeningTime }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Daily Closing Time</label>
                                    <input type="time" name="temple_closing_time" class="form-control rounded-3" value="{{ $templeClosingTime }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h5><i class="bi bi-calendar-week me-2"></i>Homepage Opening Hours</h5>
                            <p class="text-muted small mb-3">These values appear in the landing-page hero/visit panel. Enter text such as "7:30 am - 12:00 noon".</p>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">Monday to Friday morning</label><input type="text" name="hours_weekday_morning" class="form-control rounded-3" value="{{ $hoursWeekdayMorning }}" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">Morning pooja</label><input type="text" name="hours_weekday_morning_pooja" class="form-control rounded-3" value="{{ $hoursWeekdayMorningPooja }}" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">Monday to Friday evening</label><input type="text" name="hours_weekday_evening" class="form-control rounded-3" value="{{ $hoursWeekdayEvening }}" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">Evening pooja</label><input type="text" name="hours_weekday_evening_pooja" class="form-control rounded-3" value="{{ $hoursWeekdayEveningPooja }}" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">Saturday and Sunday</label><input type="text" name="hours_weekend" class="form-control rounded-3" value="{{ $hoursWeekend }}" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold text-dark">Weekend pooja</label><input type="text" name="hours_weekend_pooja" class="form-control rounded-3" value="{{ $hoursWeekendPooja }}" required></div>
                            </div>
                        </div>
                    </div>

                    <!-- INVENTORY & BOOKING -->
                    <div class="settings-panel" data-panel-content="inventory">
                        <div class="settings-section">
                            <h5><i class="bi bi-sliders me-2"></i>Inventory & Booking Rules</h5>
                            <p class="text-muted small mb-3">Define standard defaults for stock thresholds, online bookings limits, and delivery surcharges.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Default Low Stock Threshold</label>
                                    <input type="number" step="0.01" name="low_stock_threshold" class="form-control rounded-3" value="{{ $lowStockThreshold }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Max Advance Booking Limit (Days)</label>
                                    <input type="number" name="max_advance_booking_days" class="form-control rounded-3" value="{{ $maxAdvanceBookingDays }}" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark">Prasad Delivery Shipping Charge</label>
                                    <div class="input-group">
                                        <span class="input-group-text rounded-start-3 bg-light">{{ $currencyCode }}</span>
                                        <input type="number" step="0.01" name="online_pooja_shipping_charge" class="form-control rounded-end-3" value="{{ $onlinePoojaShippingCharge }}" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-submit">Save Settings</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@section('page-js')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const testingRadio = document.getElementById('mode_testing');
        const liveRadio = document.getElementById('mode_live');
        const emailSection = document.getElementById('email_handling_section');

        function toggleEmailSection() {
            if (liveRadio.checked) {
                emailSection.style.opacity = '0.5';
                emailSection.querySelectorAll('input').forEach(input => input.disabled = true);
            } else {
                emailSection.style.opacity = '1';
                emailSection.querySelectorAll('input').forEach(input => input.disabled = false);
            }
        }

        testingRadio.addEventListener('change', toggleEmailSection);
        liveRadio.addEventListener('change', toggleEmailSection);
        toggleEmailSection(); // initial call

        const themeSelect = document.getElementById('theme_preset');
        const colourInputs = {
            primary: document.querySelector('[name="theme_primary_color"]'),
            accent: document.querySelector('[name="theme_accent_color"]'),
            dark: document.querySelector('[name="theme_dark_color"]')
        };
        themeSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (this.value !== 'custom') {
                colourInputs.primary.value = option.dataset.primary;
                colourInputs.accent.value = option.dataset.accent;
                colourInputs.dark.value = option.dataset.dark;
            }
        });

        // Settings category navigation
        const navLinks = document.querySelectorAll('.settings-nav-link');
        const panels = document.querySelectorAll('.settings-panel');
        navLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                navLinks.forEach(function (l) { l.classList.remove('active'); });
                panels.forEach(function (p) { p.classList.remove('active'); });
                this.classList.add('active');
                document.querySelector('[data-panel-content="' + this.dataset.panel + '"]').classList.add('active');
            });
        });
    });
</script>
@endsection
@endsection
