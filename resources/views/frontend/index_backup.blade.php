<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>🛕 Shree Mandir · Divine Temple Portal</title>

  <!-- Bootstrap 5.3 + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  
  <!-- Google Fonts: Cinzel (Spiritual/Classical) & Outfit (Modern/Clean) -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />

  <!-- GSAP for best-in-class animations -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>

    <style>
    :root {
      --primary-saffron: #ff6f00;
      --saffron-dark: #e65100;
      --saffron-glow: rgba(255, 111, 0, 0.08);
      --primary-gold: #b8863a;
      --gold-glow: rgba(184, 134, 58, 0.12);
      --gold-light: #f3c675;
      --gold-dark: #997322;
      --dark-bg: #1e150d;
      --dark-card: rgba(30, 21, 13, 0.75);
      --light-bg: #fdfbf7;
      --text-dark: #2b221a;
      --text-light: #fdfaf6;
      --border-gold: rgba(184, 134, 58, 0.18);
      --border-gold-hover: rgba(184, 134, 58, 0.45);
      
      --gold-gradient: linear-gradient(135deg, #c9933b 0%, #b8863a 50%, #9c6c28 100%);
      --saffron-gradient: linear-gradient(135deg, #ff9e00 0%, #ff6f00 50%, #e65100 100%);
      --dark-gradient: linear-gradient(135deg, #2b1f13 0%, #17110a 100%);
      --glass-gradient: linear-gradient(135deg, rgba(255, 255, 255, 0.85) 0%, rgba(255, 248, 240, 0.65) 100%);
      --light-glass-gradient: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(253, 250, 245, 0.8) 100%);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Outfit', sans-serif;
    }

    body {
      background: var(--light-bg);
      color: var(--text-dark);
      overflow-x: hidden;
    }

    h1, h2, h3, h4, h5, h6, .font-divine {
      font-family: 'Cinzel', serif;
      font-weight: 700;
    }

    /* ----- ambient background design ----- */
    .bg-pattern {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: radial-gradient(rgba(184, 134, 58, 0.04) 1px, transparent 0);
      background-size: 32px 32px;
      pointer-events: none;
      z-index: 0;
    }

    .ambient-glow {
      position: absolute;
      width: 600px;
      height: 600px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(255, 111, 0, 0.04) 0%, rgba(184, 134, 58, 0.02) 50%, rgba(255,255,255,0) 100%);
      pointer-events: none;
      z-index: 0;
      filter: blur(40px);
    }

    /* ----- rotating mandala ----- */
    .mandala-container {
      position: absolute;
      top: -15%;
      right: -10%;
      width: 650px;
      height: 650px;
      opacity: 0.12;
      pointer-events: none;
      z-index: 0;
      animation: spinMandala 140s linear infinite;
      transform-origin: center center;
    }

    .mandala-container-left {
      position: absolute;
      bottom: -10%;
      left: -15%;
      width: 550px;
      height: 550px;
      opacity: 0.1;
      pointer-events: none;
      z-index: 0;
      animation: spinMandalaCounter 120s linear infinite;
      transform-origin: center center;
    }

    @keyframes spinMandala {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    @keyframes spinMandalaCounter {
      from { transform: rotate(360deg); }
      to { transform: rotate(0deg); }
    }

    /* ----- scrollbar styling ----- */
    ::-webkit-scrollbar {
      width: 8px;
    }
    ::-webkit-scrollbar-track {
      background: var(--light-bg);
    }
    ::-webkit-scrollbar-thumb {
      background: var(--primary-gold);
      border-radius: 10px;
      border: 2px solid var(--light-bg);
    }
    ::-webkit-scrollbar-thumb:hover {
      background: var(--primary-saffron);
    }

    /* ----- navigation bar ----- */
    .navbar-custom {
      background: rgba(253, 251, 247, 0.85);
      backdrop-filter: blur(20px) saturate(180%);
      -webkit-backdrop-filter: blur(20px) saturate(180%);
      border: 1px solid rgba(184, 134, 58, 0.18);
      border-radius: 40px;
      margin-top: 15px;
      padding: 0.6rem 1.5rem;
      transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
      box-shadow: 0 10px 30px rgba(184, 134, 58, 0.06);
      z-index: 1000;
    }

    @media (min-width: 992px) {
      .navbar-custom {
        width: calc(100% - 60px);
        left: 30px !important;
        right: 30px !important;
      }
    }

    .navbar-custom.scrolled {
      margin-top: 0;
      border-radius: 0;
      border-left: 0;
      border-right: 0;
      border-top: 0;
      width: 100% !important;
      left: 0 !important;
      right: 0 !important;
      background: rgba(253, 251, 247, 0.96);
      border-bottom: 1px solid rgba(184, 134, 58, 0.25);
      padding: 0.5rem 2rem;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    }

    .navbar-custom .navbar-brand {
      font-weight: 900;
      font-size: 1.5rem;
      letter-spacing: 1px;
      color: #2b1f13;
      text-shadow: 0 0 10px rgba(184, 134, 58, 0.1);
    }

    .navbar-custom .navbar-brand i {
      color: var(--primary-saffron);
      filter: drop-shadow(0 0 5px rgba(255, 111, 0, 0.3));
    }

    .navbar-custom .nav-link {
      color: #4a3e35;
      font-weight: 500;
      padding: 0.5rem 1.2rem;
      border-radius: 30px;
      transition: all 0.3s ease;
      font-size: 0.92rem;
      position: relative;
    }

    .navbar-custom .nav-link::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      width: 0;
      height: 2px;
      background: var(--gold-gradient);
      transition: all 0.3s ease;
      transform: translateX(-50%);
    }

    .navbar-custom .nav-link:hover::after,
    .navbar-custom .nav-link.active::after {
      width: 60%;
    }

    .navbar-custom .nav-link:hover, 
    .navbar-custom .nav-link.active {
      color: var(--primary-saffron);
      background: rgba(255, 111, 0, 0.05);
    }

    @keyframes pulseEhundi {
      0% {
        box-shadow: 0 0 0 0 rgba(212, 175, 55, 0.5);
        transform: scale(1);
      }
      50% {
        box-shadow: 0 0 0 8px rgba(212, 175, 55, 0);
        transform: scale(1.02);
      }
      100% {
        box-shadow: 0 0 0 0 rgba(212, 175, 55, 0);
        transform: scale(1);
      }
    }
    
    .navbar-custom .nav-link-ehundi {
      border: 1.5px solid #d4af37 !important;
      background: rgba(212, 175, 55, 0.12) !important;
      color: #b8863a !important;
      font-weight: 700 !important;
      animation: pulseEhundi 2.5s infinite;
      padding: 0.5rem 1.3rem !important;
    }
    
    .navbar-custom .nav-link-ehundi:hover {
      background: var(--gold-gradient) !important;
      color: #fff !important;
      box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3) !important;
      animation: none;
      transform: translateY(-1px);
    }

    /* ----- buttons ----- */
    .btn-gold {
      background: var(--gold-gradient);
      border: 1px solid var(--gold-dark);
      color: #fff !important;
      font-weight: 700;
      padding: 0.7rem 1.8rem;
      border-radius: 30px;
      transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
      box-shadow: 0 4px 15px rgba(184, 134, 58, 0.25);
    }

    .btn-gold:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(184, 134, 58, 0.45);
      filter: brightness(1.05);
    }

    .btn-saffron {
      background: var(--saffron-gradient);
      border: 1px solid var(--saffron-dark);
      color: #fff !important;
      font-weight: 700;
      padding: 0.7rem 1.8rem;
      border-radius: 30px;
      transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
      box-shadow: 0 4px 15px rgba(255, 111, 0, 0.25);
    }

    .btn-saffron:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(255, 111, 0, 0.4);
      filter: brightness(1.08);
    }

    .btn-outline-gold {
      border: 2px solid var(--primary-gold);
      color: var(--primary-gold) !important;
      background: transparent;
      font-weight: 600;
      padding: 0.6rem 1.8rem;
      border-radius: 30px;
      transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .btn-outline-gold:hover {
      background: var(--gold-gradient);
      color: #fff !important;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(184, 134, 58, 0.3);
    }

    .btn-outline-saffron {
      border: 2px solid var(--primary-saffron);
      color: var(--primary-saffron) !important;
      background: transparent;
      font-weight: 600;
      padding: 0.6rem 1.8rem;
      border-radius: 30px;
      transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .btn-outline-saffron:hover {
      background: var(--saffron-gradient);
      color: #fff !important;
      border-color: transparent;
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(255, 111, 0, 0.3);
    }

    /* ----- hero section ----- */
    .hero-section {
      padding: 10.5rem 0 7rem 0;
      position: relative;
      background: radial-gradient(circle at 80% 20%, rgba(255, 243, 224, 0.7) 0%, rgba(253, 251, 247, 1) 100%);
      color: var(--text-dark);
      overflow: hidden;
      border-bottom: 1.5px solid var(--border-gold);
    }

    .hero-title {
      font-size: 4rem;
      line-height: 1.15;
      color: #2b1f13;
      margin-bottom: 1.5rem;
    }

    .hero-title span.accent {
      background: linear-gradient(to right, var(--primary-saffron), var(--primary-gold));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      position: relative;
    }

    .hero-title span.accent::after {
      content: '';
      position: absolute;
      left: 0;
      bottom: 5px;
      width: 100%;
      height: 4px;
      background: rgba(255, 111, 0, 0.12);
      border-radius: 2px;
    }

    .hero-desc {
      font-size: 1.2rem;
      color: #615246;
      line-height: 1.7;
      margin-bottom: 2.2rem;
    }

    .hero-image-wrapper {
      position: relative;
      z-index: 1;
    }

    .hero-image-container {
      position: relative;
      border-radius: 50% 50% 30px 30px;
      overflow: hidden;
      border: 4px solid var(--primary-gold);
      padding: 6px;
      background: #fff;
      box-shadow: 0 30px 60px rgba(184, 134, 58, 0.22);
      outline: 1.5px solid rgba(184, 134, 58, 0.25);
      outline-offset: 6px;
      animation: floatHeroImage 6s ease-in-out infinite alternate;
    }

    @keyframes floatHeroImage {
      0% { transform: translateY(0px) rotate(0deg); }
      100% { transform: translateY(-15px) rotate(0.5deg); }
    }

    .hero-decor-badge {
      position: absolute;
      background: #fff;
      border-radius: 20px;
      padding: 0.8rem 1.5rem;
      box-shadow: 0 15px 35px rgba(184, 134, 58, 0.08);
      display: flex;
      align-items: center;
      gap: 12px;
      border: 1px solid rgba(184, 134, 58, 0.15);
      z-index: 2;
    }

    .badge-top-left {
      top: 15%;
      left: -8%;
      animation: floatBadge 4s ease-in-out infinite alternate;
    }

    .badge-bottom-right {
      bottom: 12%;
      right: -4%;
      animation: floatBadge 5s ease-in-out infinite alternate 1s;
    }

    @keyframes floatBadge {
      0% { transform: translateY(0px); }
      100% { transform: translateY(-10px); }
    }

    .badge-icon {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: rgba(255, 111, 0, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary-saffron);
      font-size: 1.4rem;
    }

    /* ----- global section title ----- */
    .section-header {
      margin-bottom: 4.5rem;
      position: relative;
    }

    .section-subtitle {
      color: var(--primary-saffron);
      font-size: 0.95rem;
      font-weight: 700;
      letter-spacing: 4px;
      text-transform: uppercase;
      margin-bottom: 0.8rem;
      display: inline-block;
    }

    .section-title {
      font-size: 3rem;
      color: #2b1f13;
      position: relative;
      display: inline-block;
    }

    .section-title::after {
      content: '🪔';
      position: absolute;
      bottom: -36px;
      left: 50%;
      transform: translateX(-50%);
      font-size: 1.6rem;
      filter: drop-shadow(0 2px 6px rgba(184, 134, 58, 0.2));
    }

    .section-title::before {
      content: '';
      position: absolute;
      bottom: -22px;
      left: 10%;
      width: 80%;
      height: 2px;
      background: radial-gradient(circle, var(--primary-gold) 0%, rgba(184, 134, 58, 0) 100%);
    }

    /* ----- pooja grid cards ----- */
    .pooja-card {
      background: var(--light-glass-gradient);
      border: 1px solid var(--border-gold);
      border-radius: 24px;
      padding: 2.2rem;
      transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
      position: relative;
      overflow: hidden;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 10px 30px rgba(184, 134, 58, 0.02);
    }

    .pooja-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 45px rgba(184, 134, 58, 0.15);
      border-color: var(--primary-gold);
      background: #fff;
    }

    .pooja-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 6px;
      background: var(--gold-gradient);
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .pooja-card:hover::before {
      opacity: 1;
    }

    .pooja-category {
      display: inline-block;
      padding: 0.4rem 1.1rem;
      background: rgba(184, 134, 58, 0.08);
      border: 1px solid rgba(184, 134, 58, 0.15);
      color: var(--primary-gold);
      font-weight: 700;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      border-radius: 50px;
      margin-bottom: 1.3rem;
    }

    .pooja-title {
      font-size: 1.65rem;
      color: #2b1f13;
      margin-bottom: 0.8rem;
    }

    .pooja-desc {
      color: #615246;
      font-size: 0.98rem;
      line-height: 1.65;
      margin-bottom: 1.5rem;
      flex-grow: 1;
    }

    .pooja-meta {
      border-top: 1px solid rgba(184, 134, 58, 0.1);
      padding-top: 1.2rem;
      margin-bottom: 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .pooja-fee {
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--primary-saffron);
    }

    .pooja-duration {
      font-size: 0.92rem;
      color: #8c7d70;
      font-weight: 600;
    }

    .pooja-duration i {
      color: var(--primary-gold);
      margin-right: 4px;
    }

    /* ----- events vertical timeline ----- */
    .timeline-container {
      position: relative;
      max-width: 1000px;
      margin: 0 auto;
      padding: 2rem 0;
    }

    .timeline-line {
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 4px;
      height: 100%;
      background: rgba(184, 134, 58, 0.15);
      border-radius: 2px;
      z-index: 1;
    }

    .timeline-progress {
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 4px;
      height: 0%;
      background: var(--gold-gradient);
      box-shadow: 0 0 8px var(--primary-gold);
      border-radius: 2px;
      z-index: 2;
      transition: height 0.1s linear;
    }

    .timeline-item {
      display: flex;
      justify-content: flex-end;
      padding-bottom: 4rem;
      position: relative;
      z-index: 3;
      width: 50%;
    }

    .timeline-item:nth-child(even) {
      align-self: flex-end;
      margin-left: 50%;
      justify-content: flex-start;
    }

    .timeline-item:nth-child(odd) {
      margin-right: 50%;
    }

    .timeline-badge {
      position: absolute;
      top: 10px;
      right: -25px;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: #fff;
      border: 3.5px solid var(--primary-gold);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary-saffron);
      z-index: 10;
      box-shadow: 0 4px 10px rgba(184, 134, 58, 0.15);
      transition: all 0.3s ease;
    }

    .timeline-item:nth-child(even) .timeline-badge {
      left: -25px;
    }

    .timeline-item:hover .timeline-badge {
      background: var(--primary-saffron);
      color: #fff;
      border-color: var(--primary-saffron);
      transform: scale(1.15);
      box-shadow: 0 0 15px rgba(255, 111, 0, 0.4);
    }

    .timeline-card {
      background: var(--glass-gradient);
      border: 1px solid var(--border-gold);
      border-radius: 24px;
      padding: 2.2rem;
      width: 90%;
      box-shadow: 0 10px 30px rgba(184, 134, 58, 0.03);
      transition: all 0.4s ease;
    }

    .timeline-item:nth-child(even) .timeline-card {
      margin-left: auto;
    }

    .timeline-card:hover {
      background: #fff;
      border-color: var(--primary-saffron);
      box-shadow: 0 15px 40px rgba(255, 111, 0, 0.08);
    }

    .event-date-badge {
      background: var(--saffron-gradient);
      color: #fff;
      font-weight: 700;
      padding: 0.45rem 1.2rem;
      border-radius: 50px;
      font-size: 0.85rem;
      display: inline-block;
      margin-bottom: 1.2rem;
    }

    .event-name {
      font-size: 1.60rem;
      color: #2b1f13;
      margin-bottom: 0.8rem;
    }

    .event-desc {
      color: #615246 !important;
      line-height: 1.65;
    }

    .event-meta {
      font-size: 0.92rem;
      color: #8c7d70;
      margin-top: 1.3rem;
      display: flex;
      flex-wrap: wrap;
      gap: 18px;
    }

    .event-meta span i {
      color: var(--primary-gold);
      margin-right: 6px;
    }

    @media (max-width: 768px) {
      .timeline-line, .timeline-progress {
        left: 20px;
      }
      .timeline-item {
        width: 100%;
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 55px;
        justify-content: flex-start !important;
      }
      .timeline-badge {
        left: -5px !important;
      }
      .timeline-card {
        width: 100%;
      }
    }

    /* ----- features section ----- */
    .feature-card {
      background: #fff;
      border: 1px solid var(--border-gold);
      border-radius: 28px;
      padding: 3rem 2.2rem;
      text-align: center;
      transition: all 0.4s ease;
      height: 100%;
      box-shadow: 0 10px 30px rgba(184, 134, 58, 0.02);
    }

    .feature-card:hover {
      transform: translateY(-6px);
      border-color: var(--primary-gold);
      box-shadow: 0 18px 45px rgba(184, 134, 58, 0.12);
    }

    .feature-icon-wrapper {
      width: 80px;
      height: 80px;
      border-radius: 24px;
      background: rgba(184, 134, 58, 0.06);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 2rem auto;
      color: var(--primary-gold);
      font-size: 2.3rem;
      transition: all 0.4s ease;
    }

    .feature-card:hover .feature-icon-wrapper {
      background: var(--saffron-gradient);
      color: #fff;
      transform: rotate(8deg);
      box-shadow: 0 8px 20px rgba(255, 111, 0, 0.25);
    }

    /* ----- donations section ----- */
    .donations-section {
      background: radial-gradient(circle at 10% 90%, rgba(255, 243, 224, 0.6) 0%, rgba(253, 251, 247, 1) 100%);
      position: relative;
    }

    .donation-counter-card {
      background: var(--dark-gradient);
      color: #fff;
      border-radius: 32px;
      padding: 3.5rem;
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(184, 134, 58, 0.3);
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
    }

    .donation-counter-card::after {
      content: '';
      position: absolute;
      top: -30%;
      right: -10%;
      width: 350px;
      height: 350px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(255, 111, 0, 0.15) 0%, rgba(184, 134, 58, 0.05) 60%, rgba(255,255,255,0) 100%);
      pointer-events: none;
    }

    .counter-value {
      font-size: 4.2rem;
      font-weight: 800;
      color: var(--gold-light);
      text-shadow: 0 4px 18px rgba(212, 175, 55, 0.35);
      letter-spacing: -1px;
    }

    .donation-tabs {
      background: rgba(184, 134, 58, 0.05);
      padding: 6px;
      border-radius: 50px;
      display: inline-flex;
      border: 1px solid var(--border-gold);
      margin-bottom: 2.5rem;
    }

    .donation-tab-btn {
      border: none;
      background: transparent;
      padding: 0.7rem 2.2rem;
      border-radius: 50px;
      font-weight: 700;
      color: #615246;
      transition: all 0.3s ease;
      font-size: 0.95rem;
    }

    .donation-tab-btn.active {
      background: var(--saffron-gradient);
      color: #fff;
      box-shadow: 0 5px 15px rgba(255, 111, 0, 0.25);
    }

    .donation-form-wrapper {
      background: #fff;
      border: 1px solid var(--border-gold);
      border-radius: 32px;
      padding: 3.5rem;
      box-shadow: 0 25px 50px rgba(184, 134, 58, 0.06);
    }

    .form-floating > .form-control:focus ~ label,
    .form-floating > .form-control:not(:placeholder-shown) ~ label {
      color: var(--primary-saffron);
      font-weight: 600;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-saffron);
      box-shadow: 0 0 0 0.25rem rgba(255, 111, 0, 0.15);
    }

    .form-control, .form-select {
      border: 1.5px solid rgba(184, 134, 58, 0.25);
      border-radius: 12px;
      height: calc(3.5rem + 4px);
    }

    /* Preset donation buttons */
    .preset-amount-container {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 10px;
      margin-bottom: 18px;
    }

    .preset-btn {
      flex: 1;
      min-width: 75px;
      background: rgba(184, 134, 58, 0.05);
      border: 1px solid rgba(184, 134, 58, 0.2);
      color: var(--primary-gold);
      font-weight: 700;
      padding: 0.5rem 1rem;
      border-radius: 10px;
      font-size: 0.9rem;
      transition: all 0.3s ease;
    }

    .preset-btn:hover {
      background: var(--gold-gradient);
      color: #fff;
      border-color: transparent;
      transform: translateY(-1px);
    }

    .bank-details-card {
      background: #fffcf8;
      border: 1.5px dashed var(--primary-gold);
      border-radius: 20px;
      padding: 1.8rem;
      margin-bottom: 1.8rem;
    }

    .bank-detail-item {
      display: flex;
      justify-content: space-between;
      border-bottom: 1px solid rgba(184, 134, 58, 0.1);
      padding: 0.7rem 0;
      font-size: 0.95rem;
    }

    .bank-detail-item:last-child {
      border-bottom: none;
    }

    .qr-code-container {
      background: #fffcf8;
      border: 1.5px dashed var(--primary-gold);
      border-radius: 20px;
      padding: 1.8rem;
      text-align: center;
      margin-bottom: 1.8rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .qr-instruction {
      font-weight: 600;
    }

    /* ----- footer ----- */
    .footer-custom {
      background: var(--dark-bg);
      color: #c9bdae;
      padding: 5.5rem 0 2rem 0;
      border-top: 3px solid var(--primary-gold);
      position: relative;
    }

    .footer-brand {
      font-size: 1.8rem;
      font-weight: 900;
      color: #fff;
      margin-bottom: 1.5rem;
    }

    .footer-brand span {
      color: var(--primary-saffron);
    }

    .footer-links {
      list-style: none;
      padding-left: 0;
    }

    .footer-links li {
      margin-bottom: 0.8rem;
    }

    .footer-links a {
      color: #a89d92;
      text-decoration: none;
      transition: all 0.3s ease;
    }

    .footer-links a:hover {
      color: var(--primary-saffron);
      padding-left: 6px;
    }

    .social-icons {
      display: flex;
      gap: 15px;
      margin-top: 1.5rem;
    }

    .social-icon-btn {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.05);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      text-decoration: none;
      border: 1px solid rgba(255,255,255,0.05);
    }

    .social-icon-btn:hover {
      background: var(--saffron-gradient);
      color: #fff;
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(255, 87, 34, 0.35);
    }
  </style>

