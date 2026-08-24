<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Temple e-Hundi — Sacred Online Donation</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet" />
  <style>
    /* ----- RESET & ROOT ----- */
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --gold: #D4AF37;
      --gold-light: #F0D060;
      --gold-dark: #A07820;
      --maroon: #6B0F1A;
      --maroon-mid: #8B1A28;
      --cream: #FDF6E3;
      --sand: #E8D5A3;
      --brown: #3E1C00;
      --brown-mid: #5C2A00;
      --white: #FFFFFF;
      --shadow-gold: 0 4px 32px rgba(212, 175, 55, 0.18);
    }

    html,
    body {
      min-height: 100vh;
      font-family: 'Lato', sans-serif;
      background: var(--brown);
      overflow-x: hidden;
    }

    /* ----- BACKGROUND ----- */
    .bg-layer {
      position: fixed;
      inset: 0;
      z-index: 0;
      background:
        radial-gradient(ellipse at 20% 0%, rgba(212, 175, 55, 0.22) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 100%, rgba(107, 15, 26, 0.35) 0%, transparent 60%),
        linear-gradient(160deg, #2A0D00 0%, #3E1C00 40%, #1A0800 100%);
    }

    .bg-pattern {
      position: fixed;
      inset: 0;
      z-index: 0;
      opacity: 0.04;
      background-image:
        repeating-linear-gradient(45deg, var(--gold) 0px, var(--gold) 1px, transparent 1px, transparent 20px),
        repeating-linear-gradient(-45deg, var(--gold) 0px, var(--gold) 1px, transparent 1px, transparent 20px);
    }

    /* ----- FLOATING PARTICLES ----- */
    #particle-canvas {
      position: fixed;
      inset: 0;
      z-index: 1;
      pointer-events: none;
    }

    /* ----- MAIN WRAPPER ----- */
    .page-wrap {
      position: relative;
      z-index: 2;
      min-height: 100vh;
      padding: 120px 16px 64px;
    }

    /* ----- HEADER ----- */
    .temple-header {
      text-align: center;
      margin-bottom: 36px;
    }
    .temple-header .temple-icon {
      font-size: 3.2rem;
      line-height: 1;
      filter: drop-shadow(0 0 12px rgba(212, 175, 55, 0.8));
      animation: floatIcon 3.5s ease-in-out infinite;
    }
    @keyframes floatIcon {
      0%,
      100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-8px);
      }
    }
    .temple-header h1 {
      font-family: 'Cinzel', serif;
      font-size: clamp(1.8rem, 4vw, 2.8rem);
      font-weight: 700;
      color: var(--gold);
      letter-spacing: 0.04em;
      text-shadow: 0 2px 18px rgba(212, 175, 55, 0.5);
      margin-top: 8px;
    }
    .temple-header .subtitle {
      font-size: 0.95rem;
      color: rgba(240, 208, 96, 0.75);
      font-weight: 300;
      letter-spacing: 0.06em;
      margin-top: 8px;
      max-width: 480px;
      margin-left: auto;
      margin-right: auto;
      line-height: 1.6;
    }
    .divider-om {
      display: flex;
      align-items: center;
      gap: 12px;
      justify-content: center;
      margin: 14px 0 0;
      color: rgba(212, 175, 55, 0.4);
      font-size: 0.7rem;
      letter-spacing: 0.2em;
    }
    .divider-om::before,
    .divider-om::after {
      content: '';
      width: 60px;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.5), transparent);
    }

    /* ----- LAYOUT ----- */
    .main-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 32px;
      max-width: 960px;
      margin: 0 auto;
      align-items: start;
    }
    @media (max-width: 767px) {
      .main-grid {
        grid-template-columns: 1fr;
      }
    }

    /* ----- DONATION CARD ----- */
    .donation-card {
      background: rgba(255, 255, 255, 0.04);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      border: 1px solid rgba(212, 175, 55, 0.22);
      border-radius: 20px;
      padding: 32px 28px;
      box-shadow: 0 8px 48px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(212, 175, 55, 0.15);
    }
    .card-title {
      font-family: 'Cinzel', serif;
      font-size: 1.1rem;
      color: var(--gold);
      letter-spacing: 0.08em;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .card-title::after {
      content: '';
      flex: 1;
      height: 1px;
      background: linear-gradient(90deg, rgba(212, 175, 55, 0.4), transparent);
    }

    .amount-wrap {
      position: relative;
      margin-bottom: 20px;
    }
    .currency-symbol {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--gold);
      pointer-events: none;
    }
    #donationAmount {
      width: 100%;
      background: rgba(255, 255, 255, 0.06);
      border: 1.5px solid rgba(212, 175, 55, 0.3);
      border-radius: 12px;
      padding: 16px 16px 16px 40px;
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--gold-light);
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
      letter-spacing: 0.03em;
    }
    #donationAmount::placeholder {
      color: rgba(212, 175, 55, 0.3);
      font-weight: 300;
      font-size: 1rem;
    }
    #donationAmount:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15), 0 0 24px rgba(212, 175, 55, 0.1);
    }

    .quick-label {
      font-size: 0.72rem;
      letter-spacing: 0.12em;
      color: rgba(212, 175, 55, 0.5);
      margin-bottom: 10px;
      text-transform: uppercase;
    }
    .quick-amounts {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 28px;
    }
    .quick-btn {
      background: rgba(212, 175, 55, 0.08);
      border: 1px solid rgba(212, 175, 55, 0.25);
      border-radius: 8px;
      color: var(--sand);
      font-size: 0.82rem;
      font-weight: 600;
      padding: 7px 13px;
      cursor: pointer;
      transition: all 0.18s;
      letter-spacing: 0.02em;
    }
    .quick-btn:hover {
      background: rgba(212, 175, 55, 0.2);
      border-color: var(--gold);
      color: var(--gold-light);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2);
    }
    .quick-btn.active {
      background: rgba(212, 175, 55, 0.28);
      border-color: var(--gold);
      color: var(--gold-light);
    }

    .donate-btn {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, #C8990A 0%, #D4AF37 45%, #E8C84A 100%);
      border: none;
      border-radius: 12px;
      font-family: 'Cinzel', serif;
      font-size: 1.05rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      color: var(--brown);
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 4px 20px rgba(212, 175, 55, 0.4);
    }
    .donate-btn::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.25), transparent 60%);
      border-radius: inherit;
      pointer-events: none;
    }
    .donate-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 8px 32px rgba(212, 175, 55, 0.55);
    }
    .donate-btn:active:not(:disabled) {
      transform: translateY(0);
    }
    .donate-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .ripple-effect {
      position: absolute;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.35);
      transform: scale(0);
      animation: ripple 0.5s ease-out;
      pointer-events: none;
    }
    @keyframes ripple {
      to {
        transform: scale(4);
        opacity: 0;
      }
    }

    /* ----- HUNDI COLUMN ----- */
    .hundi-col {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
      justify-content: center;
    }

    /* ----- TRADITIONAL TEMPLE HUNDI ----- */
    .hundi-wrap {
      position: relative;
      width: 220px;
      height: 300px;
      animation: hundiFloat 4s ease-in-out infinite;
      margin-top: 20px;
    }
    @keyframes hundiFloat {
      0%,
      100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-10px);
      }
    }
    .hundi-wrap.shake {
      animation: hundiShake 0.5s ease-out, hundiFloat 4s ease-in-out infinite 0.5s;
    }
    @keyframes hundiShake {
      0%,
      100% {
        transform: translateX(0);
      }
      20% {
        transform: translateX(-5px) rotate(-1deg);
      }
      40% {
        transform: translateX(5px) rotate(1deg);
      }
      60% {
        transform: translateX(-4px);
      }
      80% {
        transform: translateX(4px);
      }
    }
    .hundi-glow {
      position: absolute;
      inset: -20px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(212, 175, 55, 0.18) 0%, transparent 70%);
      pointer-events: none;
      transition: opacity 0.4s;
    }
    .hundi-glow.lit {
      background: radial-gradient(circle, rgba(212, 175, 55, 0.5) 0%, rgba(212, 175, 55, 0.1) 50%, transparent 70%);
      animation: glowPulse 0.6s ease-out;
    }
    @keyframes glowPulse {
      0% {
        transform: scale(0.8);
        opacity: 0;
      }
      50% {
        transform: scale(1.2);
        opacity: 1;
      }
      100% {
        transform: scale(1);
        opacity: 1;
      }
    }
    #sparkle-layer {
      position: absolute;
      inset: 0;
      pointer-events: none;
      z-index: 10;
      overflow: visible;
    }

    /* ----- CURRENCY ANIMATION LAYER ----- */
    #currency-layer {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      pointer-events: none;
      z-index: 100;
    }
    .currency-item {
      position: absolute;
      transform-origin: center center;
      will-change: transform, opacity;
    }
    .note-svg {
      filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.4));
    }
    .coin-svg {
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
    }

    /* ----- SUCCESS OVERLAY ----- */
    #success-overlay {
      position: fixed;
      inset: 0;
      z-index: 200;
      background: rgba(20, 8, 0, 0.88);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.6s;
      backdrop-filter: blur(6px);
    }
    #success-overlay.show {
      opacity: 1;
      pointer-events: all;
    }
    .success-icon {
      font-size: 3.5rem;
      animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes popIn {
      from {
        transform: scale(0);
        opacity: 0;
      }
      to {
        transform: scale(1);
        opacity: 1;
      }
    }
    .success-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(1.4rem, 3vw, 2rem);
      color: var(--gold);
      margin-top: 16px;
      text-shadow: 0 0 30px rgba(212, 175, 55, 0.6);
      letter-spacing: 0.06em;
    }
    .success-sub {
      font-size: 1.1rem;
      color: rgba(240, 208, 96, 0.7);
      margin-top: 8px;
      letter-spacing: 0.1em;
    }
    .success-close {
      margin-top: 32px;
      background: transparent;
      border: 1.5px solid rgba(212, 175, 55, 0.5);
      border-radius: 8px;
      color: var(--gold);
      font-family: 'Cinzel', serif;
      font-size: 0.85rem;
      letter-spacing: 0.1em;
      padding: 10px 28px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .success-close:hover {
      background: rgba(212, 175, 55, 0.15);
      border-color: var(--gold);
    }
    #celebration-canvas {
      position: absolute;
      inset: 0;
      pointer-events: none;
    }

    .footer-bar {
      text-align: center;
      margin-top: 40px;
      color: rgba(212, 175, 55, 0.25);
      font-size: 0.72rem;
      letter-spacing: 0.12em;
    }

    /* ----- ENHANCED INDIAN CURRENCY STYLES ----- */
    .currency-item .note-svg {
      border-radius: 6px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.6);
    }
    .currency-item .coin-svg {
      filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.7));
    }
    
    /* ----- BACK BUTTON ----- */
    .back-btn {
      position: absolute;
      top: 24px;
      left: 24px;
      z-index: 100;
      color: var(--gold);
      border: 1px solid rgba(212, 175, 55, 0.3);
      background: rgba(42, 18, 0, 0.6);
      backdrop-filter: blur(10px);
      padding: 8px 20px;
      border-radius: 30px;
      font-weight: 600;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.9rem;
    }
    .back-btn:hover {
      background: var(--gold);
      color: var(--brown);
      border-color: var(--gold);
      transform: translateX(-3px);
      box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
    }
  
    /* ----- NAVBAR STYLE VARS ----- */
    :root {
      --primary-saffron: #ff6f00;
      --saffron-dark: #e65100;
      --saffron-glow: rgba(255, 111, 0, 0.08);
      --primary-gold: #b8863a;
      --gold-glow: rgba(184, 134, 58, 0.12);
      --gold-light: #f3c675;
      --gold-dark: #997322;
      --gold-gradient: linear-gradient(135deg, #c9933b 0%, #b8863a 50%, #9c6c28 100%);
      --saffron-gradient: linear-gradient(135deg, #ff9e00 0%, #ff6f00 50%, #e65100 100%);
    }

    /* ----- navigation bar ----- */
    .navbar-custom {
      background: rgba(253, 251, 247, 0.78);
      backdrop-filter: blur(24px) saturate(200%);
      -webkit-backdrop-filter: blur(24px) saturate(200%);
      border: 1px solid rgba(184, 134, 58, 0.22);
      border-radius: 40px;
      margin-top: 15px;
      padding: 0.6rem 1.8rem;
      transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 8px 32px rgba(184, 134, 58, 0.04), 0 1px 1px rgba(255, 255, 255, 0.5) inset;
      z-index: 1000;
      position: fixed;
      top: 0;
      width: calc(100% - 80px);
      left: 40px;
      right: 40px;
    }

    @media (max-width: 991px) {
      .navbar-custom {
        width: 100% !important;
        left: 0 !important;
        right: 0 !important;
        margin-top: 0 !important;
        border-radius: 0 0 20px 20px !important;
      }
    }

    .navbar-custom.scrolled {
      margin-top: 0;
      border-radius: 0 0 24px 24px !important;
      border-left: 0;
      border-right: 0;
      border-top: 0;
      width: 100% !important;
      left: 0 !important;
      right: 0 !important;
      background: rgba(253, 251, 247, 0.94);
      border-bottom: 1px solid rgba(184, 134, 58, 0.28);
      padding: 0.5rem 2rem;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
    }

    .navbar-custom .navbar-brand {
      font-family: 'Cinzel', serif;
      font-weight: 700;
      font-size: 1.55rem;
      letter-spacing: 1.5px;
      background: linear-gradient(135deg, #8b6914 0%, #d4af37 40%, #e8c84a 60%, #b8863a 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      filter: drop-shadow(0 2px 4px rgba(184, 134, 58, 0.15));
      text-decoration: none;
      transition: transform 0.3s;
    }
    
    .navbar-custom .navbar-brand:hover {
      transform: scale(1.02);
    }

    .navbar-custom .navbar-brand i {
      color: #ff6f00;
      background: linear-gradient(135deg, #ff6f00, #ff8f00);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      filter: drop-shadow(0 2px 8px rgba(255, 111, 0, 0.4));
      display: inline-block;
      transition: transform 0.4s ease;
    }
    
    .navbar-custom .navbar-brand:hover i {
      transform: rotate(15deg) scale(1.1);
    }

    .navbar-custom .nav-link {
      color: #4a3e35;
      font-weight: 600;
      padding: 0.5rem 1.3rem;
      border-radius: 30px;
      transition: all 0.3s ease;
      font-size: 0.92rem;
      position: relative;
      text-decoration: none;
    }

    .navbar-custom .nav-link::after {
      content: '';
      position: absolute;
      bottom: 2px;
      left: 50%;
      width: 0;
      height: 3px;
      background: linear-gradient(90deg, transparent, #d4af37, #ff6f00, transparent);
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      transform: translateX(-50%);
      border-radius: 2px;
    }

    .navbar-custom .nav-link:hover::after,
    .navbar-custom .nav-link.active::after {
      width: 70%;
    }

    .navbar-custom .nav-link:hover, 
    .navbar-custom .nav-link.active {
      color: #ff6f00 !important;
      background: rgba(255, 111, 0, 0.05);
    }

    /* Wrapper to contain the absolute positioned falling coins */
    .ehundi-wrapper {
      position: relative;
      display: inline-block;
      vertical-align: middle;
    }

    /* The e-Hundi Button */
    .navbar-custom .nav-link-ehundi {
      position: relative;
      display: inline-flex !important;
      align-items: center;
      gap: 6px;
      padding: 0.5rem 1.4rem !important;
      border-radius: 30px !important;
      font-weight: 700 !important;
      color: #ffffff !important;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
      background: transparent !important;
      border: none !important;
      overflow: hidden;
      z-index: 1;
      box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4), inset 0 1px 1px rgba(255, 255, 255, 0.3);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Glowing running lights border via rotating conic gradient */
    .navbar-custom .nav-link-ehundi::before {
      content: '';
      position: absolute;
      top: -150%;
      left: -150%;
      width: 400%;
      height: 400%;
      background: conic-gradient(
        from 0deg,
        #ffd700 0deg,
        #ff9f00 60deg,
        #ff3c00 120deg,
        #ffd700 180deg,
        #ff9f00 240deg,
        #ff3c00 300deg,
        #ffd700 360deg
      );
      animation: rotateLight 3s linear infinite;
      z-index: -2;
    }

    /* Inner background to mask the gradient and leave a thin border */
    .navbar-custom .nav-link-ehundi::after {
      content: '';
      position: absolute;
      inset: 2.5px;
      background: linear-gradient(135deg, #ffd700 0%, #b8863a 100%);
      border-radius: 28px;
      z-index: -1;
      transition: all 0.3s ease;
    }

    .navbar-custom .nav-link-ehundi:hover {
      transform: scale(1.05) translateY(-1px);
      box-shadow: 0 8px 25px rgba(255, 215, 0, 0.6);
    }

    .navbar-custom .nav-link-ehundi:hover::after {
      background: linear-gradient(135deg, #ffe55e 0%, #d4af37 100%);
    }

    @keyframes rotateLight {
      0% {
        transform: rotate(0deg);
      }
      100% {
        transform: rotate(360deg);
      }
    }

    /* Coins falling container */
    .ehundi-coins-container {
      position: absolute;
      top: 100%;
      left: 0;
      width: 100%;
      height: 300px;
      pointer-events: none;
      overflow: visible;
      z-index: 9999;
    }

    /* Individual falling coin */
    .ehundi-coin {
      position: absolute;
      pointer-events: none;
      font-family: Arial, sans-serif;
      user-select: none;
      z-index: 1000;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
      opacity: 0;
    }

    @keyframes paisaFall {
      0% {
        transform: translateY(0) rotate(var(--rotate-start)) scale(0.5);
        opacity: 0;
      }
      15% {
        opacity: 1;
        transform: translateY(10px) rotate(calc(var(--rotate-start) + 30deg)) scale(1.1);
      }
      80% {
        opacity: 1;
      }
      100% {
        transform: translateY(180px) rotate(var(--rotate-end)) scale(0.7);
        opacity: 0;
      }
    }

    @media (max-width: 991.98px) {
      .navbar-custom {
        border-radius: 20px;
        margin: 10px 15px;
        padding: 0.8rem 1.2rem;
      }
      .navbar-custom .navbar-nav {
        background: rgba(253, 251, 247, 0.98);
        padding: 1.5rem;
        border-radius: 20px;
        margin-top: 10px;
        border: 1px solid rgba(184, 134, 58, 0.15);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      }
      .navbar-custom .nav-link {
        width: 100%;
        text-align: center;
        margin: 4px 0;
      }
      .navbar-custom .nav-link::after {
        display: none;
      }
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
      text-decoration: none;
      display: inline-block;
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
      text-decoration: none;
      display: inline-block;
    }

    .btn-saffron:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(255, 111, 0, 0.45);
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
      text-decoration: none;
      display: inline-block;
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
      text-decoration: none;
      display: inline-block;
    }

    .btn-outline-saffron:hover {
      background: var(--saffron-gradient);
      color: #fff !important;
      border-color: transparent;
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(255, 111, 0, 0.3);
    }

</style>
</head>
<body>

  <!-- Back Button -->
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
          <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#features">Features</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#poojas">Book Pooja</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#donations">Donations</a></li>
          <li class="nav-item ms-lg-2">
            <div class="ehundi-wrapper">
              <a class="nav-link nav-link-ehundi active" href="{{ route('ehundi.show') }}">
                🪔 e-Hundi
              </a>
            </div>
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
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
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


  <!-- Background layers -->
  <div class="bg-layer"></div>
  <div class="bg-pattern"></div>
  <canvas id="particle-canvas"></canvas>

  <!-- Floating currency animation layer -->
  <div id="currency-layer"></div>

  <!-- Success overlay -->
  <div id="success-overlay">
    <canvas id="celebration-canvas"></canvas>
    <div class="success-icon">✅</div>
    <div class="success-title">Thank You for Your Offering</div>
    <div class="success-sub">🙏 May the Lord Bless You</div>
    <button class="success-close" onclick="closeSuccess()">🔔 &nbsp;CLOSE</button>
  </div>

  <!-- Main page -->
  <div class="page-wrap">

    <!-- Header -->
    <div class="temple-header">
      <div class="temple-icon">🛕</div>
      <h1>Temple e-Hundi</h1>
      <p class="subtitle">Offer your humble contribution to the Lord. Every donation supports temple maintenance and charitable activities.</p>
      <div class="divider-om">✦ &nbsp; ॐ &nbsp; ✦</div>
    </div>

    <!-- Main 2-col grid -->
    <div class="main-grid">

      <!-- LEFT: Donation card -->
      <div class="donation-card">
        <div class="card-title">🌸 Make an Offering</div>

        <div class="amount-wrap">
          <span class="currency-symbol">₹</span>
          <input type="number" id="donationAmount" placeholder="Enter donation amount" min="1" step="0.01" />
        </div>

        <div class="quick-label">Quick Select</div>
        <div class="quick-amounts" id="quickAmounts">
          <button class="quick-btn" data-val="10">₹10</button>
          <button class="quick-btn" data-val="20">₹20</button>
          <button class="quick-btn" data-val="50">₹50</button>
          <button class="quick-btn" data-val="100">₹100</button>
          <button class="quick-btn" data-val="500">₹500</button>
          <button class="quick-btn" data-val="1000">₹1000</button>
          <button class="quick-btn" data-val="2000">₹2000</button>
          <button class="quick-btn" data-val="5000">₹5000</button>
        </div>

        <button class="donate-btn" id="donateBtn" onclick="handleDonate(event)">
          🪔 &nbsp; DONATE NOW &nbsp; 🪔
        </button>
      </div>

      <!-- RIGHT: Hundi column -->
      <div class="hundi-col">
        <!-- Traditional Temple Hundi - Redesigned -->
        <div class="hundi-wrap" id="hundiWrap">
          <div class="hundi-glow" id="hundiGlow"></div>
          <div id="sparkle-layer"></div>
          <!-- SVG Traditional Temple Hundi -->
          <svg id="hundiSvg" viewBox="0 0 220 300" xmlns="http://www.w3.org/2000/svg" width="220" height="300">
            <defs>
              <!-- Brass gradients -->
              <linearGradient id="hundiBrass" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%"   stop-color="#E8C84A"/>
                <stop offset="25%"  stop-color="#D4AF37"/>
                <stop offset="50%"  stop-color="#C9A227"/>
                <stop offset="75%"  stop-color="#8B6914"/>
                <stop offset="100%" stop-color="#6B4F0A"/>
              </linearGradient>
              <linearGradient id="hundiBody" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%"   stop-color="#7A5510"/>
                <stop offset="20%"  stop-color="#D4AF37"/>
                <stop offset="40%"  stop-color="#F0D060"/>
                <stop offset="60%"  stop-color="#C9A227"/>
                <stop offset="80%"  stop-color="#E8C84A"/>
                <stop offset="100%" stop-color="#7A5510"/>
              </linearGradient>
              <linearGradient id="hundiLid" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%"   stop-color="#6B4F0A"/>
                <stop offset="30%"  stop-color="#C9A227"/>
                <stop offset="50%"  stop-color="#F0D060"/>
                <stop offset="70%"  stop-color="#C9A227"/>
                <stop offset="100%" stop-color="#6B4F0A"/>
              </linearGradient>
              <linearGradient id="hundiShine" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%"   stop-color="rgba(255,255,220,0.55)"/>
                <stop offset="40%"  stop-color="rgba(255,255,220,0.08)"/>
                <stop offset="100%" stop-color="rgba(0,0,0,0)"/>
              </linearGradient>
              <filter id="hundiShadow" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="8" stdDeviation="12" flood-color="rgba(0,0,0,0.7)"/>
              </filter>
            </defs>

            <!-- Ground shadow -->
            <ellipse cx="110" cy="292" rx="70" ry="8" fill="rgba(0,0,0,0.5)"/>

            <!-- Base platform (temple style) -->
            <rect x="45" y="260" width="130" height="24" rx="4" fill="url(#hundiBrass)" filter="url(#hundiShadow)"/>
            <rect x="50" y="264" width="120" height="3" rx="1.5" fill="rgba(255,240,160,0.3)"/>
            <rect x="50" y="276" width="120" height="3" rx="1.5" fill="rgba(0,0,0,0.2)"/>
            <!-- Platform decorative dots -->
            <circle cx="60" cy="272" r="2" fill="rgba(255,240,160,0.25)"/>
            <circle cx="80" cy="272" r="2" fill="rgba(255,240,160,0.25)"/>
            <circle cx="100" cy="272" r="2" fill="rgba(255,240,160,0.25)"/>
            <circle cx="120" cy="272" r="2" fill="rgba(255,240,160,0.25)"/>
            <circle cx="140" cy="272" r="2" fill="rgba(255,240,160,0.25)"/>
            <circle cx="160" cy="272" r="2" fill="rgba(255,240,160,0.25)"/>

            <!-- Main Hundi body - traditional rounded pot shape -->
            <path d="M40 258 Q30 200 28 160 Q26 110 45 80 Q65 52 110 50 Q155 52 175 80 Q194 110 192 160 Q190 200 180 258 Z"
                  fill="url(#hundiBody)" filter="url(#hundiShadow)"/>

            <!-- Body left highlight -->
            <path d="M42 258 Q32 200 30 155 Q28 110 48 82 Q60 62 85 54 Q75 80 75 130 Q75 180 75 258 Z"
                  fill="rgba(255,250,200,0.15)"/>

            <!-- Body right highlight -->
            <path d="M178 258 Q188 200 190 155 Q192 110 172 82 Q160 62 135 54 Q145 80 145 130 Q145 180 145 258 Z"
                  fill="rgba(255,250,200,0.08)"/>

            <!-- Top decorative band -->
            <path d="M55 100 Q110 92 165 100" stroke="rgba(120,80,10,0.5)" stroke-width="3" fill="none"/>
            <path d="M55 100 Q110 92 165 100" stroke="rgba(255,240,160,0.25)" stroke-width="1.5" fill="none"/>
            <!-- Small diamonds on band -->
            <polygon points="65,98 70,92 75,98 70,104" fill="rgba(180,130,20,0.4)"/>
            <polygon points="85,96 90,90 95,96 90,102" fill="rgba(180,130,20,0.4)"/>
            <polygon points="105,95 110,89 115,95 110,101" fill="rgba(180,130,20,0.4)"/>
            <polygon points="125,96 130,90 135,96 130,102" fill="rgba(180,130,20,0.4)"/>
            <polygon points="145,98 150,92 155,98 150,104" fill="rgba(180,130,20,0.4)"/>

            <!-- Center decorative band - Lotus petals -->
            <path d="M45 165 Q110 152 175 165 Q110 178 45 165 Z" fill="rgba(180,130,20,0.3)"/>
            <!-- Lotus petal pattern -->
            <ellipse cx="110" cy="165" rx="16" ry="8" fill="none" stroke="rgba(255,240,140,0.35)" stroke-width="1.5"/>
            <ellipse cx="86" cy="165" rx="10" ry="6" fill="none" stroke="rgba(255,240,140,0.25)" stroke-width="1"/>
            <ellipse cx="134" cy="165" rx="10" ry="6" fill="none" stroke="rgba(255,240,140,0.25)" stroke-width="1"/>
            <ellipse cx="62" cy="165" rx="8" ry="5" fill="none" stroke="rgba(255,240,140,0.2)" stroke-width="1"/>
            <ellipse cx="158" cy="165" rx="8" ry="5" fill="none" stroke="rgba(255,240,140,0.2)" stroke-width="1"/>
            <!-- Small decorative dots around lotus -->
            <circle cx="72" cy="158" r="1.5" fill="rgba(255,240,140,0.3)"/>
            <circle cx="148" cy="158" r="1.5" fill="rgba(255,240,140,0.3)"/>
            <circle cx="72" cy="172" r="1.5" fill="rgba(255,240,140,0.3)"/>
            <circle cx="148" cy="172" r="1.5" fill="rgba(255,240,140,0.3)"/>

            <!-- Lower decorative band -->
            <path d="M50 215 Q110 206 170 215 Q110 224 50 215 Z" fill="rgba(180,130,20,0.2)"/>
            <path d="M53 215 Q110 208 167 215" stroke="rgba(255,240,160,0.2)" stroke-width="1" fill="none"/>

            <!-- Neck / shoulder -->
            <path d="M65 70 Q110 60 155 70 L158 82 Q110 74 62 82 Z" fill="url(#hundiBrass)"/>
            <path d="M65 72 Q110 64 155 72" stroke="rgba(255,245,180,0.4)" stroke-width="2" fill="none"/>
            <!-- Neck ring -->
            <ellipse cx="110" cy="78" rx="48" ry="6" fill="none" stroke="rgba(255,240,160,0.2)" stroke-width="1.5"/>

            <!-- Lid - flat top -->
            <ellipse cx="110" cy="56" rx="44" ry="15" fill="url(#hundiLid)"/>
            <ellipse cx="110" cy="56" rx="40" ry="11" fill="rgba(255,250,200,0.1)"/>

            <!-- Lid - dome -->
            <path d="M66 56 Q68 26 110 20 Q152 26 154 56 Z" fill="url(#hundiBrass)"/>
            <!-- Dome highlight -->
            <path d="M80 50 Q98 25 125 32" stroke="rgba(255,250,200,0.3)" stroke-width="2.5" fill="none"/>
            <!-- Dome knob / finial -->
            <ellipse cx="110" cy="20" rx="12" ry="8" fill="url(#hundiLid)"/>
            <ellipse cx="108" cy="18" rx="6" ry="4" fill="rgba(255,250,200,0.4)"/>
            <!-- Knob top -->
            <circle cx="110" cy="14" r="4" fill="url(#hundiBrass)"/>
            <circle cx="109" cy="13" r="2" fill="rgba(255,250,200,0.3)"/>

            <!-- Coin slot on lid -->
            <rect x="97" y="18" width="26" height="6" rx="3" fill="#2A1500"/>
            <rect x="98" y="19" width="24" height="4" rx="2" fill="#1A0800"/>
            <!-- Slot glow -->
            <rect id="slotGlowRect" x="97" y="18" width="26" height="6" rx="3"
                  fill="rgba(255,200,30,0)" style="transition: fill 0.3s;"/>

            <!-- Overall shine overlay -->
            <path d="M40 258 Q30 200 28 160 Q26 110 45 80 Q65 52 110 50 Q155 52 175 80 Q194 110 192 160 Q190 200 180 258 Z"
                  fill="url(#hundiShine)" style="pointer-events:none;"/>
          </svg>
        </div>
      </div>
    </div>

    <div class="footer-bar">
      ✦ &nbsp; Secure Donations &nbsp;•&nbsp; 100% goes to temple &nbsp;•&nbsp; Blessed by the Lord &nbsp; ✦
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    /* ══════════════════════════════════════════════════════
       SOUND HOOKS
    ══════════════════════════════════════════════════════ */
    const SFX = {
      bell: () => {},
      coin: () => {},
      chant: () => {},
      success: () => {}
    };

    /* ══════════════════════════════════════════════════════
       PARTICLE CANVAS
    ══════════════════════════════════════════════════════ */
    (function initParticles() {
      const canvas = document.getElementById('particle-canvas');
      const ctx = canvas.getContext('2d');
      let W, H, particles = [];

      function resize() {
        W = canvas.width = window.innerWidth;
        H = canvas.height = window.innerHeight;
      }
      resize();
      window.addEventListener('resize', resize);

      const COLORS = ['rgba(212,175,55,', 'rgba(240,208,96,', 'rgba(200,153,10,'];
      for (let i = 0; i < 55; i++) {
        particles.push({
          x: Math.random() * 1000,
          y: Math.random() * 800,
          r: Math.random() * 3 + 1,
          alpha: Math.random() * 0.35 + 0.05,
          speed: Math.random() * 0.3 + 0.1,
          drift: (Math.random() - 0.5) * 0.3,
          color: COLORS[Math.floor(Math.random() * COLORS.length)],
          type: Math.random() > 0.6 ? 'petal' : 'spark',
          angle: Math.random() * Math.PI * 2,
          rot: (Math.random() - 0.5) * 0.02
        });
      }

      function draw() {
        ctx.clearRect(0, 0, W, H);
        particles.forEach(p => {
          p.y -= p.speed;
          p.x += p.drift;
          p.angle += p.rot;
          if (p.y < -10) { p.y = H + 10;
            p.x = Math.random() * W; }
          if (p.x < -10) p.x = W + 10;
          if (p.x > W + 10) p.x = -10;

          ctx.save();
          ctx.translate(p.x % W, p.y);
          ctx.rotate(p.angle);
          ctx.globalAlpha = p.alpha;

          if (p.type === 'petal') {
            ctx.fillStyle = p.color + '1)';
            ctx.beginPath();
            ctx.ellipse(0, 0, p.r * 2.5, p.r, 0, 0, Math.PI * 2);
            ctx.fill();
          } else {
            ctx.fillStyle = p.color + '1)';
            ctx.beginPath();
            ctx.arc(0, 0, p.r * 0.7, 0, Math.PI * 2);
            ctx.fill();
          }
          ctx.restore();
        });
        requestAnimationFrame(draw);
      }
      draw();
    })();

    /* ══════════════════════════════════════════════════════
       QUICK AMOUNT BUTTONS
    ══════════════════════════════════════════════════════ */
    document.querySelectorAll('.quick-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.quick-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('donationAmount').value = btn.dataset.val;
      });
    });
    document.getElementById('donationAmount').addEventListener('input', () => {
      document.querySelectorAll('.quick-btn').forEach(b => b.classList.remove('active'));
    });

    /* ══════════════════════════════════════════════════════
       RIPPLE EFFECT
    ══════════════════════════════════════════════════════ */
    function addRipple(btn, e) {
      const r = document.createElement('span');
      r.className = 'ripple-effect';
      const size = Math.max(btn.offsetWidth, btn.offsetHeight);
      const rect = btn.getBoundingClientRect();
      r.style.cssText =
        `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px`;
      btn.appendChild(r);
      r.addEventListener('animationend', () => r.remove());
    }

    /* ══════════════════════════════════════════════════════
       CURRENCY DECOMPOSITION
    ══════════════════════════════════════════════════════ */
    const DENOMS = [
      { val: 500, type: 'note' },
      { val: 200, type: 'note' },
      { val: 100, type: 'note' },
      { val: 50, type: 'note' },
      { val: 20, type: 'note' },
      { val: 10, type: 'note' },
      { val: 5, type: 'coin' },
      { val: 2, type: 'coin' },
      { val: 1, type: 'coin' },
      { val: 0.5, type: 'coin' },
      { val: 0.25, type: 'coin' },
    ];

    function decompose(amount) {
      let pieces = [];
      let rem = Math.round(amount * 100);
      const denomPaise = [50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50, 25];
      denomPaise.forEach((dp, i) => {
        const count = Math.floor(rem / dp);
        if (count > 0) {
          for (let c = 0; c < count; c++) {
            pieces.push(DENOMS[i]);
          }
          rem -= count * dp;
        }
      });
      return pieces;
    }

    /* ══════════════════════════════════════════════════════
       INDIAN NOTE SVG
    ══════════════════════════════════════════════════════ */
    const NOTE_COLORS = {
      500: { bg: '#8B5A2B', accent: '#A87A40', band: '#6B441E', text: '#FFF8E0', emblem: '#D4AF37' },
      200: { bg: '#B87333', accent: '#D4A06A', band: '#9B5E2A', text: '#FFF0D8', emblem: '#C07820' },
      100: { bg: '#4A7C59', accent: '#5E9470', band: '#3A6647', text: '#E8FFE8', emblem: '#C5D96B' },
      50: { bg: '#9B7DB2', accent: '#B090C8', band: '#7D6090', text: '#F5E8FF', emblem: '#D4B0E0' },
      20: { bg: '#D4AF37', accent: '#E8C84A', band: '#B89020', text: '#2A1500', emblem: '#6B4500' },
      10: { bg: '#4A6B8C', accent: '#6080A0', band: '#385575', text: '#E8F4FF', emblem: '#90C0D4' },
    };

    function makeNote(denom) {
      const c = NOTE_COLORS[denom] || NOTE_COLORS[100];
      const W = 140,
        H = 64;
      const id = 'n' + Math.random().toString(36).slice(2);
      return `<svg class="note-svg" viewBox="0 0 ${W} ${H}" width="${W}" height="${H}" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="${id}bg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="${c.bg}"/>
            <stop offset="50%" stop-color="${c.accent}"/>
            <stop offset="100%" stop-color="${c.band}"/>
          </linearGradient>
          <linearGradient id="${id}sh" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stop-color="rgba(255,255,255,0.22)"/>
            <stop offset="100%" stop-color="rgba(0,0,0,0.1)"/>
          </linearGradient>
        </defs>
        <rect width="${W}" height="${H}" rx="4" fill="url(#${id}bg)"/>
        <rect width="${W}" height="${H}" rx="4" fill="url(#${id}sh)"/>
        <rect x="0" y="0" width="14" height="${H}" rx="3" fill="${c.band}" opacity="0.7"/>
        <rect x="${W-14}" y="0" width="14" height="${H}" rx="3" fill="${c.band}" opacity="0.7"/>
        <circle cx="7" cy="${H/2}" r="5" fill="none" stroke="${c.emblem}" stroke-width="1"/>
        <circle cx="7" cy="${H/2}" r="2" fill="${c.emblem}" opacity="0.6"/>
        <line x1="18" y1="12" x2="${W-18}" y2="12" stroke="${c.emblem}" stroke-width="0.5" opacity="0.4"/>
        <line x1="18" y1="${H-12}" x2="${W-18}" y2="${H-12}" stroke="${c.emblem}" stroke-width="0.5" opacity="0.4"/>
        <text x="${W/2}" y="${H/2+1}" text-anchor="middle" dominant-baseline="middle"
              font-family="'Cinzel',serif" font-size="20" font-weight="bold"
              fill="${c.text}" letter-spacing="1">₹${denom}</text>
        <text x="${W/2}" y="${H/2+15}" text-anchor="middle"
              font-family="sans-serif" font-size="5.5" fill="${c.text}" opacity="0.6">भारत सरकार</text>
        <text x="${W-7}" y="${H/2+1}" text-anchor="middle" dominant-baseline="middle"
              font-family="'Cinzel',serif" font-size="8" fill="${c.emblem}" opacity="0.5">₹${denom}</text>
        <rect x="18" y="2" width="${W-36}" height="18" rx="2" fill="rgba(255,255,255,0.08)"/>
      </svg>`;
    }

    /* ══════════════════════════════════════════════════════
       INDIAN COIN SVG
    ══════════════════════════════════════════════════════ */
    function makeCoin(denom) {
      const labelMap = { 5: '₹5', 2: '₹2', 1: '₹1', 0.5: '50p', 0.25: '25p' };
      const label = labelMap[denom] || ('₹' + denom);
      const id = 'c' + Math.random().toString(36).slice(2);
      const R = 30;
      return `<svg class="coin-svg" viewBox="0 0 ${R*2} ${R*2}" width="${R*2}" height="${R*2}" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <radialGradient id="${id}cg" cx="38%" cy="30%" r="65%">
            <stop offset="0%"   stop-color="#F0D060"/>
            <stop offset="40%"  stop-color="#C9A227"/>
            <stop offset="75%"  stop-color="#8B6914"/>
            <stop offset="100%" stop-color="#5A4008"/>
          </radialGradient>
          <radialGradient id="${id}rim" cx="50%" cy="50%" r="50%">
            <stop offset="80%"  stop-color="transparent"/>
            <stop offset="100%" stop-color="rgba(0,0,0,0.4)"/>
          </radialGradient>
        </defs>
        <circle cx="${R}" cy="${R}" r="${R-1}" fill="url(#${id}cg)"/>
        <circle cx="${R}" cy="${R}" r="${R-1}" fill="url(#${id}rim)"/>
        <circle cx="${R}" cy="${R}" r="${R-4}" fill="none" stroke="rgba(255,240,160,0.3)" stroke-width="1"/>
        <circle cx="${R}" cy="${R}" r="5" fill="none" stroke="rgba(255,230,100,0.5)" stroke-width="1"/>
        <text x="${R}" y="${R+4}" text-anchor="middle"
              font-family="'Cinzel',serif" font-size="${denom < 1 ? 8 : 9}" font-weight="bold"
              fill="rgba(60,30,0,0.9)">${label}</text>
        <ellipse cx="${R*0.6}" cy="${R*0.45}" rx="${R*0.35}" ry="${R*0.18}" fill="rgba(255,255,220,0.35)" transform="rotate(-30,${R},${R})"/>
      </svg>`;
    }

    /* ══════════════════════════════════════════════════════
       HUNDI POSITION HELPER
    ══════════════════════════════════════════════════════ */
    function getSlotPosition() {
      const hundi = document.getElementById('hundiWrap');
      const rect = hundi.getBoundingClientRect();
      const slotY = rect.top + (18 / 300) * rect.height;
      const slotX = rect.left + rect.width * 0.5;
      return { x: slotX, y: slotY };
    }

    /* ══════════════════════════════════════════════════════
       SPARKLE EMITTER
    ══════════════════════════════════════════════════════ */
    function emitSparkles() {
      const layer = document.getElementById('sparkle-layer');
      for (let i = 0; i < 14; i++) {
        const sp = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        const angle = Math.random() * Math.PI * 2;
        const dist = Math.random() * 60 + 20;
        const size = Math.random() * 3 + 1;
        sp.setAttribute('cx', 110);
        sp.setAttribute('cy', 18);
        sp.setAttribute('r', size);
        sp.setAttribute('fill', '#F0D060');
        sp.setAttribute('opacity', '0.9');
        layer.appendChild(sp);

        const tx = Math.cos(angle) * dist;
        const ty = Math.sin(angle) * dist - 20;
        sp.animate([
          { transform: 'translate(0,0)', opacity: 0.9 },
          { transform: `translate(${tx}px,${ty}px)`, opacity: 0, offset: 1 }
        ], { duration: 500 + Math.random() * 400, easing: 'ease-out', fill: 'forwards' })
          .finished.then(() => sp.remove());
      }
    }

    /* ══════════════════════════════════════════════════════
       HUNDI REACTION
    ══════════════════════════════════════════════════════ */
    function triggerHundiReaction() {
      const wrap = document.getElementById('hundiWrap');
      const glow = document.getElementById('hundiGlow');
      const slot = document.getElementById('slotGlowRect');

      wrap.classList.remove('shake');
      void wrap.offsetWidth;
      wrap.classList.add('shake');

      glow.classList.add('lit');
      slot.setAttribute('fill', 'rgba(255,220,50,0.7)');

      emitSparkles();
      SFX.coin();

      setTimeout(() => {
        glow.classList.remove('lit');
        slot.setAttribute('fill', 'rgba(255,220,50,0)');
      }, 700);
    }

    /* ══════════════════════════════════════════════════════
       ANIMATE ONE CURRENCY PIECE
    ══════════════════════════════════════════════════════ */
    function animateCurrencyItem(piece, startX, startY, destX, destY, delay, isLastBatch, onAllDone) {
      return new Promise(resolve => {
        setTimeout(() => {
          const layer = document.getElementById('currency-layer');
          const wrap = document.createElement('div');
          wrap.className = 'currency-item';

          const isCoin = piece.type === 'coin';
          wrap.innerHTML = isCoin ? makeCoin(piece.val) : makeNote(piece.val);

          const scatterX = startX + (Math.random() - 0.5) * 80;
          const scatterY = startY - 60 - Math.random() * 40;
          const rotation = (Math.random() - 0.5) * 25;

          wrap.style.cssText = `
            left: ${scatterX}px;
            top:  ${scatterY}px;
            transform: rotate(${rotation}deg) scale(0);
            opacity: 0;
          `;
          layer.appendChild(wrap);

          wrap.animate([
            { transform: `rotate(${rotation}deg) scale(0)`, opacity: 0 },
            { transform: `rotate(${rotation}deg) scale(1)`, opacity: 1 }
          ], { duration: 200, fill: 'forwards', easing: 'cubic-bezier(0.175,0.885,0.32,1.275)' })
            .finished.then(() => {
              const travelTime = 550 + Math.random() * 150;
              const dx = destX - scatterX;
              const dy = destY - scatterY;
              const bendRot = isCoin ? rotation + 360 : rotation + (Math.random() - 0.5) * 15;

              wrap.animate([
                { transform: `translate(0,0) rotate(${rotation}deg)`, opacity: 1 },
                { transform: `translate(${dx*0.4}px,${dy*0.4}px) rotate(${rotation + bendRot * 0.3}deg)`, opacity: 1,
                  offset: 0.4 },
                { transform: `translate(${dx}px,${dy}px) rotate(${bendRot}deg) scale(0.3)`, opacity: 0.3 }
              ], { duration: travelTime, fill: 'forwards', easing: 'ease-in' })
                .finished.then(() => {
                  triggerHundiReaction();
                  wrap.remove();
                  resolve();
                  if (isLastBatch) onAllDone();
                });
            });
        }, delay);
      });
    }

    /* ══════════════════════════════════════════════════════
       OFFERINGS COUNTER ANIMATION
    ══════════════════════════════════════════════════════ */
    function animateCounter(toAdd) {
      const el = document.getElementById('offeringsAmount');
      if (!el) return;
      // This is disabled for devotees/guests as per design request to not show total amount.
    }

    /* ══════════════════════════════════════════════════════
       CELEBRATION CANVAS
    ══════════════════════════════════════════════════════ */
    function runCelebration() {
      const canvas = document.getElementById('celebration-canvas');
      const ctx = canvas.getContext('2d');
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;

      const items = [];
      const TYPES = ['petal', 'bell', 'spark', 'diya'];
      const GOLD = ['#D4AF37', '#F0D060', '#E8C84A', '#FFD700', '#C9A227'];

      for (let i = 0; i < 80; i++) {
        items.push({
          x: Math.random() * canvas.width,
          y: Math.random() * canvas.height * 0.3 - 20,
          vx: (Math.random() - 0.5) * 1.5,
          vy: Math.random() * 1.2 + 0.4,
          r: Math.random() * 6 + 3,
          alpha: Math.random() * 0.7 + 0.3,
          color: GOLD[Math.floor(Math.random() * GOLD.length)],
          type: TYPES[Math.floor(Math.random() * TYPES.length)],
          angle: Math.random() * Math.PI * 2,
          rot: (Math.random() - 0.5) * 0.08
        });
      }

      let frame = 0;

      function draw() {
        if (frame++ > 180) { ctx.clearRect(0, 0, canvas.width, canvas.height); return; }
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        items.forEach(p => {
          p.x += p.vx;
          p.y += p.vy;
          p.angle += p.rot;
          p.alpha -= 0.003;
          if (p.alpha <= 0) return;

          ctx.save();
          ctx.translate(p.x, p.y);
          ctx.rotate(p.angle);
          ctx.globalAlpha = p.alpha;

          if (p.type === 'petal') {
            ctx.fillStyle = p.color;
            ctx.beginPath();
            ctx.ellipse(0, 0, p.r * 2.5, p.r, 0, 0, Math.PI * 2);
            ctx.fill();
          } else if (p.type === 'spark') {
            ctx.fillStyle = p.color;
            ctx.beginPath();
            ctx.arc(0, 0, p.r * 0.6, 0, Math.PI * 2);
            ctx.fill();
            for (let s = 0; s < 4; s++) {
              ctx.save();
              ctx.rotate(s * Math.PI / 4);
              ctx.beginPath();
              ctx.moveTo(0, -p.r);
              ctx.lineTo(0.5, -p.r * 0.3);
              ctx.lineTo(0, 0);
              ctx.closePath();
              ctx.fill();
              ctx.restore();
            }
          } else if (p.type === 'bell') {
            ctx.font = `${p.r * 2.5}px serif`;
            ctx.textAlign = 'center';
            ctx.fillText('🔔', 0, 0);
          } else if (p.type === 'diya') {
            ctx.font = `${p.r * 2.5}px serif`;
            ctx.textAlign = 'center';
            ctx.fillText('🪔', 0, 0);
          }
          ctx.restore();
        });
        requestAnimationFrame(draw);
      }
      draw();
    }

    /* ══════════════════════════════════════════════════════
       SUCCESS OVERLAY
    ══════════════════════════════════════════════════════ */
    function showSuccess() {
      const overlay = document.getElementById('success-overlay');
      overlay.classList.add('show');
      runCelebration();
      SFX.success();
      SFX.bell();
    }

    function closeSuccess() {
      document.getElementById('success-overlay').classList.remove('show');
    }

    /* ══════════════════════════════════════════════════════
       MAIN DONATE HANDLER
    ══════════════════════════════════════════════════════ */
    async function handleDonate(e) {
      addRipple(document.getElementById('donateBtn'), e);

      const rawVal = parseFloat(document.getElementById('donationAmount').value);
      if (!rawVal || rawVal <= 0) {
        document.getElementById('donationAmount').focus();
        document.getElementById('donationAmount').style.borderColor = 'rgba(255,80,80,0.7)';
        setTimeout(() => {
          document.getElementById('donationAmount').style.borderColor = '';
        }, 1200);
        return;
      }

      const btn = document.getElementById('donateBtn');
      btn.disabled = true;
      btn.textContent = '🙏  Offering...';

      // Send Fetch request to Laravel backend
      const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      try {
        const response = await fetch('{{ route("ehundi.offer") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({ amount: rawVal })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Failed to record donation');
        }
      } catch (error) {
        alert(error.message || 'An error occurred while placing your offering. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '🪔 &nbsp; DONATE NOW &nbsp; 🪔';
        return;
      }

      const pieces = decompose(rawVal);
      const slotPos = getSlotPosition();
      const startX = window.innerWidth * 0.5;
      const startY = window.innerHeight * 0.35;

      const MAX_ANIMATED = 18;
      const animPieces = pieces.slice(0, MAX_ANIMATED);
      const total = animPieces.length;

      const allDone = () => {
        setTimeout(() => {
          showSuccess();
          btn.disabled = false;
          btn.innerHTML = '🪔 &nbsp; DONATE NOW &nbsp; 🪔';
          document.getElementById('donationAmount').value = '';
          document.querySelectorAll('.quick-btn').forEach(b => b.classList.remove('active'));
        }, 400);
      };

      animPieces.forEach((piece, i) => {
        const isLast = (i === total - 1);
        animateCurrencyItem(
          piece,
          startX, startY,
          slotPos.x, slotPos.y,
          i * 260,
          isLast,
          allDone
        );
      });

      if (total === 0) {
        setTimeout(allDone, 300);
      }

      SFX.chant();
    }

    // ----- Navbar Scrolling Effect -----
    window.addEventListener('scroll', function() {
      const navbar = document.getElementById('mainNavbar');
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });

    // e-Hundi falling coins animation
    document.addEventListener("DOMContentLoaded", function() {
      const ehundiBtns = document.querySelectorAll('.nav-link-ehundi');
      ehundiBtns.forEach(btn => {
        const wrapper = btn.closest('.ehundi-wrapper');
        if (!wrapper) return;
        
        // Create a container for falling coins
        const coinsContainer = document.createElement('div');
        coinsContainer.className = 'ehundi-coins-container';
        wrapper.appendChild(coinsContainer);

        let activeInterval = null;

        function spawnCoin() {
          const coin = document.createElement('div');
          coin.className = 'ehundi-coin';
          
          // Randomly select coin emoji
          const coinsArray = ['🪙', '🪙', '🪙', '🪙', '🪙'];
          coin.innerHTML = coinsArray[Math.floor(Math.random() * coinsArray.length)];
          
          // Random horizontal position within the button
          const leftPos = Math.random() * btn.offsetWidth;
          coin.style.left = leftPos + 'px';
          coin.style.top = '0px';
          
          // Random size
          const size = 14 + Math.random() * 12; // 14px to 26px
          coin.style.fontSize = size + 'px';
          
          // Random rotation
          const rotateStart = Math.random() * 360;
          const rotateEnd = rotateStart + 360 + Math.random() * 720;
          coin.style.setProperty('--rotate-start', rotateStart + 'deg');
          coin.style.setProperty('--rotate-end', rotateEnd + 'deg');
          
          // Random duration
          const duration = 1.5 + Math.random() * 1.0; // 1.5s to 2.5s (softer animation for steady fall)
          coin.style.animation = `paisaFall ${duration}s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards`;

          coinsContainer.appendChild(coin);

          // Remove coin after animation ends
          setTimeout(() => {
            coin.remove();
          }, duration * 1000);
        }

        // Start constant/steady falling coins (gentle flow)
        function startSteadyCoins() {
          if (activeInterval) clearInterval(activeInterval);
          activeInterval = setInterval(spawnCoin, 900); // One coin every 900ms
        }

        // Fast fall on hover (rapid stream)
        function startFastCoins() {
          if (activeInterval) clearInterval(activeInterval);
          // Spawn immediate burst
          for (let i = 0; i < 5; i++) {
            setTimeout(spawnCoin, i * 100);
          }
          activeInterval = setInterval(spawnCoin, 200); // One coin every 200ms
        }

        // Initialize steady falling coins right away
        startSteadyCoins();

        btn.addEventListener('mouseenter', () => {
          startFastCoins();
        });

        btn.addEventListener('mouseleave', () => {
          startSteadyCoins();
        });
      });
    });
  </script>
</body>
</html>
