<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Devotee Portal')</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Google Font (Inter) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: transparent !important;
      color: #1e1e2a;
    }

    /* Dynamic background layers */
    .dashboard-bg-layer {
      position: fixed;
      inset: 0;
      z-index: -2;
      background: linear-gradient(135deg, #fdfbf7 0%, #f7f1e6 50%, #faf5eb 100%);
    }

    .dashboard-bg-pattern {
      position: fixed;
      inset: 0;
      z-index: -1;
      opacity: 0.03;
      background-image: 
        radial-gradient(circle, #b8863a 1px, transparent 1px),
        radial-gradient(circle, #b8863a 1px, transparent 1px);
      background-size: 40px 40px;
      background-position: 0 0, 20px 20px;
      animation: shiftPattern 40s linear infinite;
    }

    @keyframes shiftPattern {
      from { background-position: 0 0, 20px 20px; }
      to { background-position: 40px 40px, 60px 60px; }
    }

    .dashboard-ambient-glow {
      position: fixed;
      border-radius: 50%;
      filter: blur(120px);
      pointer-events: none;
      z-index: -1;
      opacity: 0.22;
      animation: floatGlow 25s ease-in-out infinite alternate;
    }

    .glow-1 {
      width: 450px;
      height: 450px;
      background: radial-gradient(circle, rgba(255, 111, 0, 0.4) 0%, transparent 70%);
      top: -10%;
      right: 5%;
    }

    .glow-2 {
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(184, 134, 58, 0.35) 0%, transparent 70%);
      bottom: -15%;
      left: -5%;
      animation-delay: -5s;
    }

    @keyframes floatGlow {
      0% {
        transform: translate(0, 0) scale(1);
      }
      50% {
        transform: translate(40px, -30px) scale(1.1);
      }
      100% {
        transform: translate(-30px, 40px) scale(0.95);
      }
    }

    /* ---------- SIDEBAR ---------- */
    .sidebar {
      width: 260px;
      height: 100vh;
      position: fixed;
      left: 0;
      top: 0;
      background: #ffffff;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
      overflow-y: auto;
      z-index: 1050;
      padding: 0 0 24px 0;
      border-right: 1px solid rgba(184, 134, 58, 0.08);
      transition: transform 0.25s ease;
    }
    .logo-area {
      padding: 24px 20px 20px 24px;
      display: flex;
      align-items: center;
      gap: 10px;
      border-bottom: 1px solid rgba(0,0,0,0.03);
    }
    .logo-icon {
      background: linear-gradient(135deg, #b8863a, #d4a05a);
      width: 40px;
      height: 40px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 22px;
      box-shadow: 0 6px 12px rgba(184, 134, 58, 0.2);
    }
    .logo-text {
      font-weight: 700;
      font-size: 22px;
      letter-spacing: -0.3px;
      color: #2d1f0e;
    }
    .logo-text span {
      background: linear-gradient(135deg, #b8863a, #d4a05a);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .sidebar .nav {
      padding: 16px 12px 0 12px;
    }
    .sidebar .nav-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 11px 16px;
      border-radius: 14px;
      color: #3e3e4a;
      font-weight: 500;
      font-size: 15px;
      transition: all 0.2s;
      margin-bottom: 2px;
      position: relative;
    }
    .sidebar .nav-link i {
      font-size: 1.25rem;
      width: 24px;
      text-align: center;
      color: #7b6b5a;
      transition: color 0.2s;
    }
    .sidebar .nav-link:hover {
      background: #f3ebe0;
      color: #b8863a;
    }
    .sidebar .nav-link:hover i {
      color: #b8863a;
    }
    .sidebar .nav-link.active {
      background: linear-gradient(135deg, #b8863a, #d4a05a);
      color: white;
      box-shadow: 0 6px 16px rgba(184, 134, 58, 0.25);
    }
    .sidebar .nav-link.active i {
      color: white;
    }
    .sidebar .nav-link.logout-link {
      margin-top: 20px;
      border-top: 1px solid #eeece7;
      border-radius: 0;
      padding-top: 20px;
      color: #b34a4a;
    }
    .sidebar .nav-link.logout-link i {
      color: #b34a4a;
    }
    .sidebar .nav-link.logout-link:hover {
      background: transparent;
      color: #b34a4a;
    }

    /* ---------- MAIN CONTENT ---------- */
    .main-content {
      margin-left: 260px;
      min-height: 100vh;
      transition: margin 0.25s;
    }

    /* top bar */
    .topbar {
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(8px);
      padding: 16px 32px;
      border-bottom: 1px solid rgba(0,0,0,0.02);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      position: sticky;
      top: 0;
      z-index: 1020;
    }
    .topbar h4 {
      font-weight: 600;
      font-size: 1.5rem;
      letter-spacing: -0.3px;
      color: #2d1f0e;
      margin: 0;
    }
    .topbar h4 i {
      color: #b8863a;
      margin-right: 8px;
    }
    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .profile-toggle {
      background: white;
      border: none;
      padding: 6px 16px 6px 12px;
      border-radius: 40px;
      font-weight: 500;
      box-shadow: 0 2px 8px rgba(0,0,0,0.02);
      display: flex;
      align-items: center;
      gap: 10px;
      transition: 0.2s;
    }
    .profile-toggle:hover {
      background: #f3ebe0;
    }
    .profile-toggle i {
      font-size: 1.4rem;
      color: #b8863a;
    }

    .membership-badge {
      padding: 6px 18px;
      border-radius: 40px;
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.5px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-transform: uppercase;
    }
    .membership-badge.gold {
      background: linear-gradient(135deg, #ffd700, #f5a623);
      color: #7c5c00;
      box-shadow: 0 2px 12px rgba(255, 215, 0, 0.3);
    }
    .membership-badge.silver {
      background: linear-gradient(135deg, #e8e8e8, #c0c0c0);
      color: #4a4a4a;
      box-shadow: 0 2px 12px rgba(192, 192, 192, 0.3);
    }
    .membership-badge.bronze {
      background: linear-gradient(135deg, #cd7f32, #a05a2c);
      color: #ffffff;
      box-shadow: 0 2px 12px rgba(205, 127, 50, 0.3);
    }
    .membership-badge.platinum {
      background: linear-gradient(135deg, #e5e4e2, #b8b8b8);
      color: #2d2d2d;
      box-shadow: 0 2px 12px rgba(181, 181, 181, 0.3);
      border: 1px solid #d4d4d4;
    }

    .membership-sidebar-card {
      margin: 16px 16px 0;
      padding: 16px;
      background: linear-gradient(135deg, #faf6f0, #f3ebe0);
      border-radius: 16px;
      border: 1px solid rgba(184, 134, 58, 0.1);
      text-align: center;
    }
    .membership-sidebar-card .membership-tier {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #7b6b5a;
      font-weight: 600;
    }
    .membership-sidebar-card .tier-name {
      font-size: 1.2rem;
      font-weight: 700;
      margin: 4px 0;
    }
    .membership-sidebar-card .tier-name.gold-text { color: #b8863a; }
    .membership-sidebar-card .tier-name.silver-text { color: #8a8a8a; }
    .membership-sidebar-card .tier-name.bronze-text { color: #cd7f32; }
    .membership-sidebar-card .tier-name.platinum-text { color: #6b6b6b; }
    .membership-sidebar-card .membership-benefits {
      font-size: 0.75rem;
      color: #7b6b5a;
      margin-top: 6px;
    }

    @media (max-width: 992px) {
      .sidebar {
        transform: translateX(-100%);
        width: 280px;
      }
      .sidebar.show {
        transform: translateX(0);
      }
      .main-content {
        margin-left: 0;
      }
      .topbar {
        padding: 14px 20px;
      }
    }

    .menu-toggle {
      background: white;
      border: none;
      width: 44px;
      height: 44px;
      border-radius: 40px;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }
    @media (max-width: 992px) {
      .menu-toggle {
        display: flex;
      }
    }
  </style>
  
  @yield('page-css')
</head>
<body>
  <!-- Animated background layers -->
  <div class="dashboard-bg-layer"></div>
  <div class="dashboard-bg-pattern"></div>
  <div class="dashboard-ambient-glow glow-1"></div>
  <div class="dashboard-ambient-glow glow-2"></div>

  @include('layouts.partials.notifications')

  {{-- Devotee Sidebar --}}
  @include('devotee.layouts.sidebar')

  <div class="main-content">
    {{-- Devotee Topbar --}}
    @include('devotee.layouts.topbar')

    <div class="container-fluid px-4 py-4">
      @yield('content')
    </div>
  </div>

  <!-- LOGOUT CONFIRMATION MODAL -->
  <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius:24px; border:none; box-shadow:0 24px 48px rgba(0,0,0,0.08);">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-right me-2 text-danger"></i>Confirm Logout</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body pt-3">
          <p class="mb-0" style="font-weight: 450; color: #2d1f0e;">Are you sure you want to logout?</p>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
          <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmLogoutBtn" style="background:#b34a4a; border:none;">Logout</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <script>
    $(document).ready(function() {
      // Mobile sidebar toggle
      $('#menuToggle').on('click', function(e) {
        e.stopPropagation();
        $('#sidebar').toggleClass('show');
      });

      $(document).on('click', function(e) {
        if ($(window).width() <= 992) {
          if (!$('#sidebar').is(e.target) && $('#sidebar').has(e.target).length === 0 && !$('#menuToggle').is(e.target) && $('#menuToggle').has(e.target).length === 0) {
            $('#sidebar').removeClass('show');
          }
        }
      });

      // Logout modal behavior
      const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
      
      $('#sidebarLogoutBtn, #topbarLogoutBtn').on('click', function(e) {
        e.preventDefault();
        logoutModal.show();
      });

      $('#confirmLogoutBtn').on('click', function() {
        window.location.href = '{{ route('logout') }}';
      });
    });
  </script>

  <!-- AI Chatbot Styling -->
  <style>
    /* Custom Scrollbar for Chat Messages */
    .chatbot-messages::-webkit-scrollbar,
    #staffChatMessagesArea::-webkit-scrollbar,
    .chat-sessions-list::-webkit-scrollbar {
      width: 6px;
    }
    .chatbot-messages::-webkit-scrollbar-track,
    #staffChatMessagesArea::-webkit-scrollbar-track,
    .chat-sessions-list::-webkit-scrollbar-track {
      background: #fafaf8;
    }
    .chatbot-messages::-webkit-scrollbar-thumb,
    #staffChatMessagesArea::-webkit-scrollbar-thumb,
    .chat-sessions-list::-webkit-scrollbar-thumb {
      background: #e3dad0;
      border-radius: 4px;
    }
    .chatbot-messages::-webkit-scrollbar-thumb:hover,
    #staffChatMessagesArea::-webkit-scrollbar-thumb:hover,
    .chat-sessions-list::-webkit-scrollbar-thumb:hover {
      background: #b8863a;
    }

    /* AI Chatbot Floating Toggle & Badge */
    .chatbot-container {
      position: fixed;
      bottom: 24px;
      right: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      z-index: 2000;
    }
    .chatbot-badge {
      background: linear-gradient(135deg, #2d1f0e, #1c1308);
      color: #d4a05a;
      padding: 10px 18px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 700;
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
      border: 1px solid rgba(184, 134, 58, 0.3);
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      animation: floatBadge 3s ease-in-out infinite alternate;
      transition: all 0.2s;
    }
    .chatbot-badge:hover {
      transform: scale(1.05);
      box-shadow: 0 10px 28px rgba(184, 134, 58, 0.2);
    }
    .chatbot-toggle {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, #b8863a, #d4a05a);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      box-shadow: 0 8px 24px rgba(184, 134, 58, 0.4);
      cursor: pointer;
      border: 2px solid rgba(255, 255, 255, 0.25);
      transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes floatBadge {
      0% { transform: translateY(0); }
      100% { transform: translateY(-6px); }
    }
    .chatbot-toggle:hover {
      transform: scale(1.1) rotate(15deg);
      box-shadow: 0 12px 30px rgba(184, 134, 58, 0.6);
    }
    .chatbot-window {
      position: fixed;
      bottom: 96px;
      right: 24px;
      width: 380px;
      height: 520px;
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(16px);
      border-radius: 24px;
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(184, 134, 58, 0.2);
      display: none;
      flex-direction: column;
      z-index: 2000;
      overflow: hidden;
      transition: all 0.3s ease;
    }
    @media (max-width: 576px) {
      .chatbot-window {
        width: calc(100% - 32px);
        height: calc(100vh - 140px);
        bottom: 96px;
        right: 16px;
      }
    }
    .chatbot-header {
      background: linear-gradient(135deg, #2d1f0e, #1c1308);
      padding: 14px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      color: white;
      border-bottom: 2px solid #b8863a;
    }
    .chatbot-header-info {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .chatbot-header-info i {
      font-size: 22px;
      color: #d4a05a;
      text-shadow: 0 0 10px rgba(212, 160, 90, 0.5);
    }
    .chatbot-header-title {
      font-weight: 700;
      font-size: 14px;
      margin: 0;
    }
    .chatbot-header-subtitle {
      font-size: 10px;
      color: #a89f92;
      margin: 0;
    }
    .chatbot-header-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .chatbot-btn-end {
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 12px;
      background: rgba(179, 74, 74, 0.2);
      border: 1px solid rgba(179, 74, 74, 0.4);
      color: #ff9e9e;
      cursor: pointer;
      transition: 0.2s;
      font-weight: 600;
    }
    .chatbot-btn-end:hover {
      background: rgba(179, 74, 74, 0.4);
      color: white;
    }
    .chatbot-btn-close {
      background: transparent;
      border: none;
      color: #a89f92;
      font-size: 18px;
      cursor: pointer;
      padding: 0;
      display: flex;
      align-items: center;
      transition: color 0.2s;
    }
    .chatbot-btn-close:hover {
      color: white;
    }
    .chatbot-messages {
      flex: 1;
      padding: 20px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 12px;
      background: #fafaf8;
    }
    .chatbot-msg-row {
      display: block;
      width: 100%;
      clear: both;
      margin-bottom: 4px;
    }
    .chatbot-msg-bubble {
      max-width: 85%;
      padding: 10px 14px;
      border-radius: 16px;
      font-size: 13.5px;
      line-height: 1.45;
      box-shadow: 0 2px 5px rgba(0,0,0,0.02);
      word-wrap: break-word;
      display: inline-block;
    }
    .chatbot-msg-bubble p {
      margin-bottom: 0;
    }
    .chatbot-msg-row.bot .chatbot-msg-bubble,
    .chatbot-msg-row.staff .chatbot-msg-bubble {
      background: #f1ebd9;
      color: #2d1f0e;
      border-bottom-left-radius: 4px;
      float: left;
      border-left: 3px solid #b8863a;
    }
    .chatbot-msg-row.devotee .chatbot-msg-bubble {
      background: linear-gradient(135deg, #b8863a, #d4a05a);
      color: white;
      border-bottom-right-radius: 4px;
      float: right;
    }
    .chatbot-msg-sender {
      font-size: 9.5px;
      font-weight: 700;
      margin-bottom: 3px;
      color: #7b6b5a;
      text-transform: uppercase;
    }
    .chatbot-msg-row.devotee .chatbot-msg-sender {
      text-align: right;
      color: #b8863a;
    }
    .chatbot-msg-row.staff .chatbot-msg-sender {
      color: #b34a4a;
    }
    .chatbot-options-container {
      clear: both;
      padding: 6px 0;
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      width: 100%;
      float: left;
    }
    .chatbot-option-btn {
      background: white;
      border: 1px solid rgba(184, 134, 58, 0.3);
      color: #b8863a;
      font-size: 12px;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 20px;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 2px 4px rgba(184, 134, 58, 0.05);
    }
    .chatbot-option-btn:hover {
      background: #b8863a;
      color: white;
      border-color: #b8863a;
      transform: translateY(-1px);
    }
    .chatbot-footer {
      padding: 12px 16px;
      background: white;
      border-top: 1px solid rgba(0,0,0,0.05);
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .chatbot-input {
      flex: 1;
      border: 1px solid rgba(184, 134, 58, 0.2);
      border-radius: 20px;
      padding: 8px 14px;
      font-size: 13.5px;
      outline: none;
      transition: 0.2s;
    }
    .chatbot-input:focus {
      border-color: #b8863a;
    }
    .chatbot-btn-send {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #b8863a, #d4a05a);
      color: white;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      cursor: pointer;
      box-shadow: 0 3px 8px rgba(184, 134, 58, 0.2);
      transition: all 0.2s;
    }
    .chatbot-btn-send:hover {
      transform: scale(1.05);
    }
    .chatbot-qr-card {
      background: white;
      border: 2px solid rgba(184, 134, 58, 0.2);
      border-radius: 16px;
      padding: 12px;
      margin-top: 6px;
      text-align: center;
      width: 100%;
      float: left;
      clear: both;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .chatbot-qr-image-container {
      width: 120px;
      height: 120px;
      margin: 8px auto;
      border: 3px solid #2d1f0e;
      border-radius: 8px;
      background: white;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }
  </style>

  <!-- Floating Chatbot Elements -->
  <div class="chatbot-container">
    <div class="chatbot-badge animate__animated animate__fadeInRight" id="chatbotBadge">
      <i class="bi bi-robot"></i>
      <span>Ask me for help!</span>
    </div>
    <div class="chatbot-toggle" id="chatbotToggle" title="Chat Support">
      <i class="bi bi-chat-dots-fill"></i>
    </div>
  </div>

  <div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-header">
      <div class="chatbot-header-info">
        <i class="bi bi-robot"></i>
        <div>
          <h6 class="chatbot-header-title">Shree Mandir Assistant</h6>
          <p class="chatbot-header-subtitle">Chat Support & Booking</p>
        </div>
      </div>
      <div class="chatbot-header-actions">
        <button class="chatbot-btn-end" id="chatbotBtnEnd">End Chat</button>
        <button class="chatbot-btn-close" id="chatbotBtnClose"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="chatbot-messages" id="chatbotMessages">
      <!-- Dynamically filled -->
    </div>
    
    <!-- Scroll to bottom button -->
    <button id="chatbotScrollBtn" class="chatbot-scroll-btn" style="position: absolute; bottom: 64px; right: 20px; width: 32px; height: 32px; border-radius: 50%; background: #b8863a; color: white; border: 1px solid rgba(255,255,255,0.3); display: none; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(184, 134, 58, 0.3); z-index: 100; cursor: pointer; transition: all 0.2s;">
      <i class="bi bi-chevron-double-down"></i>
    </button>

    <div class="chatbot-footer">
      <input type="text" class="chatbot-input" id="chatbotInput" placeholder="Ask something or select an option..." autocomplete="off">
      <button class="chatbot-btn-send" id="chatbotBtnSend"><i class="bi bi-send-fill"></i></button>
    </div>
  </div>

  <!-- Chatbot Logic script -->
  <script>
    $(document).ready(function() {
      const toggle = $('#chatbotToggle, #chatbotBadge');
      const win = $('#chatbotWindow');
      const closeBtn = $('#chatbotBtnClose');
      const endBtn = $('#chatbotBtnEnd');
      const sendBtn = $('#chatbotBtnSend');
      const input = $('#chatbotInput');
      const messagesContainer = $('#chatbotMessages');

      let pollInterval = null;
      let isWindowOpen = false;
      let sessionMode = 'bot';

      toggle.on('click', function() {
        isWindowOpen = !isWindowOpen;
        win.css('display', isWindowOpen ? 'flex' : 'none');
        if (isWindowOpen) {
          $('#chatbotBadge').hide();
          initChat();
        } else {
          $('#chatbotBadge').show();
        }
      });

      closeBtn.on('click', function() {
        isWindowOpen = false;
        win.css('display', 'none');
        $('#chatbotBadge').show();
      });

      messagesContainer.on('scroll', function() {
        const threshold = 150;
        const totalHeight = messagesContainer[0].scrollHeight;
        const currentScroll = messagesContainer.scrollTop() + messagesContainer.innerHeight();
        
        if (totalHeight - currentScroll > threshold) {
          $('#chatbotScrollBtn').css('display', 'flex');
        } else {
          $('#chatbotScrollBtn').hide();
        }
      });

      $('#chatbotScrollBtn').on('click', function() {
        scrollToBottom();
      });

      endBtn.on('click', function() {
        if (confirm("Are you sure you want to end this conversation? This will clear the current session.")) {
          $.post('{{ route('devotee.chat.end') }}', { _token: '{{ csrf_token() }}' }, function() {
            messagesContainer.empty();
            initChat();
          });
        }
      });

      sendBtn.on('click', function() {
        sendMessage();
      });

      input.on('keypress', function(e) {
        if (e.which === 13) {
          sendMessage();
        }
      });

      $(document).on('click', '.chatbot-option-btn', function() {
        const val = $(this).data('value');
        const label = $(this).text();
        sendMessage(val, label);
      });

      function initChat() {
        $.get('{{ route('devotee.chat.session') }}', function(res) {
          if (res.success) {
            loadMessages();
            startPolling();
          }
        });
      }

      function loadMessages() {
        $.get('{{ route('devotee.chat.messages') }}', function(res) {
          if (res.success) {
            sessionMode = res.mode;
            renderMessages(res.messages);
            scrollToBottom();
            
            // Adjust end chat text if in agent mode
            if (sessionMode === 'agent') {
              $('.chatbot-header-subtitle').text('Live Chatting with Staff');
              $('.chatbot-header-info i').removeClass('bi-robot').addClass('bi-person-badge-fill');
            } else {
              $('.chatbot-header-subtitle').text('Chat Support & Booking');
              $('.chatbot-header-info i').removeClass('bi-person-badge-fill').addClass('bi-robot');
            }
          }
        });
      }

      function renderMessages(messages) {
        messagesContainer.empty();
        messages.forEach(msg => {
          const type = msg.sender_type;
          const senderName = type === 'devotee' ? 'You' : (type === 'staff' ? 'Staff Agent' : 'Mandir Bot');
          
          let bubbleHtml = `
            <div class="chatbot-msg-row ${type}">
              <div class="chatbot-msg-sender">${senderName}</div>
              <div class="chatbot-msg-bubble">
                ${formatMessageText(msg.message_text)}
              </div>
            </div>
          `;
          messagesContainer.append(bubbleHtml);

          // Handle special layouts in metadata
          if (msg.metadata) {
            const meta = msg.metadata;
            
            // QR Code
            if (meta.payment_qr) {
              let qrHtml = `
                <div class="chatbot-qr-card animate__animated animate__fadeIn">
                  <div class="fw-bold text-dark mb-1">Scan & Pay via UPI</div>
                  <div class="small text-danger mb-2 fw-semibold">Amount: ₹${parseFloat(meta.qr_amount).toFixed(2)}</div>
                  <div class="chatbot-qr-image-container">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=2d1f0e&data=upi://pay?pa=rohandevadigapithrodi-1@oksbi%26pn=${encodeURIComponent(meta.qr_payee)}%26am=${meta.qr_amount}%26cu=INR" alt="UPI QR Code" style="width: 100%; height: 100%; object-fit: contain;" />
                  </div>
                  <div class="small text-muted mb-1">Payee: ${meta.qr_payee}</div>
                </div>
              `;
              messagesContainer.append(qrHtml);
            }

            // Quick Actions Options Buttons
            if (meta.options && meta.options.length > 0) {
              let optHtml = `<div class="chatbot-options-container">`;
              meta.options.forEach(opt => {
                optHtml += `<button class="chatbot-option-btn" data-value="${opt.value}">${opt.label}</button>`;
              });
              optHtml += `</div>`;
              messagesContainer.append(optHtml);
            }
          }
        });
      }

      function formatMessageText(text) {
        if (!text) return '';
        // Basic bold markdown support **text**
        return text
          .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
          .replace(/\*(.*?)\*/g, '<em>$1</em>')
          .replace(/\n/g, '<br>');
      }

      function sendMessage(customVal = null, displayLabel = null) {
        const text = customVal || input.val().trim();
        const display = displayLabel || text;
        if (!text) return;

        if (!customVal) {
          input.val('');
        }

        // Show devotee message immediately locally
        const localMsgHtml = `
          <div class="chatbot-msg-row devotee">
            <div class="chatbot-msg-sender">You</div>
            <div class="chatbot-msg-bubble">
              ${formatMessageText(display)}
            </div>
          </div>
        `;
        messagesContainer.append(localMsgHtml);
        scrollToBottom();

        $.post('{{ route('devotee.chat.send') }}', {
          _token: '{{ csrf_token() }}',
          message: text
        }, function(res) {
          loadMessages();
        });
      }

      function scrollToBottom() {
        messagesContainer.animate({ scrollTop: messagesContainer[0].scrollHeight }, 200);
      }

      function startPolling() {
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(function() {
          if (isWindowOpen) {
            loadMessages();
          }
        }, 3000); // Poll every 3 seconds
      }
    });
  </script>

  @yield('page-js')
</body>
</html>
