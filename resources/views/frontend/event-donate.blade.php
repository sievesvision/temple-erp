<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donate · {{ $event->event_name }} | {{ $temple['name'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --primary: {{ $temple['primary_color'] }}; --accent: {{ $temple['accent_color'] }}; --dark: {{ $temple['dark_color'] }}; --cream:#fbf8f1; --ink:#25231f; --muted:#716c64; --line:#e9e1d5; --serif:'Playfair Display',Georgia,serif; --sans:'DM Sans',sans-serif; }
        * { box-sizing:border-box; } body { margin:0; color:var(--ink); background:var(--cream); font-family:var(--sans); } h1,h2,h3 { font-family:var(--serif); }
        .event-header-banner { line-height:0; margin-bottom:2rem; }
        .event-header-banner img { width:100%; height:auto; display:block; border-radius:10px; box-shadow:0 16px 35px rgba(37,35,31,.1); }
        .event-hero { background:var(--dark); color:#fff; padding:2rem 0; position:relative; overflow:hidden; }
        .event-hero .kicker { color:var(--accent); font-weight:700; font-size:.8rem; letter-spacing:.15em; text-transform:uppercase; }
        .event-hero h1 { font-size:clamp(1.9rem,4vw,3rem); margin:.6rem 0 1rem; }
        .event-meta { display:flex; flex-wrap:wrap; gap:1.5rem; color:#e8e4d8; }
        .event-meta span i { color:var(--accent); margin-right:.4rem; }
        .raised-pill { background:rgba(255,255,255,.12); border-radius:999px; padding:.6rem 1.2rem; display:inline-flex; gap:.5rem; align-items:center; margin-top:1rem; }
        .section-pad { padding:4rem 0; }
        .donate-tabs-card { background:#fff; border-radius:10px; padding:2rem; box-shadow:0 16px 35px rgba(37,35,31,.08); }
        .donate-method-tabs .nav-link { border-radius:999px; color:var(--ink); font-weight:600; font-size:.85rem; padding:.55rem 1.1rem; border:1px solid var(--line); margin-right:.5rem; }
        .donate-method-tabs .nav-link.active { background:var(--primary); color:#fff; border-color:var(--primary); }
        .donation-bank-card { background:#f4e5d1; border-top:4px solid var(--primary); border-radius:8px; padding:1.5rem; }
        .bank-label { display:block; color:var(--muted); font-size:.72rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; margin-bottom:.3rem; }
        .bank-value { display:block; color:var(--dark); font-size:1.05rem; }
        .locked-event-badge { background:#f4e5d1; border-radius:6px; padding:.75rem 1rem; font-weight:700; color:var(--dark); }
        .donation-form label { font-size:.78rem; font-weight:700; }
        .donation-form .form-control,.donation-form .form-select { border-color:var(--line); border-radius:4px; padding:.7rem; }
        .donation-form .btn { background:var(--primary); border-color:var(--primary); color:#fff; font-weight:700; }
        .back-link { color:var(--muted); font-size:.85rem; }
        .festival-brief { background:#fff; border:1px solid var(--line); border-left:4px solid var(--accent); border-radius:8px; padding:1.25rem 1.5rem; margin-bottom:2.5rem; box-shadow:0 12px 30px rgba(37,35,31,.06); }
        .donation-intro { margin-bottom:1.75rem; }
        .donation-intro h2 { font-size:1.5rem; color:var(--dark); margin:0 0 .5rem; }
        .donation-intro p { color:var(--muted); line-height:1.75; }
        .donation-tier-options { display:flex; flex-direction:column; gap:.6rem; }
        .donation-tier-option { border:1px solid var(--line); border-radius:8px; padding:.85rem 1rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.6rem; }
        .donation-tier-option:has(input:checked), .donation-tier-option.selected { border-color:var(--primary); background:#faf3ea; }
        .tier-option-label { display:flex; align-items:center; gap:.7rem; cursor:pointer; margin:0; flex:1; min-width:200px; }
        .tier-option-text { display:flex; flex-direction:column; }
        .tier-amount { color:var(--muted); font-size:.8rem; }
        .tier-qty-wrap { display:flex; align-items:center; }
        .tier-qty-input, .tier-free-amount-input { width:100px; }
        .event-flyer img { border-radius:10px; box-shadow:0 16px 35px rgba(37,35,31,.1); }
    </style>
</head>
<body>
    <div class="event-hero">
        <div class="container">
            <a class="back-link" style="color:#c4c6bb;" href="{{ route('home') }}#event-donations"><i class="bi bi-arrow-left me-1"></i>Back to {{ $temple['name'] }}</a>
            <div class="kicker mt-3">Event Donation</div>
            <h1>{{ $event->event_name }}</h1>
            <div class="event-meta">
                <span><i class="bi bi-calendar-event"></i>{{ date('d M Y', strtotime($event->event_date)) }}</span>
                @if($event->location)<span><i class="bi bi-geo-alt"></i>{{ $event->location }}</span>@endif
                @if($event->start_time)<span><i class="bi bi-clock"></i>{{ date('g:i A', strtotime($event->start_time)) }}</span>@endif
            </div>
            @if($event->show_donation_summary)
                <div class="raised-pill"><i class="bi bi-heart-fill" style="color:var(--accent)"></i> {{ $temple['currency'] }} {{ number_format($raised, 2) }} raised so far</div>
            @endif
        </div>
    </div>

    <div class="section-pad" style="padding-top:4rem;">
        <div class="container" style="max-width:760px;">
            @if($event->header_image)
                <div class="event-header-banner">
                    <img src="{{ asset($event->header_image) }}" alt="{{ $event->event_name }}">
                </div>
            @endif

            @if($event->description)
                <div class="festival-brief">
                    <div class="section-kicker" style="color:var(--primary);font-weight:700;font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;">About this event</div>
                    <p class="mb-0 mt-1" style="color:var(--ink);">{{ $event->description }}</p>
                </div>
            @endif

            <div class="donation-intro">
                <h2>Sponsorship &amp; Donations</h2>
                <p class="mb-0">Sponsorships and donations for this event are warmly welcome. Use the form below to submit your details, and make your payment by cash at the temple, bank transfer, or online payment.</p>
            </div>

            @if(session('success_donation'))<div class="alert alert-success">{{ session('success_donation') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            @include('frontend.partials.donate-form', ['temple' => $temple, 'lockedEvent' => $event, 'donationOptions' => $donationOptions, 'formAction' => route('donate.without.login'), 'formId' => 'event-donate-form', 'stripeEnabled' => $stripeEnabled])

            @if($event->flyer_image)
                <div class="event-flyer mt-5 text-center">
                    <img src="{{ asset($event->flyer_image) }}" class="img-fluid" alt="{{ $event->event_name }} flyer">
                </div>
            @endif
        </div>
    </div>
</body>
</html>
