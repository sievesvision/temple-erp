<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donate · {{ $event->event_name }} | {{ $temple['name'] }}</title>
    <link rel="icon" type="image/gif" href="{{ $temple['logo'] }}">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <style>
        :root { --primary: {{ $temple['primary_color'] }}; --accent: {{ $temple['accent_color'] }}; --dark: {{ $temple['dark_color'] }}; --cream:#fbf8f1; --ink:#25231f; --muted:#716c64; --line:#e9e1d5; --serif:'Playfair Display',Georgia,serif; --sans:'DM Sans',sans-serif; }
        * { box-sizing:border-box; } html { scroll-behavior:smooth; } body { margin:0; color:var(--ink); background:var(--cream); font-family:var(--sans); } h1,h2,h3 { font-family:var(--serif); }
        #donate-now { scroll-margin-top:20px; }

        .event-header-banner { line-height:0; margin-bottom:2rem; }
        .event-header-banner img { width:100%; height:auto; display:block; border-radius:10px; box-shadow:0 16px 35px rgba(37,35,31,.1); }
        .event-hero { background:var(--dark); color:#fff; padding:2.5rem 0 3rem; position:relative; overflow:hidden; }
        .event-hero .kicker { color:var(--accent); font-weight:700; font-size:.8rem; letter-spacing:.15em; text-transform:uppercase; }
        .event-hero h1 { font-size:clamp(1.9rem,4vw,3rem); margin:.6rem 0 1rem; }
        .event-hero .hero-tagline { font-size:1.1rem; color:#e8e4d8; max-width:640px; margin-bottom:1.25rem; }
        .event-meta { display:flex; flex-wrap:wrap; gap:1.5rem; color:#e8e4d8; }
        .event-meta span i { color:var(--accent); margin-right:.4rem; }
        .raised-pill { background:rgba(255,255,255,.12); border-radius:999px; padding:.6rem 1.2rem; display:inline-flex; gap:.5rem; align-items:center; margin-top:1.25rem; margin-right:.75rem; }
        .btn-donate-hero { display:inline-flex; align-items:center; gap:.5rem; background:linear-gradient(135deg, var(--accent), var(--primary)); color:#fff; font-weight:800; font-size:1.05rem; padding:.85rem 1.75rem; border-radius:999px; text-decoration:none; margin-top:1.25rem; box-shadow:0 14px 30px rgba(0,0,0,.25); transition:transform .15s; }
        .btn-donate-hero:hover { color:#fff; transform:translateY(-2px); }

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

        .event-story { margin-bottom:2.5rem; }
        .event-story .story-lead { font-family:var(--serif); font-size:1.4rem; line-height:1.5; color:var(--dark); margin-bottom:1.5rem; }
        .event-story .story-body p { color:var(--ink); line-height:1.85; margin-bottom:1.15rem; font-size:1.02rem; }
        .story-callout { background:linear-gradient(135deg, #f4e5d1, #f8efe0); border-radius:10px; padding:1.5rem 1.75rem; margin-top:1.5rem; border-left:4px solid var(--primary); }
        .story-callout p { font-family:var(--serif); font-style:italic; font-size:1.15rem; color:var(--dark); margin:0 0 1rem; line-height:1.6; }

        .section-heading { display:flex; align-items:center; gap:.75rem; margin-bottom:1.25rem; }
        .section-heading .icon-badge { width:42px; height:42px; border-radius:12px; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
        .section-heading h2 { font-size:1.4rem; color:var(--dark); margin:0; }
        .section-subnote { color:var(--muted); font-size:.85rem; margin:-.75rem 0 1.25rem; }

        .gallery-section { margin-bottom:2.5rem; }
        .gallery-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:1rem; }
        @media (max-width:576px) { .gallery-grid { grid-template-columns:1fr; } }
        .gallery-item { position:relative; border-radius:10px; overflow:hidden; box-shadow:0 12px 28px rgba(37,35,31,.1); cursor:zoom-in; background:#fff; }
        .gallery-item img { width:100%; height:220px; object-fit:cover; display:block; transition:transform .3s; }
        .gallery-item:hover img { transform:scale(1.04); }
        .gallery-item .gallery-caption { position:absolute; bottom:0; left:0; right:0; background:linear-gradient(0deg, rgba(0,0,0,.65), transparent); color:#fff; font-size:.78rem; font-weight:600; padding:1.5rem .8rem .5rem; }
        .gallery-lightbox { position:fixed; inset:0; background:rgba(15,13,10,.92); display:none; align-items:center; justify-content:center; z-index:1000; padding:2rem; }
        .gallery-lightbox.show { display:flex; }
        .gallery-lightbox img { max-width:100%; max-height:88vh; border-radius:8px; box-shadow:0 20px 50px rgba(0,0,0,.4); }
        .gallery-lightbox .lightbox-close { position:absolute; top:1.5rem; right:1.5rem; color:#fff; font-size:2rem; background:none; border:none; cursor:pointer; line-height:1; }

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

        .donate-section-anchor { background:#fff; border-radius:14px; padding:2.5rem; box-shadow:0 20px 50px rgba(37,35,31,.1); border:1px solid var(--line); }
        .donate-section-anchor .section-heading h2 { font-size:1.75rem; }
    </style>
</head>
<body>
    <div class="event-hero">
        <div class="container">
            <a class="back-link" style="color:#c4c6bb;" href="{{ route('home') }}#event-donations"><i class="bi bi-arrow-left me-1"></i>Back to {{ $temple['name'] }}</a>
            <div class="kicker mt-3">Event Donation</div>
            <h1>{{ $event->event_name }}</h1>
            <div class="event-meta">
                @if($event->date_tbc)
                    <span><i class="bi bi-calendar-event"></i>Date to be confirmed</span>
                @else
                    <span><i class="bi bi-calendar-event"></i>{{ date('d M Y', strtotime($event->event_date)) }}</span>
                    @if($event->start_time)<span><i class="bi bi-clock"></i>{{ date('g:i A', strtotime($event->start_time)) }}</span>@endif
                @endif
                @if($event->location)<span><i class="bi bi-geo-alt"></i>{{ $event->location }}</span>@endif
            </div>
            <div>
                @if($event->show_donation_summary)
                    <div class="raised-pill"><i class="bi bi-heart-fill" style="color:var(--accent)"></i> {{ $temple['currency'] }} {{ number_format($raised, 2) }} raised so far</div>
                @endif
            </div>
            <a href="#donate-now" class="btn-donate-hero"><i class="bi bi-hand-thumbs-up-fill"></i> Donate Now</a>
        </div>
    </div>

    <div class="section-pad" style="padding-top:4rem;">
        <div class="container" style="max-width:800px;">
            @if($event->header_image)
                <div class="event-header-banner">
                    <img src="{{ asset($event->header_image) }}" alt="{{ $event->event_name }}">
                </div>
            @endif

            @php
                $storyParagraphs = $event->description ? array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', trim($event->description))))) : [];
            @endphp

            @if(count($storyParagraphs) > 1)
                <div class="event-story">
                    <p class="story-lead">{{ $storyParagraphs[0] }}</p>
                    <div class="story-body">
                        @foreach(array_slice($storyParagraphs, 1, -1) as $para)
                            <p>{{ $para }}</p>
                        @endforeach
                    </div>
                    <div class="story-callout">
                        <p>&ldquo;{{ end($storyParagraphs) }}&rdquo;</p>
                        <a href="#donate-now" class="btn-donate-hero" style="box-shadow:none; padding:.7rem 1.5rem; font-size:.95rem;"><i class="bi bi-hand-thumbs-up-fill"></i> Donate Now</a>
                    </div>
                </div>
            @elseif($event->description)
                <div class="festival-brief">
                    <div class="section-kicker" style="color:var(--primary);font-weight:700;font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;">About this event</div>
                    <p class="mb-0 mt-1" style="color:var(--ink);">{{ $event->description }}</p>
                </div>
            @endif

            @if($event->galleryImages())
                <div class="gallery-section">
                    <div class="section-heading">
                        <div class="icon-badge"><i class="bi bi-images"></i></div>
                        <h2>Tentative Renovation Plans</h2>
                    </div>
                    <div class="section-subnote">Visual drafts of the proposed renovation — designs are indicative only and subject to change.</div>
                    <div class="gallery-grid">
                        @foreach($event->galleryImages() as $i => $imagePath)
                        <div class="gallery-item" onclick="openGalleryLightbox('{{ asset($imagePath) }}')">
                            <img src="{{ asset($imagePath) }}" alt="Tentative renovation plan {{ $i + 1 }}" loading="lazy">
                            <div class="gallery-caption">Visual Draft {{ $i + 1 }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($event->qr_code_image || $event->contactList() || $temple['address'])
                <div class="row g-3 mb-4">
                    @if($event->qr_code_image)
                    <div class="col-md-4">
                        <div class="donation-bank-card text-center h-100 d-flex flex-column justify-content-center">
                            <img src="{{ asset($event->qr_code_image) }}" alt="Scan to donate" class="img-fluid mb-2" style="max-width:160px; margin:0 auto;">
                            <span class="bank-label mb-0">Scan to donate online</span>
                        </div>
                    </div>
                    @endif
                    @if($event->contactList() || $temple['address'])
                    <div class="col-md-{{ $event->qr_code_image ? 8 : 12 }}">
                        <div class="donation-bank-card h-100">
                            @if($temple['address'])
                                <span class="bank-label">Location</span>
                                <span class="bank-value mb-2" style="display:block;">{{ $temple['address'] }}</span>
                            @endif
                            @if($event->contactList())
                                <span class="bank-label">Contact</span>
                                <div class="row g-2 mt-0">
                                    @foreach($event->contactList() as $contact)
                                    <div class="col-sm-6">
                                        <span class="bank-value" style="font-size:.95rem;">{{ $contact['name'] }}@if($contact['phone']) — {{ $contact['phone'] }}@endif</span>
                                    </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            @endif

            <div id="donate-now" class="donate-section-anchor">
                <div class="section-heading">
                    <div class="icon-badge"><i class="bi bi-hand-thumbs-up-fill"></i></div>
                    <h2>Make Your Donation</h2>
                </div>

                @if(session('success_donation'))<div class="alert alert-success">{{ session('success_donation') }}</div>@endif
                @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

                @include('frontend.partials.donate-form', ['temple' => $temple, 'lockedEvent' => $event, 'donationOptions' => $donationOptions, 'formAction' => route('donate.without.login'), 'formId' => 'event-donate-form', 'stripeEnabled' => $stripeEnabled, 'requireContactDetails' => $requireContactDetails])
            </div>

            @if($event->flyer_image)
                <div class="event-flyer mt-5 text-center">
                    <img src="{{ asset($event->flyer_image) }}" class="img-fluid" alt="{{ $event->event_name }} flyer">
                </div>
            @endif
        </div>
    </div>

    <div class="gallery-lightbox" id="galleryLightbox" onclick="closeGalleryLightbox()">
        <button type="button" class="lightbox-close" onclick="closeGalleryLightbox(event)">&times;</button>
        <img id="galleryLightboxImg" src="" alt="Tentative renovation plan — enlarged view">
    </div>
    <script>
        function openGalleryLightbox(src) {
            document.getElementById('galleryLightboxImg').src = src;
            document.getElementById('galleryLightbox').classList.add('show');
        }
        function closeGalleryLightbox(e) {
            if (e) { e.stopPropagation(); }
            document.getElementById('galleryLightbox').classList.remove('show');
        }
    </script>
</body>
</html>