</head>
<body>

  <div class="bg-pattern"></div>
  <div class="ambient-glow" style="top: -200px; right: -200px;"></div>
  <div class="ambient-glow" style="top: 1500px; left: -300px;"></div>

  <!-- ============================================ -->
  <!--  NAVBAR (Sticky Glassmorphism)               -->
  <!-- ============================================ -->
  <nav class="navbar navbar-expand-lg navbar-custom py-3 fixed-top" id="mainNavbar">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="{{ route('home') }}">
        <i class="bi bi-temple me-2"></i>
        <span>SHREE MANDIR</span>
      </a>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
              aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center gap-1">
          <li class="nav-item"><a class="nav-link active" href="{{ route('home') }}">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
          <li class="nav-item"><a class="nav-link" href="#poojas">Book Pooja</a></li>
          <li class="nav-item"><a class="nav-link" href="#donations">Donations</a></li>
          <li class="nav-item ms-lg-2">
            <a class="nav-link nav-link-ehundi" href="{{ route('ehundi.show') }}">
              🪔 e-Hundi
            </a>
          </li>

          <!-- Login or Dashboard Authentication -->
          @auth
            @php
              $role = auth()->user()->role;
              $dashboardRoute = 'login';
              if ($role === 'Admin') $dashboardRoute = 'admin.dashboard';
              elseif ($role === 'Devotee') $dashboardRoute = 'devotee.dashboard';
              elseif ($role === 'Priest') $dashboardRoute = 'priest.dashboard';
              elseif ($role === 'Trustee') $dashboardRoute = 'trustee.dashboard';
              elseif ($role === 'Staff') $dashboardRoute = 'staff.dashboard';
              elseif ($role === 'Accountant') $dashboardRoute = 'accountant.dashboard';
            @endphp
            <li class="nav-item ms-lg-3">
              <a class="btn btn-gold" href="{{ route($dashboardRoute) }}">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
              </a>
            </li>
            <li class="nav-item ms-lg-2">
              <a class="btn btn-outline-gold" href="{{ route('logout') }}">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
              </a>
            </li>
          @else
            <li class="nav-item ms-lg-3">
              <a class="btn btn-saffron" href="{{ route('login') }}">
                <i class="bi  bi-box-arrow-in-right me-1"></i> Login
              </a>
            </li>
            <li class="nav-item ms-lg-2">
              <a class="btn btn-outline-saffron" href="{{ route('register') }}">
                <i class="bi bi-person-plus me-1"></i> Register
              </a>
            </li>
          @endauth
        </ul>
      </div>
    </div>
  </nav>

  <!-- ============================================ -->
  <!--  HERO SECTION                                -->
  <!-- ============================================ -->
  <section class="hero-section" id="hero">
    <!-- Ambient glows -->
    <div class="ambient-glow" style="top: -10%; right: 5%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(255, 87, 34, 0.12) 0%, transparent 70%);"></div>
    <div class="ambient-glow" style="bottom: -10%; left: -10%; width: 600px; height: 600px; background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);"></div>

    <!-- Rotating Mandala SVG -->
    <div class="mandala-container">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="46" fill="none" stroke="var(--primary-gold)" stroke-width="0.3" opacity="0.4"/>
        <circle cx="50" cy="50" r="38" fill="none" stroke="var(--primary-gold)" stroke-width="0.2" stroke-dasharray="1,1" opacity="0.6"/>
        <circle cx="50" cy="50" r="30" fill="none" stroke="var(--primary-gold)" stroke-width="0.3" opacity="0.5"/>
        <circle cx="50" cy="50" r="22" fill="none" stroke="var(--primary-gold)" stroke-width="0.2" stroke-dasharray="1,1" opacity="0.6"/>
        <circle cx="50" cy="50" r="14" fill="none" stroke="var(--primary-gold)" stroke-width="0.4" opacity="0.7"/>
        <g stroke="var(--primary-gold)" stroke-width="0.25" fill="none" opacity="0.6">
          <line x1="50" y1="4" x2="50" y2="96"/>
          <line x1="4" y1="50" x2="96" y2="50"/>
          <line x1="17.5" y1="17.5" x2="82.5" y2="82.5"/>
          <line x1="17.5" y1="82.5" x2="82.5" y2="17.5"/>
          <!-- Rotated lines -->
          <line x1="50" y1="4" x2="50" y2="96" transform="rotate(15 50 50)"/>
          <line x1="4" y1="50" x2="96" y2="50" transform="rotate(15 50 50)"/>
          <line x1="17.5" y1="17.5" x2="82.5" y2="82.5" transform="rotate(15 50 50)"/>
          <line x1="17.5" y1="82.5" x2="82.5" y2="17.5" transform="rotate(15 50 50)"/>
          <line x1="50" y1="4" x2="50" y2="96" transform="rotate(30 50 50)"/>
          <line x1="4" y1="50" x2="96" y2="50" transform="rotate(30 50 50)"/>
          <line x1="17.5" y1="17.5" x2="82.5" y2="82.5" transform="rotate(30 50 50)"/>
          <line x1="17.5" y1="82.5" x2="82.5" y2="17.5" transform="rotate(30 50 50)"/>
          <line x1="50" y1="4" x2="50" y2="96" transform="rotate(45 50 50)"/>
          <line x1="4" y1="50" x2="96" y2="50" transform="rotate(45 50 50)"/>
          <line x1="17.5" y1="17.5" x2="82.5" y2="82.5" transform="rotate(45 50 50)"/>
          <line x1="17.5" y1="82.5" x2="82.5" y2="17.5" transform="rotate(45 50 50)"/>
          <line x1="50" y1="4" x2="50" y2="96" transform="rotate(60 50 50)"/>
          <line x1="4" y1="50" x2="96" y2="50" transform="rotate(60 50 50)"/>
          <line x1="17.5" y1="17.5" x2="82.5" y2="82.5" transform="rotate(60 50 50)"/>
          <line x1="17.5" y1="82.5" x2="82.5" y2="17.5" transform="rotate(60 50 50)"/>
          <line x1="50" y1="4" x2="50" y2="96" transform="rotate(75 50 50)"/>
          <line x1="4" y1="50" x2="96" y2="50" transform="rotate(75 50 50)"/>
          <line x1="17.5" y1="17.5" x2="82.5" y2="82.5" transform="rotate(75 50 50)"/>
          <line x1="17.5" y1="82.5" x2="82.5" y2="17.5" transform="rotate(75 50 50)"/>
        </g>
        <g fill="var(--primary-gold)" opacity="0.6">
          <circle cx="50" cy="8" r="0.8"/>
          <circle cx="50" cy="92" r="0.8"/>
          <circle cx="8" cy="50" r="0.8"/>
          <circle cx="92" cy="50" r="0.8"/>
        </g>
      </svg>
    </div>

    <div class="container">
      <div class="row align-items-center g-5">
        <!-- left content -->
        <div class="col-lg-6 hero-text-col">
          <div class="badge-icon mb-3" style="display:inline-flex; width:auto; height:auto; padding: 0.4rem 1.2rem; border-radius:30px; font-size: 0.85rem; font-weight:700;">
            🪔 DIVINE EXPERIENCES & SERVICES
          </div>
          <h1 class="hero-title">
            Connect with the <br />
            <span class="accent">Divine Essence</span>
          </h1>
          <p class="hero-desc">
            Experience spiritual peace, book personalized daily rituals, and support our temple trust projects. Easily manage your family poojas, secure slots with priests, and make secure donations directly.
          </p>
          <div class="d-flex flex-wrap gap-3">
            <a href="#poojas" class="btn btn-saffron btn-lg px-4 py-3">
              <i class="bi bi-calendar-check me-2"></i> Book Pooja Now
            </a>
            <a href="#donations" class="btn btn-outline-gold btn-lg px-4 py-3">
              <i class="bi bi-gift me-2"></i> Make Donation
            </a>
          </div>
          
          <div class="d-flex gap-5 mt-5 hero-stats">
            <div>
              <span class="fw-bold fs-3 text-gold" style="color: var(--primary-gold);">10+</span>
              <p class="text-muted small mb-0">Daily Rituals</p>
            </div>
            <div>
              <span class="fw-bold fs-3 text-gold" style="color: var(--primary-gold);">15+</span>
              <p class="text-muted small mb-0">Vedic Priests</p>
            </div>
            <div>
              <span class="fw-bold fs-3 text-gold" style="color: var(--primary-gold);">5000+</span>
              <p class="text-muted small mb-0">Devotee Family</p>
            </div>
          </div>
        </div>

        <!-- right image wrapper -->
        <div class="col-lg-6 text-center hero-img-col">
          <div class="hero-image-wrapper mx-auto" style="max-width: 480px;">
            <div class="hero-decor-badge badge-top-left">
              <div class="badge-icon"><i class="bi bi-heart-fill"></i></div>
              <div class="text-start">
                <p class="mb-0 fw-bold small text-dark">Total Blessed</p>
                <small class="text-muted">10k+ Prasad Sent</small>
              </div>
            </div>

            <div class="hero-decor-badge badge-bottom-right">
              <div class="badge-icon"><i class="bi bi-patch-check-fill"></i></div>
              <div class="text-start">
                <p class="mb-0 fw-bold small text-dark">Vedic Scholars</p>
                <small class="text-muted">Certified Priests</small>
              </div>
            </div>

            <div class="hero-image-container">
              <img src="https://images.unsplash.com/photo-1544735716-392fe2489ffa?w=800&auto=format&fit=crop&q=80"
                   alt="Illuminated Temple Gopuram" 
                   class="img-fluid" 
                   style="height: 520px; width: 100%; object-fit: cover;" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================ -->
  <!--  POOJA BOOKING GRID                          -->
  <!-- ============================================ -->
  <section class="py-6" id="poojas" style="padding: 6rem 0;">
    <div class="container">
      <div class="section-header text-center mx-auto" style="max-width: 600px;">
        <span class="section-subtitle">Sacred Services</span>
        <h2 class="section-title">Available Poojas</h2>
        <p class="text-muted mt-3">Select from our list of daily and special rituals. Book online and get a personalized blessing along with Prasad sent to your address.</p>
      </div>

      <div class="row g-4 justify-content-center">
        @forelse($poojas as $pooja)
          <div class="col-lg-4 col-md-6 pooja-card-wrapper">
            <div class="pooja-card">
              <div>
                <span class="pooja-category">{{ $pooja->category }}</span>
                <h3 class="pooja-title">{{ $pooja->pooja_name }}</h3>
                <p class="pooja-desc">{{ Str::limit($pooja->description, 100) }}</p>
              </div>

              <div>
                <div class="pooja-meta">
                  <div class="pooja-fee">₹{{ number_format($pooja->pooja_fee, 2) }}</div>
                  <div class="pooja-duration">
                    <i class="bi bi-clock"></i> {{ $pooja->duration_minutes }} Mins
                  </div>
                </div>

                <!-- Redirect directly to Login Page as requested -->
                <a href="{{ route('login') }}" class="btn btn-saffron w-100 py-2.5">
                  <i class="bi bi-calendar2-plus me-2"></i> Book Pooja
                </a>
              </div>
            </div>
          </div>
        @empty
          <div class="col-12 text-center py-5">
            <p class="text-muted fs-5">No active poojas available at this time. Please check back later.</p>
          </div>
        @endforelse
      </div>
    </div>
  </section>

  <!-- ============================================ -->
  <!--  UPCOMING EVENTS SECTION                     -->
  <!-- ============================================ -->
  <section class="py-6" id="events" style="background: var(--dark-gradient); color: var(--text-light); padding: 6rem 0; position: relative; overflow: hidden; border-top: 1.5px solid var(--border-gold); border-bottom: 1.5px solid var(--border-gold);">
    <!-- Ambient glows -->
    <div class="ambient-glow" style="top: 20%; left: -10%; width: 450px; height: 450px; background: radial-gradient(circle, rgba(212, 175, 55, 0.07) 0%, transparent 70%);"></div>
    <div class="ambient-glow" style="bottom: 10%; right: -10%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(255, 111, 0, 0.08) 0%, transparent 70%);"></div>
    
    <!-- Rotating Mandala SVG (Counter) -->
    <div class="mandala-container-left">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="46" fill="none" stroke="var(--primary-gold)" stroke-width="0.3" opacity="0.3"/>
        <circle cx="50" cy="50" r="38" fill="none" stroke="var(--primary-gold)" stroke-width="0.2" stroke-dasharray="1,1" opacity="0.5"/>
        <circle cx="50" cy="50" r="30" fill="none" stroke="var(--primary-gold)" stroke-width="0.3" opacity="0.4"/>
        <circle cx="50" cy="50" r="22" fill="none" stroke="var(--primary-gold)" stroke-width="0.2" stroke-dasharray="1,1" opacity="0.5"/>
        <circle cx="50" cy="50" r="14" fill="none" stroke="var(--primary-gold)" stroke-width="0.4" opacity="0.6"/>
        <g stroke="var(--primary-gold)" stroke-width="0.25" fill="none" opacity="0.5">
          <line x1="50" y1="4" x2="50" y2="96"/>
          <line x1="4" y1="50" x2="96" y2="50"/>
          <line x1="17.5" y1="17.5" x2="82.5" y2="82.5"/>
          <line x1="17.5" y1="82.5" x2="82.5" y2="17.5"/>
          <line x1="50" y1="4" x2="50" y2="96" transform="rotate(20 50 50)"/>
          <line x1="4" y1="50" x2="96" y2="50" transform="rotate(20 50 50)"/>
          <line x1="50" y1="4" x2="50" y2="96" transform="rotate(40 50 50)"/>
          <line x1="4" y1="50" x2="96" y2="50" transform="rotate(40 50 50)"/>
          <line x1="50" y1="4" x2="50" y2="96" transform="rotate(60 50 50)"/>
          <line x1="4" y1="50" x2="96" y2="50" transform="rotate(60 50 50)"/>
          <line x1="50" y1="4" x2="50" y2="96" transform="rotate(80 50 50)"/>
          <line x1="4" y1="50" x2="96" y2="50" transform="rotate(80 50 50)"/>
        </g>
      </svg>
    </div>

    <div class="container" style="position: relative; z-index: 2;">
      <div class="section-header text-center mx-auto" style="max-width: 600px;">
        <span class="section-subtitle">Auspicious Days</span>
        <h2 class="">Upcoming Festivals & Events</h2>
        <p class="text-muted mt-3" style="color: #c9bdae !important;">Stay updated with our festival calendar and special events. Participate online or visit in person to receive blessings.</p>
      </div>

      <div class="timeline-container">
        <div class="timeline-line"></div>
        <div class="timeline-progress" id="timelineProgress"></div>

        @forelse($events as $index => $event)
          <div class="timeline-item">
            <div class="timeline-badge">
              <i class="bi bi-calendar-event"></i>
            </div>
            <div class="timeline-card">
              <div class="event-date-badge">
                <i class="bi bi-calendar-check me-2"></i>{{ \Carbon\Carbon::parse($event->event_date)->format('M d, Y') }}
              </div>
              <h3 class="event-name">{{ $event->event_name }}</h3>
              <p class="event-desc mb-0">{{ $event->description }}</p>
              
              <div class="event-meta">
                <span><i class="bi bi-clock"></i> {{ \Carbon\Carbon::parse($event->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($event->end_time)->format('h:i A') }}</span>
                <span><i class="bi bi-geo-alt"></i> {{ $event->location }}</span>
              </div>
            </div>
          </div>
        @empty
          <div class="col-12 text-center py-5">
            <div class="timeline-card mx-auto text-center p-5" style="max-width: 600px; background: rgba(30, 20, 15, 0.65);">
              <i class="bi bi-calendar-x fs-1 text-muted mb-3" style="color: var(--primary-gold) !important;"></i>
              <h4 class="fw-bold text-white">No Festivals Listed Yet</h4>
              <p class="text-muted" style="color: #c9bdae !important;">Stay tuned! We are compiling the details of upcoming auspicious festivals and rituals. Check back soon.</p>
            </div>
          </div>
        @endforelse
      </div>
    </div>
  </section>

  <!-- ============================================ -->
  <!--  TEMPLE FEATURES SECTION                     -->
  <!-- ============================================ -->
  <section class="py-6" id="features" style="padding: 6rem 0;">
    <div class="container">
      <div class="section-header text-center mx-auto" style="max-width: 600px;">
        <span class="section-subtitle">Our Services</span>
        <h2 class="section-title">Temple Features</h2>
        <p class="text-muted mt-3">We offer state-of-the-art facilities and services designed to help devotees easily connect with the temple administration.</p>
      </div>

      <div class="row g-4 justify-content-center">
        <!-- Feature 1: Online Prasad -->
        <div class="col-lg-4 col-md-6 feature-card-wrapper">
          <div class="feature-card">
            <div class="feature-icon-wrapper">
              <i class="bi bi-box2-heart"></i>
            </div>
            <h4 class="fw-bold mb-3">Home Prasad Delivery</h4>
            <p class="text-muted mb-4">Book poojas from anywhere. We perform the rituals on your behalf with full Vedic traditions and courier holy Prasad directly to your home.</p>
            <!-- Direct redirect to login -->
            <a href="{{ route('login') }}" class="btn btn-outline-gold btn-sm">Learn More</a>
          </div>
        </div>

        <!-- Feature 2: Devotee Memberships -->
        <div class="col-lg-4 col-md-6 feature-card-wrapper">
          <div class="feature-card">
            <div class="feature-icon-wrapper">
              <i class="bi bi-shield-check"></i>
            </div>
            <h4 class="fw-bold mb-3">Devotee Membership</h4>
            <p class="text-muted mb-4">Become a life member of the temple trust. Enjoy priority slot bookings, exclusive invitations to major festivals, and complimentary stays.</p>
            <!-- Direct redirect to login -->
            <a href="{{ route('login') }}" class="btn btn-outline-gold btn-sm">Register Member</a>
          </div>
        </div>

        <!-- Feature 3: Priest Consultations -->
        <div class="col-lg-4 col-md-6 feature-card-wrapper">
          <div class="feature-card">
            <div class="feature-icon-wrapper">
              <i class="bi bi-person-video3"></i>
            </div>
            <h4 class="fw-bold mb-3">Vedic Priest Services</h4>
            <p class="text-muted mb-4">Arrange offline or online priest consultations for marriages, Griha Pravesh, naming ceremonies, and general guidance on astro-rituals.</p>
            <!-- Direct redirect to login -->
            <a href="{{ route('login') }}" class="btn btn-outline-gold btn-sm">Consult Priest</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================ -->
  <!--  DONATIONS & GUEST DONATIONS                 -->
  <!-- ============================================ -->
  <section class="py-6 donations-section" id="donations" style="padding: 6rem 0;">
    <div class="container">
      <div class="section-header text-center mx-auto" style="max-width: 600px;">
        <span class="section-subtitle">Generous Support</span>
        <h2 class="section-title">Support The Temple</h2>
        <p class="text-muted mt-3">Your donations support our daily anna-prasada (free meals) schemes, gaushala (cow protection), and Vedic school maintenance.</p>
      </div>

      <!-- Display general validation errors for the donation form -->
      @if(isset($errors) && $errors->any())
        <div class="row justify-content-center mb-4">
          <div class="col-lg-8">
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius: 16px; background-color: #fff2f2;">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                <strong class="text-danger">Please fix the form errors before submitting:</strong>
              </div>
              <ul class="mb-0 mt-2 text-danger small">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          </div>
        </div>
      @endif

      <div class="row g-5 align-items-stretch">
        <!-- Left Column: Stats & Login Option -->
        <div class="col-lg-5 d-flex flex-column justify-content-between donation-left-col">
          <!-- Total Donation Amount Display -->
          <div class="donation-counter-card text-center mb-4 flex-grow-1 d-flex flex-column justify-content-center align-items-center">
            <i class="bi bi-bank fs-1 mb-3 text-gold" style="color: var(--primary-gold);"></i>
            <h4 class="font-divine" style="color: var(--gold-light); letter-spacing: 1px;">Overall Donations Received</h4>
            <div class="counter-value my-3" id="donationCounter" data-target="{{ $totalDonations }}">₹0</div>
            <p class="text-muted mb-0 small" style="color: #c9bdae !important;">Directly contributing to Temple renovation & community service projects.</p>
          </div>

          <!-- Donate With Login CTA -->
          <div class="card p-4 text-center border-0 shadow-sm" style="border-radius: 24px; background: rgba(184,134,58,0.06); border: 1.5px solid var(--border-glass);">
            <h5 class="fw-bold text-dark mb-2"><i class="bi bi-person-check me-2 text-gold"></i>Donate with Account</h5>
            <p class="text-muted small mb-3">Log in to your devotee account to access and download instant 80G tax exemption receipts and track your full payment history.</p>
            <a href="{{ route('login') }}" class="btn btn-outline-gold w-100">
              <i class="bi bi-box-arrow-in-right me-1"></i> Log In and Donate
            </a>
          </div>
        </div>

        <!-- Right Column: Donate Without Login Form -->
        <div class="col-lg-7 donation-form-col">
          <div class="donation-form-wrapper">
            <h3 class="fw-bold mb-4 font-divine text-dark text-center">Donate Without Login</h3>

            <!-- Payment Method Selection -->
            <div class="text-center">
              <div class="donation-tabs">
                <button type="button" class="donation-tab-btn active" id="tabBank" onclick="switchDonationTab('Bank')">
                  <i class="bi bi-credit-card me-2"></i>Bank Transfer
                </button>
                <button type="button" class="donation-tab-btn" id="tabUPI" onclick="switchDonationTab('UPI')">
                  <i class="bi bi-qr-code me-2"></i>UPI Transfer
                </button>
              </div>
            </div>

            <!-- Form -->
            <form action="{{ route('donate.without.login') }}" method="POST" id="guestDonationForm">
              @csrf
              <input type="hidden" name="payment_method" id="inputPaymentMethod" value="Bank" />

              <!-- Basic Fields -->
              <div class="row g-3">
                <div class="col-md-12">
                  <div class="form-floating">
                    <input type="text" class="form-control" name="donor_name" id="donorName" placeholder="Devotee Name" required value="{{ old('donor_name') }}" />
                    <label for="donorName">Devotee Full Name *</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="email" class="form-control" name="email" id="donorEmail" placeholder="Email Address" value="{{ old('email') }}" />
                    <label for="donorEmail">Email Address (Optional)</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" name="mobile" id="donorMobile" placeholder="Mobile Number" value="{{ old('mobile') }}" />
                    <label for="donorMobile">Mobile Number (Optional)</label>
                  </div>
                </div>
                <div class="col-md-12">
                  <div class="form-floating">
                    <input type="number" min="1" class="form-control" name="amount" id="donationAmount" placeholder="Donation Amount" required value="{{ old('amount') }}" />
                    <label for="donationAmount">Donation Amount (₹) *</label>
                  </div>
                  <div class="preset-amount-container">
                    <button type="button" class="preset-btn" onclick="setPresetAmount(501)">₹501</button>
                    <button type="button" class="preset-btn" onclick="setPresetAmount(1100)">₹1,100</button>
                    <button type="button" class="preset-btn" onclick="setPresetAmount(2100)">₹2,100</button>
                    <button type="button" class="preset-btn" onclick="setPresetAmount(5001)">₹5,001</button>
                    <button type="button" class="preset-btn" onclick="setPresetAmount(11000)">₹11,000</button>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <select class="form-select" name="purpose" id="donationPurpose" required>
                      <option value="Annadanam" {{ old('purpose') == 'Annadanam' ? 'selected' : '' }}>Annadanam (Free Meals Scheme)</option>
                      <option value="Gaushala" {{ old('purpose') == 'Gaushala' ? 'selected' : '' }}>Gaushala (Cow Protection)</option>
                      <option value="Temple Renovation" {{ old('purpose') == 'Temple Renovation' ? 'selected' : '' }}>Temple Renovation</option>
                      <option value="Vedic School" {{ old('purpose') == 'Vedic School' ? 'selected' : '' }}>Vedic Gurukul School</option>
                      <option value="General Puja & Festivals" {{ old('purpose') == 'General Puja & Festivals' ? 'selected' : '' }}>General Puja & Festivals</option>
                      <option value="Other" {{ old('purpose') == 'Other' ? 'selected' : '' }}>Other Purpose</option>
                    </select>
                    <label for="donationPurpose">Donation Purpose *</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" name="purpose_details" id="purposeDetails" placeholder="Occasion (e.g. Birthday, Anniversary)" value="{{ old('purpose_details') }}" />
                    <label for="purposeDetails">Occasion / Special Notes (Optional)</label>
                  </div>
                </div>
              </div>

              <!-- DYNAMIC CONTENT DEPENDING ON PAYMENT METHOD -->
              <div class="mt-4">
                <!-- BANK DETAILS PANEL -->
                <div id="bankDetailsPanel">
                  <div class="row g-4 align-items-center mb-3">
                    <div class="col-md-7">
                      <div class="bank-details-card mb-0">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bank2 text-gold me-2"></i>Temple Bank Account Details</h6>
                        <div class="bank-detail-item">
                          <span class="text-muted">Account Holder:</span>
                          <strong class="text-dark">SHREE TEMPLE RELIEF TRUST</strong>
                        </div>
                        <div class="bank-detail-item">
                          <span class="text-muted">Bank Name:</span>
                          <strong class="text-dark">BANK OF BARODA</strong>
                        </div>
                        <div class="bank-detail-item">
                          <span class="text-muted">Account Number:</span>
                          <strong class="text-dark">98765432109</strong>
                        </div>
                        <div class="bank-detail-item">
                          <span class="text-muted">IFSC Code:</span>
                          <strong class="text-dark">BARB0TEMPLE</strong>
                        </div>
                        <div class="bank-detail-item">
                          <span class="text-muted">Branch Name:</span>
                          <strong class="text-dark">SIDDHIVINAYAK BRANCH, MUMBAI</strong>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-5">
                      <div class="qr-code-container mb-0" style="padding: 1.2rem;">
                        <h6 class="fw-bold text-dark mb-2" style="font-size: 0.9rem;"><i class="bi bi-qr-code-scan text-gold me-2"></i>Scan to Pay (UPI)</h6>
                        <div class="text-muted small mb-2" style="font-size: 0.7rem; word-break: break-all;">UPI ID: <strong class="text-gold">rohandevadigapithrodi-1@oksbi</strong></div>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=upi://pay?pa=rohandevadigapithrodi-1@oksbi%26pn=Temple%20Trust" 
                             alt="UPI QR Code" 
                             class="img-fluid border p-2 bg-white shadow-sm mb-2 upi-qr-image" 
                             style="border-radius: 12px; width: 130px; height: 130px;" />
                        <p class="text-muted small mb-0 qr-instruction" style="font-size: 0.7rem;">Enter amount to generate QR.</p>
                      </div>
                    </div>
                  </div>

                  <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i>Please transfer funds to the bank account above or scan the QR code, then enter transaction details below.</p>
                  
                  <div class="row g-3">
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input type="text" class="form-control" name="bank_name" id="bankName" placeholder="Your Bank Name" value="{{ old('bank_name') }}" />
                        <label for="bankName">Your Bank Name *</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input type="text" class="form-control" name="bank_account_no" id="bankAccount" placeholder="Your Bank Account No" value="{{ old('bank_account_no') }}" />
                        <label for="bankAccount">Your Account No *</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input type="text" class="form-control" name="bank_ifsc" id="bankIfsc" placeholder="Your Bank IFSC" value="{{ old('bank_ifsc') }}" />
                        <label for="bankIfsc">Your Bank IFSC Code *</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input type="text" class="form-control" name="bank_branch" id="bankBranch" placeholder="Your Bank Branch" value="{{ old('bank_branch') }}" />
                        <label for="bankBranch">Your Bank Branch *</label>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- UPI PANEL -->
                <div id="upiPanel" style="display: none;">
                  <div class="qr-code-container">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-qr-code-scan text-gold me-2"></i>Scan to Pay with Any UPI App</h6>
                    <div class="text-muted small mb-3">UPI ID: <strong class="text-gold">rohandevadigapithrodi-1@oksbi</strong></div>
                    
                    <!-- Dynamic QR Code generated by JS -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=upi://pay?pa=rohandevadigapithrodi-1@oksbi%26pn=Temple%20Trust" 
                         alt="UPI QR Code" 
                         class="img-fluid border p-2 bg-white shadow-sm mb-3 upi-qr-image" 
                         style="border-radius: 12px; width: 180px; height: 180px;" />
                    <p class="text-muted small mb-0 qr-instruction">Enter amount above to generate dynamic QR code.</p>
                  </div>
                </div>
              </div>          </div>

              <!-- Transaction ID (Common Field) -->
              <div class="mt-3">
                <div class="form-floating">
                  <input type="text" class="form-control" name="transaction_id" id="txnId" placeholder="Transaction Reference ID" required value="{{ old('transaction_id') }}" />
                  <label for="txnId">Transaction Reference ID / UTR Number *</label>
                </div>
                <small class="text-muted mt-1 d-block"><i class="bi bi-shield-lock-fill text-success me-1"></i>Your information is fully encrypted and securely saved.</small>
              </div>

              <div class="mt-4">
                <button type="submit" class="btn btn-saffron w-100 py-3 fs-5">
                  <i class="bi bi-heart-pulse me-2"></i>Submit Donation Record
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================ -->
  <!--  FOOTER                                      -->
  <!-- ============================================ -->
  <footer class="footer-custom">
    <div class="container">
      <div class="row g-4 justify-content-between mb-5">
        <div class="col-lg-4 col-md-6">
          <div class="footer-brand d-flex align-items-center">
            <i class="bi bi-temple me-2" style="color: var(--primary-saffron);"></i>
            <span>SHREE MANDIR</span>
          </div>
          <p class="text-muted" style="color: #a89d92 !important;">Dedicated to preserving ancient Vedic traditions, spreading spiritual wisdom, and rendering selfless humanitarian services to all devotees around the world.</p>
          <div class="social-icons">
            <a href="#" class="social-icon-btn"><i class="bi bi-facebook"></i></a>
            <a href="#" class="social-icon-btn"><i class="bi bi-instagram"></i></a>
            <a href="#" class="social-icon-btn"><i class="bi bi-youtube"></i></a>
            <a href="#" class="social-icon-btn"><i class="bi bi-twitter-x"></i></a>
          </div>
        </div>

        <div class="col-lg-2 col-md-6 col-6">
          <h5 class="text-white fw-bold mb-4">Quick Links</h5>
          <ul class="footer-links">
            <li><a href="#hero">Home</a></li>
            <li><a href="#poojas">Book Pooja</a></li>
            <li><a href="#events">Festivals</a></li>
            <li><a href="#donations">Donations</a></li>
          </ul>
        </div>

        <div class="col-lg-2 col-md-6 col-6">
          <h5 class="text-white fw-bold mb-4">Devotee Portal</h5>
          <ul class="footer-links">
            <li><a href="{{ route('login') }}">Devotee Log In</a></li>
            <li><a href="{{ route('register') }}">Create Account</a></li>
            <li><a href="#features">Member Benefits</a></li>
            <li><a href="{{ route('login') }}">Priest Schedule</a></li>
          </ul>
        </div>

        <div class="col-lg-3 col-md-6">
          <h5 class="text-white fw-bold mb-4">Contact Info</h5>
          <p class="mb-2 text-muted" style="color: #a89d92 !important;"><i class="bi bi-geo-alt-fill text-gold me-2"></i>Main Temple Complex, Srisailam, Andhra Pradesh, India</p>
          <p class="mb-2 text-muted" style="color: #a89d92 !important;"><i class="bi bi-envelope-fill text-gold me-2"></i>contact@shreemandir.org</p>
          <p class="mb-0 text-muted" style="color: #a89d92 !important;"><i class="bi bi-telephone-fill text-gold me-2"></i>+91 80 1234 5678</p>
        </div>
      </div>

      <div class="pt-4 border-top border-secondary text-center text-muted small" style="border-color: rgba(255,255,255,0.08) !important;">
        <p style="color: gray"  class="mb-0">© 2026 Shree Mandir Trust — All Rights Reserved. Crafted with deep devotion and dedication. </p>
      </div>
    </div>
  </footer>

  <!-- ============================================ -->
  <!--  SUCCESS MODAL                              -->
  <!-- ============================================ -->
  @if(session('success_donation'))
    <div class="modal fade show" id="donationSuccessModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.6); z-index: 1050;">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4 border-0 shadow-lg" style="border-radius: 24px; background: #fffcf8; border: 2px solid var(--primary-gold) !important;">
          <div class="modal-body">
            <div class="text-success mb-3" style="font-size: 4.5rem; filter: drop-shadow(0 4px 10px rgba(40,167,69,0.25));">
              <i class="bi bi-patch-check-fill text-success"></i>
            </div>
            <h3 class="fw-bold mb-2 font-divine" style="color: var(--primary-saffron);">Jai Shri Krishna!</h3>
            <p class="text-dark mb-4">{{ session('success_donation') }}</p>
            <button type="button" class="btn btn-gold px-5 py-2.5 rounded-pill" onclick="document.getElementById('donationSuccessModal').style.display='none'">
              Om Namah Shivaya
            </button>
          </div>
        </div>
      </div>
    </div>
  @endif

  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Custom Javascript Logic (GSAP animations, switch tabs, QR generator, counter) -->
  <script>
    // ----- Navbar Scrolling Effect -----
    window.addEventListener('scroll', function() {
      const navbar = document.getElementById('mainNavbar');
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });

    // ----- Set Preset Donation Amount -----
    function setPresetAmount(amount) {
      const amountInput = document.getElementById('donationAmount');
      if (amountInput) {
        amountInput.value = amount;
        // Dispatch input event to update QR code server/URL triggers
        amountInput.dispatchEvent(new Event('input'));
      }
    }

    // ----- Donate Without Login Tab Switching -----
    function switchDonationTab(method) {
      const tabBank = document.getElementById('tabBank');
      const tabUPI = document.getElementById('tabUPI');
      const bankPanel = document.getElementById('bankDetailsPanel');
      const upiPanel = document.getElementById('upiPanel');
      const inputMethod = document.getElementById('inputPaymentMethod');

      // Update input fields validation requirements
      const bankFields = ['bankName', 'bankAccount', 'bankIfsc', 'bankBranch'];

      if (method === 'Bank') {
        tabBank.classList.add('active');
        tabUPI.classList.remove('active');
        bankPanel.style.display = 'block';
        upiPanel.style.display = 'none';
        inputMethod.value = 'Bank';

        // Make bank fields required
        bankFields.forEach(id => {
          document.getElementById(id).setAttribute('required', 'required');
        });
      } else {
        tabUPI.classList.add('active');
        tabBank.classList.remove('active');
        upiPanel.style.display = 'block';
        bankPanel.style.display = 'none';
        inputMethod.value = 'UPI';

        // Remove bank fields requirement
        bankFields.forEach(id => {
          document.getElementById(id).removeAttribute('required');
        });
      }
    }

    // Initialize required bank fields on load
    switchDonationTab('Bank');

    // ----- UPI Dynamic QR Code Generator -----
    const amountInput = document.getElementById('donationAmount');
    const qrImages = document.querySelectorAll('.upi-qr-image');
    const qrInstructions = document.querySelectorAll('.qr-instruction');

    amountInput.addEventListener('input', function() {
      const val = parseFloat(this.value) || 0;
      const payeeUPI = 'rohandevadigapithrodi-1@oksbi';
      const payeeName = 'Shree Mandir Trust';
      
      let qrSrc = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=upi://pay?pa=${payeeUPI}%26pn=${encodeURIComponent(payeeName)}`;
      let instructionText = "Enter amount above to generate dynamic QR code.";
      let isSuccess = false;

      if (val > 0) {
        const upiUrl = `upi://pay?pa=${payeeUPI}&pn=${encodeURIComponent(payeeName)}&am=${val}&cu=INR&tn=TempleDonation`;
        qrSrc = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(upiUrl)}`;
        instructionText = `Dynamic QR generated for <strong class="text-saffron">₹${val.toLocaleString('en-IN')}</strong>. Scan to pay now.`;
        isSuccess = true;
      }

      qrImages.forEach(img => {
        img.src = qrSrc;
      });

      qrInstructions.forEach(inst => {
        inst.innerHTML = instructionText;
        if (isSuccess) {
          inst.classList.add('text-success');
        } else {
          inst.classList.remove('text-success');
        }
      });
    });

    // ----- GSAP Animations -----
    document.addEventListener("DOMContentLoaded", function() {
      gsap.registerPlugin(ScrollTrigger);

      // Hero animations
      gsap.from(".hero-text-col > *", {
        opacity: 0,
        y: 40,
        stagger: 0.2,
        duration: 1.2,
        ease: "power3.out"
      });

      gsap.from(".hero-img-col", {
        opacity: 0,
        scale: 0.95,
        duration: 1.5,
        ease: "power2.out"
      });

      gsap.from(".hero-decor-badge", {
        opacity: 0,
        y: 20,
        stagger: 0.3,
        duration: 1.2,
        delay: 0.5,
        ease: "power2.out"
      });

      // Pooja Cards animations
      gsap.from(".pooja-card-wrapper", {
        scrollTrigger: {
          trigger: "#poojas",
          start: "top 80%",
        },
        opacity: 0,
        y: 50,
        stagger: 0.15,
        duration: 1,
        ease: "power2.out"
      });

      // Feature Cards animations
      gsap.from(".feature-card-wrapper", {
        scrollTrigger: {
          trigger: "#features",
          start: "top 80%",
        },
        opacity: 0,
        y: 50,
        stagger: 0.15,
        duration: 1,
        ease: "power2.out"
      });

      // Timeline items slide in and timeline line filling
      const timelineItems = gsap.utils.toArray(".timeline-item");
      timelineItems.forEach((item, index) => {
        gsap.from(item, {
          scrollTrigger: {
            trigger: item,
            start: "top 85%",
          },
          opacity: 0,
          x: index % 2 === 0 ? -60 : 60,
          duration: 1,
          ease: "power3.out"
        });
      });

      // Animate the timeline progress line based on scroll
      gsap.to("#timelineProgress", {
        scrollTrigger: {
          trigger: ".timeline-container",
          start: "top 60%",
          end: "bottom 80%",
          scrub: true,
        },
        height: "100%",
        ease: "none"
      });

      // Donation overall stats animation
      gsap.from(".donation-left-col > *", {
        scrollTrigger: {
          trigger: "#donations",
          start: "top 75%",
        },
        opacity: 0,
        x: -40,
        stagger: 0.2,
        duration: 1,
        ease: "power2.out"
      });

      gsap.from(".donation-form-col", {
        scrollTrigger: {
          trigger: "#donations",
          start: "top 75%",
        },
        opacity: 0,
        x: 40,
        duration: 1.2,
        ease: "power2.out"
      });

      // Donation counter count-up animation
      const counterElement = document.getElementById("donationCounter");
      const targetVal = parseFloat(counterElement.getAttribute("data-target")) || 0;
      
      let countObj = { value: 0 };
      gsap.to(countObj, {
        scrollTrigger: {
          trigger: "#donations",
          start: "top 75%",
        },
        value: targetVal,
        duration: 2.5,
        ease: "power3.out",
        onUpdate: function() {
          counterElement.innerHTML = "₹" + Math.floor(countObj.value).toLocaleString('en-IN');
        }
      });
    });
  </script>
</body>
</html>