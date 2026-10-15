<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book a Pooja | {{ $temple['name'] }}</title>
    <link rel="icon" type="image/gif" href="{{ $temple['logo'] }}">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <style>
        :root { --primary: {{ $temple['primary_color'] }}; --accent: {{ $temple['accent_color'] }}; --dark: {{ $temple['dark_color'] }}; --cream:#fbf8f1; --ink:#25231f; --muted:#716c64; --line:#e9e1d5; --serif:'Playfair Display',Georgia,serif; --sans:'DM Sans',sans-serif; }
        * { box-sizing:border-box; } body { margin:0; color:var(--ink); background:var(--cream); font-family:var(--sans); } h1,h2,h3 { font-family:var(--serif); } a { text-decoration:none; }

        {{-- Same nav/announcement styling as frontend/index.blade.php, so this page reads
             as part of the site rather than a disconnected standalone form. --}}
        .announcement { background:var(--dark); color:#fff; font-size:.78rem; letter-spacing:.04em; } .announcement a { color:var(--accent); }
        .site-nav { background:rgba(251,248,241,.97); border-bottom:1px solid var(--line); } .brand-mark { width:48px; height:48px; object-fit:contain; } .brand-om { color:var(--primary); font-size:2.45rem; line-height:1; } .brand-title { font:700 1.1rem var(--serif); color:var(--dark); line-height:1.15; } .brand-subtitle { color:var(--muted); font-size:.68rem; letter-spacing:.13em; text-transform:uppercase; }
        .navbar-nav .nav-link { color:var(--ink); font-size:.82rem; font-weight:600; padding:.75rem .7rem !important; } .navbar-nav .nav-link:hover,.navbar-nav .nav-link:focus { color:var(--primary); }
        .btn-temple { background:var(--primary); border-color:var(--primary); color:#fff; font-weight:700; border-radius:5px; padding:.75rem 1.15rem; } .btn-temple:hover { background:var(--dark); border-color:var(--dark); color:#fff; } .btn-outline-temple { border:1px solid var(--primary); color:var(--primary); border-radius:5px; font-weight:700; padding:.75rem 1.15rem; } .btn-outline-temple:hover { background:var(--primary); color:#fff; }

        .pooja-hero { background:var(--dark); color:#fff; padding:2.5rem 0 3rem; }
        .pooja-hero .kicker { color:var(--accent); font-weight:700; font-size:.8rem; letter-spacing:.15em; text-transform:uppercase; }
        .pooja-hero h1 { font-size:clamp(1.9rem,4vw,3rem); margin:.6rem 0 1rem; }
        .pooja-hero p { color:#e8e4d8; font-size:1.05rem; max-width:620px; margin:0 0 1.5rem; line-height:1.7; }

        {{-- Login-vs-guest choice — sits right under the hero's own intro paragraph, before
             anyone scrolls to the guest form, so it's the first real decision the page
             asks for rather than an entry assumed by default. --}}
        .entry-choice { display:flex; flex-wrap:wrap; gap:.85rem; }
        .entry-choice a { display:inline-flex; align-items:center; gap:.5rem; font-weight:700; font-size:.92rem; padding:.7rem 1.3rem; border-radius:999px; }
        .entry-choice .choice-login { background:var(--accent); color:var(--dark); }
        .entry-choice .choice-login:hover { background:#fff; color:var(--dark); }
        .entry-choice .choice-guest { border:1.5px solid rgba(255,255,255,.55); color:#fff; }
        .entry-choice .choice-guest:hover { background:rgba(255,255,255,.12); color:#fff; }

        {{-- Category descriptions — a quick orientation before the booking form itself,
             since a first-time visitor has no way to know what "Sponsored Abishekam" vs
             "Other Function" actually means before picking one. --}}
        .category-intro { background:#fff; border-bottom:1px solid var(--line); padding:2.75rem 0; }
        .category-intro .section-kicker { color:var(--primary); font-size:.76rem; font-weight:700; letter-spacing:.17em; text-transform:uppercase; }
        .category-intro h2 { color:var(--dark); font-size:clamp(1.3rem,2vw,1.7rem); margin:.4rem 0 1.5rem; }
        .category-card { height:100%; padding:1.25rem 1.4rem; border:1px solid var(--line); border-radius:10px; background:color-mix(in srgb, var(--line) 18%, white); }
        .category-card i { color:var(--primary); font-size:1.3rem; margin-bottom:.5rem; display:block; }
        .category-card h4 { font-family:var(--sans); font-weight:700; font-size:.92rem; color:var(--dark); margin:0 0 .35rem; }
        .category-card p { color:var(--muted); font-size:.82rem; line-height:1.6; margin:0; }

        .section-pad { padding:3rem 0 4rem; }
        .booking-card { background:#fff; border-radius:14px; box-shadow:0 20px 50px rgba(37,35,31,.1); border:1px solid var(--line); padding:2.5rem; }
        .booking-card label { font-size:.76rem; font-weight:700; letter-spacing:.03em; color:var(--dark); margin-bottom:.35rem; display:inline-block; }
        .booking-card .form-control, .booking-card .form-select {
            border:1.5px solid var(--line); border-radius:8px; padding:.7rem .9rem; font-size:.97rem; color:var(--ink);
            background:color-mix(in srgb, var(--line) 20%, white);
        }
        .booking-card .form-control:focus, .booking-card .form-select:focus {
            border-color:var(--primary); background:#fff; outline:none; box-shadow:0 0 0 4px color-mix(in srgb, var(--primary) 16%, transparent);
        }
        .pooja-fee-note { color:var(--muted); font-size:.85rem; margin-top:.35rem; }

        .pay-method-options { display:grid; grid-template-columns:repeat(2, 1fr); gap:.75rem; }
        @media (max-width:560px) { .pay-method-options { grid-template-columns:1fr; } }
        .pay-method-option { border:1.5px solid var(--line); border-radius:10px; padding:1rem 1.1rem; cursor:pointer; display:flex; align-items:center; gap:.65rem; }
        .pay-method-option:has(input:checked) { border-color:var(--primary); background:color-mix(in srgb, var(--primary) 8%, white); }
        .pay-method-option i { font-size:1.3rem; color:var(--primary); }
        .pay-method-option .pm-title { font-weight:700; color:var(--dark); font-size:.92rem; }
        .pay-method-option .pm-sub { color:var(--muted); font-size:.78rem; }

        .btn-book-submit { background:linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 55%, black)); border:none; color:#fff; font-weight:700; border-radius:8px; padding:.85rem 1.5rem; font-size:1rem; width:100%; }
        .btn-book-submit:hover { color:#fff; }

        .booking-confirm-popup { position:fixed; inset:0; background:rgba(15,13,10,.55); display:flex; align-items:center; justify-content:center; z-index:1000; padding:1.25rem; }
        .booking-confirm-box { background:#fff; border-radius:14px; padding:2.25rem; max-width:460px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.25); }
        .booking-confirm-box i { font-size:2.5rem; color:#1f9d6a; margin-bottom:.75rem; display:block; }
        .booking-confirm-box p { color:var(--ink); line-height:1.7; margin-bottom:1.25rem; }

        @media (max-width:360px) {
            .g-recaptcha { transform:scale(0.86); transform-origin:0 0; margin-bottom:-14px !important; }
        }
    </style>
</head>
<body>
    <div class="announcement"><div class="container py-2 d-flex flex-wrap justify-content-between gap-2"><span><i class="bi bi-geo-alt me-1"></i>{{ $temple['address'] }}</span><a href="tel:{{ $temple['phone'] }}"><i class="bi bi-telephone me-1"></i>{{ $temple['phone'] }}</a></div></div>
    <nav class="navbar navbar-expand-lg site-nav sticky-top" aria-label="Main navigation">
        <div class="container py-2">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                @if($temple['logo'])<img class="brand-mark" src="{{ $temple['logo'] }}" alt="{{ $temple['name'] }} logo">@else<span class="brand-mark brand-om">ॐ</span>@endif
                <span><span class="brand-title d-block">{{ $temple['name'] }}</span><span class="brand-subtitle d-block">{{ $temple['subtitle'] }}</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainNavigation">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#about">About</a></li>
                    <li class="nav-item"><a class="nav-link fw-bold" style="color:var(--primary)" href="{{ route('booking.public.show') }}">Pooja Bookings</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#calendar">Festival Calendar</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#contact">Contact</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-temple" href="{{ route('home') }}#give">Donate</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-outline-temple" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="pooja-hero">
        <div class="container">
            <div class="kicker">Pooja Booking</div>
            <h1>Book a Pooja at {{ $temple['name'] }}</h1>
            <p>Reserve a pooja for your family — pick a ritual, date and time below. We'll confirm your booking and send the payment details by email.</p>
            <div class="entry-choice">
                <a class="choice-login" href="{{ route('login') }}"><i class="bi bi-person-check-fill"></i>Log in — I already have an account</a>
                <a class="choice-guest" href="#guestBookingForm"><i class="bi bi-arrow-down-circle"></i>Continue as a guest</a>
            </div>
        </div>
    </div>

    {{-- Pooja categories — a quick orientation before the form below, since "Sponsored
         Abishekam" vs "Other Function" means nothing to a first-time visitor without one. --}}
    @php
        $categoryBlurbs = [
            'Sponsored Poojas' => ['icon' => 'bi-flower1', 'text' => "Personal poojas you sponsor for your family's wellbeing, performed on a date and time of your choosing."],
            'Sponsored Abishekams' => ['icon' => 'bi-droplet-fill', 'text' => 'Sacred abishekams — ritual bathing of the deity — sponsored in your family\'s name.'],
            'Festivals' => ['icon' => 'bi-calendar-heart', 'text' => "Poojas tied to the temple's own festival calendar throughout the year."],
            'Other Functions' => ['icon' => 'bi-building', 'text' => 'Personal functions and ceremonies held at the temple itself.'],
            'Functions Outside the Temple' => ['icon' => 'bi-house-door-fill', 'text' => 'A priest performing a pooja or ceremony at your own home or venue.'],
        ];
        $presentCategories = $poojas->pluck('category')->unique()->filter();
    @endphp
    @if($presentCategories->isNotEmpty())
    <div class="category-intro">
        <div class="container">
            <div class="section-kicker">What you can book</div>
            <h2>A pooja for every occasion</h2>
            <div class="row g-3">
                @foreach($presentCategories->sort() as $category)
                @php $blurb = $categoryBlurbs[$category] ?? ['icon' => 'bi-journal-bookmark-fill', 'text' => 'One of the pooja options available below.']; @endphp
                <div class="col-sm-6 col-lg-4">
                    <div class="category-card">
                        <i class="bi {{ $blurb['icon'] }}"></i>
                        <h4>{{ $category }}</h4>
                        <p>{{ $blurb['text'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="section-pad" id="guestBookingForm">
        <div class="container" style="max-width:720px;">
            @if(session('success_booking') || $errors->any() || session('error'))
            <div class="booking-confirm-popup" id="bookingPopup">
                <div class="booking-confirm-box">
                    @if(session('success_booking'))
                        <i class="bi bi-check-circle-fill"></i>
                        <p>{{ session('success_booking') }}</p>
                    @elseif(session('error'))
                        <i class="bi bi-exclamation-circle-fill" style="color:#dc3545;"></i>
                        <p>{{ session('error') }}</p>
                    @else
                        <i class="bi bi-exclamation-circle-fill" style="color:#dc3545;"></i>
                        <p>Please check the form — some details need fixing.</p>
                    @endif
                    <button type="button" class="btn btn-sm" style="background:var(--primary); color:#fff; border-radius:999px; padding:.5rem 1.5rem; font-weight:700; border:none;" onclick="document.getElementById('bookingPopup').remove()">Close</button>
                </div>
            </div>
            @endif

            <div class="booking-card">
                @if($poojas->isEmpty())
                    <p class="mb-0" style="color:var(--muted);">No poojas are currently available for booking. Please check back later or contact the temple directly.</p>
                @else
                <form method="POST" action="{{ route('booking.public.store') }}" id="publicBookingForm">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Pooja</label>
                            <select name="pooja_id" class="form-select" id="pb_pooja_id" required>
                                <option value="" disabled selected>Select a pooja</option>
                                @foreach($poojas->groupBy(fn($p) => $p->category ?: 'General') as $categoryName => $categoryPoojas)
                                    <optgroup label="{{ $categoryName }}">
                                        @foreach($categoryPoojas as $pooja)
                                            <option value="{{ $pooja->pooja_id }}" data-fee="{{ $pooja->pooja_fee }}" {{ old('pooja_id') == $pooja->pooja_id ? 'selected' : '' }}>
                                                {{ $pooja->pooja_name }} — {{ $temple['currency'] }} {{ number_format($pooja->pooja_fee, 2) }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <div class="pooja-fee-note" id="pb_fee_note"></div>
                        </div>
                        <div class="col-md-3">
                            <label>Date</label>
                            <input type="date" name="booking_date" class="form-control" min="{{ date('Y-m-d') }}" max="{{ date('Y-m-d', strtotime('+10 days')) }}" value="{{ old('booking_date') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label>Time</label>
                            <select name="booking_time" class="form-select" required>
                                <option value="" disabled selected>Select</option>
                                <option value="06:00:00" {{ old('booking_time') == '06:00:00' ? 'selected' : '' }}>06:00 AM</option>
                                <option value="07:00:00" {{ old('booking_time') == '07:00:00' ? 'selected' : '' }}>07:00 AM</option>
                                <option value="08:00:00" {{ old('booking_time') == '08:00:00' ? 'selected' : '' }}>08:00 AM</option>
                                <option value="09:00:00" {{ old('booking_time') == '09:00:00' ? 'selected' : '' }}>09:00 AM</option>
                                <option value="10:00:00" {{ old('booking_time') == '10:00:00' ? 'selected' : '' }}>10:00 AM</option>
                                <option value="11:00:00" {{ old('booking_time') == '11:00:00' ? 'selected' : '' }}>11:00 AM</option>
                                <option value="12:00:00" {{ old('booking_time') == '12:00:00' ? 'selected' : '' }}>12:00 PM</option>
                                <option value="14:00:00" {{ old('booking_time') == '14:00:00' ? 'selected' : '' }}>02:00 PM</option>
                                <option value="15:00:00" {{ old('booking_time') == '15:00:00' ? 'selected' : '' }}>03:00 PM</option>
                                <option value="16:00:00" {{ old('booking_time') == '16:00:00' ? 'selected' : '' }}>04:00 PM</option>
                                <option value="17:00:00" {{ old('booking_time') == '17:00:00' ? 'selected' : '' }}>05:00 PM</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color:var(--line);">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Your Name</label>
                            <input type="text" name="devotee_name" class="form-control" value="{{ old('devotee_name') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label>Mobile Number</label>
                            <input type="text" name="devotee_mobile" class="form-control" pattern="[0-9]{10}" placeholder="10 digits" value="{{ old('devotee_mobile') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label>Email</label>
                            <input type="email" name="devotee_email" class="form-control" value="{{ old('devotee_email') }}" required>
                        </div>
                    </div>
                    <div class="pooja-fee-note mb-0">We'll use this to confirm your booking and set up your account login — mandatory for every booking.</div>

                    <hr class="my-4" style="border-color:var(--line);">

                    <label class="mb-2">How will you pay?</label>
                    <div class="pay-method-options mb-3">
                        <label class="pay-method-option">
                            <input type="radio" name="payment_method" value="Bank" checked style="display:none;">
                            <i class="bi bi-bank"></i>
                            <span>
                                <span class="pm-title d-block">Bank Transfer</span>
                                <span class="pm-sub">Details emailed once confirmed</span>
                            </span>
                        </label>
                        <label class="pay-method-option">
                            <input type="radio" name="payment_method" value="Cash" style="display:none;">
                            <i class="bi bi-shop"></i>
                            <span>
                                <span class="pm-title d-block">Cash / Card at Temple</span>
                                <span class="pm-sub">Pay at the counter on your visit</span>
                            </span>
                        </label>
                    </div>

                    @include('partials.recaptcha-widget')

                    <button type="submit" class="btn-book-submit">
                        <i class="bi bi-calendar-check me-1"></i> Book Now
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.getElementById('pb_pooja_id')?.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            const fee = opt?.dataset?.fee;
            document.getElementById('pb_fee_note').textContent = fee ? 'Pooja fee: {{ $temple["currency"] }} ' + Number(fee).toLocaleString(undefined, {minimumFractionDigits: 2}) : '';
        });
    </script>
</body>
</html>
