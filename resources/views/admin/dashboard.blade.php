@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')

@section('page-css')
<style>
    /* cards */
    .stat-card {
        background: white;
        border-radius: 24px;
        padding: 22px 24px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(184, 134, 58, 0.06);
        transition: transform 0.15s, box-shadow 0.2s;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 32px rgba(184, 134, 58, 0.08);
    }
    .stat-card .stat-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #7b6b5a;
        font-weight: 600;
    }
    .stat-card .stat-number {
        font-size: 2.2rem;
        font-weight: 700;
        color: #1e1e2a;
        letter-spacing: -0.5px;
        margin: 4px 0 0 0;
    }
    .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }
    .stat-icon.gold {
        background: #b8863a;
    }
    .stat-icon.blue {
        background: #2a6fdb;
    }
    .stat-icon.green {
        background: #1f9d6a;
    }
    .stat-icon.rose {
        background: #c94b6e;
    }
    .stat-icon.red {
        background: #ff0d0d;
    }
    .stat-icon.yellow {
        background: #ffbb00;
    }

    /* quick actions */
    .quick-card {
        background: white;
        border-radius: 24px;
        padding: 24px 28px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .quick-card h5 {
        font-weight: 600;
        color: #2d1f0e;
        margin-bottom: 18px;
    }
    .quick-btn {
        border-radius: 60px;
        padding: 12px 0;
        font-weight: 600;
        font-size: 0.95rem;
        border: none;
        transition: all 0.2s;
        background: #f4efe9;
        color: #2d1f0e;
        width: 100%;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }
    .quick-btn:hover {
        background: #b8863a;
        color: white;
        transform: scale(0.98);
        text-decoration: none;
    }
    .quick-btn i {
        margin-right: 8px;
    }
    .quick-btn.primary {
        background: #b8863a;
        color: white;
    }
    .quick-btn.primary:hover {
        background: #a07431;
        color: white;
    }

    /* table cards */
    .table-wrap {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        overflow: hidden;
        height: 100%;
    }
    .table-wrap .card-header {
        background: transparent;
        border-bottom: 1px solid #f0ece6;
        padding: 18px 24px;
        font-weight: 600;
        font-size: 1.05rem;
        color: #2d1f0e;
    }
    .table-wrap .table {
        margin: 0;
    }
    .table-wrap .table th {
        font-weight: 600;
        color: #5a4e3e;
        border-bottom: 1px solid #f0ece6;
        padding: 14px 24px;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .table-wrap .table td {
        padding: 14px 24px;
        border-bottom: 1px solid #f5f0ea;
        color: #1e1e2a;
        font-weight: 500;
    }
    .table-wrap .table tr:last-child td {
        border-bottom: none;
    }
    .badge-amount {
        background: #f3ebe0;
        color: #b8863a;
        padding: 4px 12px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.8rem;
    }
    .text-muted-light {
        color: #a0907e;
    }

    /* Staff/Admin Chat Support Styles */
    .chat-layout {
        display: flex;
        height: calc(100vh - 200px);
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        border: 1px solid rgba(184, 134, 58, 0.08);
        overflow: hidden;
    }
    .chat-sidebar {
        width: 320px;
        border-right: 1px solid #f4eeeb;
        display: flex;
        flex-direction: column;
        background: #fdfcfb;
    }
    .chat-sidebar-header {
        padding: 20px;
        border-bottom: 1px solid #f4eeeb;
        font-weight: 700;
        color: #2d1f0e;
    }
    .chat-sessions-list {
        flex: 1;
        overflow-y: auto;
    }
    .chat-session-item {
        padding: 16px 20px;
        border-bottom: 1px solid #f9f6f3;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .chat-session-item:hover {
        background: #f6efe2;
    }
    .chat-session-item.active {
        background: #f0e4cf;
        border-left: 4px solid #b8863a;
    }
    .chat-session-devotee {
        font-weight: 600;
        font-size: 14.5px;
        color: #2d1f0e;
    }
    .chat-session-email {
        font-size: 12px;
        color: #8c7e70;
    }
    .chat-session-time {
        font-size: 11px;
        color: #b8863a;
        align-self: flex-end;
    }
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #fafaf8;
    }
    .chat-main-header {
        padding: 16px 24px;
        background: white;
        border-bottom: 1px solid #f4eeeb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .chat-main-devotee-info {
        display: flex;
        flex-direction: column;
    }
    .chat-main-devotee-name {
        font-weight: 700;
        font-size: 16px;
        color: #2d1f0e;
    }
    .chat-main-devotee-email {
        font-size: 12.5px;
        color: #8c7e70;
    }
    .chat-area-messages {
        flex: 1;
        padding: 24px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .chat-msg-row-staff {
        display: block;
        width: 100%;
        clear: both;
        margin-bottom: 8px;
    }
    .chat-bubble-staff {
        max-width: 75%;
        padding: 12px 18px;
        border-radius: 18px;
        font-size: 14px;
        line-height: 1.45;
        word-wrap: break-word;
        display: inline-block;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }
    .chat-msg-row-staff.devotee .chat-bubble-staff {
        background: white;
        color: #2d1f0e;
        border-bottom-left-radius: 4px;
        float: left;
        border-left: 3px solid #b8863a;
    }
    .chat-msg-row-staff.staff .chat-bubble-staff {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        border-bottom-right-radius: 4px;
        float: right;
    }
    .chat-msg-row-staff.bot .chat-bubble-staff {
        background: #e9e5d9;
        color: #7c6853;
        border-bottom-left-radius: 4px;
        float: left;
        font-style: italic;
    }
    .chat-bubble-sender {
        font-size: 10px;
        font-weight: 700;
        margin-bottom: 4px;
        text-transform: uppercase;
    }
    .chat-msg-row-staff.devotee .chat-bubble-sender {
        color: #b8863a;
    }
    .chat-msg-row-staff.staff .chat-bubble-sender {
        color: rgba(255,255,255,0.7);
        text-align: right;
    }
    .chat-msg-row-staff.bot .chat-bubble-sender {
        color: #8c7e70;
    }
    .chat-footer-staff {
        padding: 18px 24px;
        background: white;
        border-top: 1px solid #f4eeeb;
        display: flex;
        gap: 12px;
    }
    .chat-input-staff {
        flex: 1;
        border: 1px solid rgba(184, 134, 58, 0.25);
        border-radius: 24px;
        padding: 10px 20px;
        font-size: 14px;
        outline: none;
    }
    .chat-input-staff:focus {
        border-color: #b8863a;
    }
    .chat-btn-send-staff {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 24px;
        font-weight: 600;
        font-size: 14.5px;
        box-shadow: 0 4px 10px rgba(184, 134, 58, 0.2);
        transition: all 0.2s;
    }
    .chat-btn-send-staff:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(184, 134, 58, 0.35);
    }
    .chat-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #8c7e70;
        gap: 12px;
    }
    .chat-empty-state i {
        font-size: 48px;
        color: #e3dad0;
    }
</style>
@endsection

@section('content')
<!-- DASHBOARD CONTENT -->
<div class="container-fluid px-4 py-4">

    @if(request()->get('tab') == 'chats' || request()->get('tab') == 'prev_chats')
        @php
          $isPrevChats = request()->get('tab') == 'prev_chats';
        @endphp
        <!-- SUPPORT CHATS SECTION -->
        <div class="chat-layout animate__animated animate__fadeIn">
          <!-- Sidebar / Active Sessions List -->
          <div class="chat-sidebar">
            <div class="chat-sidebar-header d-flex justify-content-between align-items-center">
              @if($isPrevChats)
                <span><i class="bi bi-clock-history text-warning me-2"></i>Completed Chats</span>
                <input type="hidden" id="chatSessionTypeFilter" value="ended">
              @else
                <span><i class="bi bi-chat-dots-fill text-warning me-2"></i>Support Chats</span>
                <select id="chatSessionTypeFilter" class="form-select form-select-sm border-0 bg-transparent text-warning fw-bold p-0" style="width: auto; cursor: pointer; box-shadow: none; font-size: 14px;">
                  <option value="active" selected>Active</option>
                  <option value="ended">History</option>
                </select>
              @endif
            </div>
            <div class="chat-sessions-list" id="staffChatSessionsList">
              <div class="p-4 text-center text-muted">Loading chats...</div>
            </div>
          </div>

          <!-- Main Chat Area -->
          <div class="chat-main" id="staffChatMain">
            <div class="chat-empty-state">
              <i class="bi bi-chat-left-dots"></i>
              <h5>Select a conversation</h5>
              @if($isPrevChats)
                <p class="small text-muted">Choose a completed devotee support session from the list to view the history.</p>
              @else
                <p class="small text-muted">Choose an active devotee support session from the list to start replying.</p>
              @endif
            </div>
          </div>
        </div>
    @elseif(request()->get('tab') != 'profile')
        <!-- STATS ROW -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Devotees</div>
                        <div class="stat-number">{{ number_format($devoteesCount) }}</div>
                    </div>
                    <div class="stat-icon gold"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Today's Poojas</div>
                        <div class="stat-number">{{ number_format($todayPoojasCount) }}</div>
                    </div>
                    <div class="stat-icon blue"><i class="bi bi-calendar-event"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Donations</div>
                        <div class="stat-number">{{ $donationsDisplay }}</div>
                    </div>
                    <div class="stat-icon green"><i class="bi bi-wallet2"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Events</div>
                        <div class="stat-number">{{ number_format($eventsCount) }}</div>
                    </div>
                    <div class="stat-icon rose"><i class="bi bi-stars"></i></div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="quick-card mb-4">
            <h5><i class="bi bi-lightning-charge-fill me-2" style="color:#b8863a;"></i>Quick Actions</h5>
            <div class="row g-3">
                <div class="col-md-3 col-sm-6">
                    <a href="{{ route('admin.devotees.index') }}" class="quick-btn primary w-100">
                        <i class="bi bi-people-fill"></i> Manage Devotees
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="{{ route('admin.priests.index') }}" class="quick-btn primary w-100">
                        <i class="bi bi-person-plus"></i> Manage Priest
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="{{ route('admin.donations.index') }}" class="quick-btn w-100 text-decoration-none d-block text-center">
                        <i class="bi bi-coin"></i> Manage Donation
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="{{ route('admin.events.index') }}" class="quick-btn w-100 text-decoration-none d-block text-center">
                        <i class="bi bi-calendar-plus"></i> Manage Event
                    </a>
                </div>
            </div>
        </div>

        <!-- PRIEST STATUS ROW -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Online Priest</div>
                        <div class="stat-number">{{ $onlinePriests }}</div>
                    </div>
                    <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Busy Priest</div>
                        <div class="stat-number">{{ $busyPriests }}</div>
                    </div>
                    <div class="stat-icon rose"><i class="bi bi-calendar-event"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">   
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Offline Priest</div>
                        <div class="stat-number">{{ $offlinePriests }}</div>
                    </div>
                    <div class="stat-icon red"><i class="bi bi-calendar-event"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Priest in leave</div>
                        <div class="stat-number">{{ $leavePriests }}</div>
                    </div>
                    <div class="stat-icon blue"><i class="bi bi-wallet2"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Total Priest</div>
                        <div class="stat-number">{{ $priestsCount }}</div>
                    </div>
                    <div class="stat-icon yellow"><i class="bi bi-stars"></i></div>
                </div>
            </div>
        </div>

        <!-- TABLES ROW -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="table-wrap">
                    <div class="card-header"><i class="bi bi-clock-history me-2" style="color:#b8863a;"></i>Recent Devotees</div>
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentDevotees as $devotee)
                            <tr>
                                <td>{{ $devotee->name }}</td>
                                <td>{{ $devotee->mobile }}</td>
                                <td>
                                    @if(date('Y-m-d', strtotime($devotee->created_at)) == date('Y-m-d'))
                                        <span class="badge-amount">today</span>
                                    @else
                                        {{ date('M d', strtotime($devotee->created_at)) }}
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">No devotees found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="table-wrap">
                    <div class="card-header"><i class="bi bi-gift me-2" style="color:#b8863a;"></i>Recent Donations</div>
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Donor</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentDonations as $donation)
                            <tr>
                                <td>{{ $donation->donor_name ?? 'Anonymous' }}</td>
                                <td>₹{{ number_format($donation->amount) }}</td>
                                <td>
                                    @if(date('Y-m-d', strtotime($donation->donation_date)) == date('Y-m-d'))
                                        <span class="badge-amount">today</span>
                                    @else
                                        {{ date('M d', strtotime($donation->donation_date)) }}
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">No donations found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- extra spacer -->
        <div class="mt-4 text-muted-light small d-flex justify-content-end">
            <i class="bi bi-droplet me-1"></i> updated just now
        </div>
    @else
        <!-- Admin Profile -->
        <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
            <h5 class="fw-bold mb-4"><i class="bi bi-person-circle text-warning me-2"></i>My Profile Information</h5>
            
            @if(session('success'))
                <div class="alert alert-success border-0 rounded-3 p-3 mb-4 shadow-sm" style="background: #e6f9f0; color: #1f7a52;">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger border-0 rounded-3 p-3 mb-4 shadow-sm" style="background: #ffebe6; color: #cc3300;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger border-0 rounded-3 p-3 mb-4 shadow-sm" style="background: #ffebe6; color: #cc3300;">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('profile.update') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control rounded-3" value="{{ auth()->user()->name }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" class="form-control rounded-3" value="{{ auth()->user()->email }}" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Mobile Number</label>
                        <input type="text" name="mobile" class="form-control rounded-3" value="{{ auth()->user()->mobile }}" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-warning rounded-pill px-5 fw-semibold mt-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none; color: white;">Save Changes</button>
            </form>
        </div>
    @endif
</div>
@endsection

@section('page-js')
<script>
    const BASE_URL = '{{ url("/") }}';
    $(document).ready(function() {
        console.log('Admin Dashboard loaded');

        @if(request()->get('tab') === 'chats' || request()->get('tab') === 'prev_chats')
        // Admin chat support variables
        let activeSessionId = null;
        let sessionsPollInterval = null;
        let messagesPollInterval = null;

        function loadSessionsList() {
            const type = $('#chatSessionTypeFilter').val() || 'active';
            const url = type === 'ended' ? '{{ route('admin.chats.history') }}' : '{{ route('admin.chats.active') }}';

            $.get(url, function(res) {
                if (res.success) {
                    const list = $('#staffChatSessionsList');
                    const currentActive = activeSessionId;
                    list.empty();
                    if (res.sessions.length === 0) {
                        list.append('<div class="p-4 text-center text-muted small">No ' + type + ' support chats.</div>');
                        return;
                    }
                    res.sessions.forEach(sess => {
                        const activeClass = currentActive == sess.session_id ? 'active' : '';
                        const timeStr = new Date(sess.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        
                        // Pulsing green dot for new devotee messages in active lists
                        const greenDot = (type === 'active' && sess.last_sender_type === 'devotee')
                            ? '<span class="badge bg-success ms-2 animate__animated animate__pulse animate__infinite" style="font-size: 9px; padding: 2px 5px;">New Msg</span>'
                            : '';

                        const item = `
                            <div class="chat-session-item ${activeClass}" data-id="${sess.session_id}" data-name="${sess.devotee_name}" data-email="${sess.devotee_email}" data-status="${sess.status}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="chat-session-devotee">${sess.devotee_name} ${greenDot}</span>
                                    <span class="chat-session-time">${timeStr}</span>
                                </div>
                                <span class="chat-session-email">${sess.devotee_email}</span>
                            </div>
                        `;
                        list.append(item);
                    });
                }
            });
        }

        function loadChatMessages() {
            if (!activeSessionId) return;
            $.get(`${BASE_URL}/admin/chats/${activeSessionId}/messages`, function(res) {
                if (res.success) {
                    const container = $('#staffChatMessagesArea');
                    if (container.length === 0) return;
                    const scrollBottom = container.scrollTop() + container.innerHeight() >= container[0].scrollHeight - 50;
                    
                    container.empty();
                    res.messages.forEach(msg => {
                        const type = msg.sender_type;
                        const sender = type === 'devotee' ? 'Devotee' : (type === 'staff' ? 'You' : 'Mandir Bot');
                        let content = msg.message_text;

                        // Format text formatting
                        content = content
                            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                            .replace(/\*(.*?)\*/g, '<em>$1</em>')
                            .replace(/\n/g, '<br>');

                        let bubble = `
                            <div class="chat-msg-row-staff ${type}">
                                <div class="chat-bubble-sender">${sender}</div>
                                <div class="chat-bubble-staff">${content}</div>
                            </div>
                        `;
                        container.append(bubble);
                    });

                    // If session is ended, disable typing inputs and show info alert
                    if (res.session_status === 'ended') {
                        $('#staffChatInput').prop('disabled', true).attr('placeholder', 'This chat has been ended.');
                        $('#staffChatSendBtn').prop('disabled', true);
                        $('#staffEndChatBtn').hide();
                        if ($('#staffChatEndedAlert').length === 0) {
                            container.append('<div id="staffChatEndedAlert" class="alert alert-warning text-center mx-3 my-2 small py-2"><i class="bi bi-info-circle me-1"></i> Devotee has ended this conversation.</div>');
                        }
                    } else {
                        $('#staffChatInput').prop('disabled', false).attr('placeholder', 'Type a reply...');
                        $('#staffChatSendBtn').prop('disabled', false);
                        $('#staffEndChatBtn').show();
                        $('#staffChatEndedAlert').remove();
                    }

                    if (scrollBottom) {
                        container.scrollTop(container[0].scrollHeight);
                    }
                }
            });
        }

        // Reload list when toggling active/ended filter
        $(document).on('change', '#chatSessionTypeFilter', function() {
            loadSessionsList();
        });

        // Handle session click
        $(document).on('click', '.chat-session-item', function() {
            activeSessionId = $(this).data('id');
            const name = $(this).data('name');
            const email = $(this).data('email');
            const status = $(this).data('status');

            $('.chat-session-item').removeClass('active');
            $(this).addClass('active');

            // Render main chat area
            const main = $('#staffChatMain');
            const endBtnHtml = status === 'active' 
                ? `<button class="btn btn-outline-danger btn-sm rounded-pill px-3" id="staffEndChatBtn">End Conversation</button>` 
                : '';

            const footerHtml = status === 'active'
                ? `
                    <div class="chat-footer-staff">
                        <input type="text" class="chat-input-staff" id="staffChatInput" placeholder="Type a reply..." autocomplete="off">
                        <button class="chat-btn-send-staff" id="staffChatSendBtn">Send Reply</button>
                    </div>
                `
                : `
                    <div class="chat-footer-staff justify-content-center bg-light border-top py-3 px-4">
                        <div class="alert alert-secondary text-center w-100 mb-0 py-2 small" style="border-radius: 20px; color: #5c3c10; background-color: #fcf8e3; border: 1px solid #faebcc;">
                            <i class="bi bi-lock-fill me-1"></i> This conversation has been completed and is now read-only.
                        </div>
                    </div>
                `;

            main.empty().html(`
                <div class="chat-main-header">
                    <div class="chat-main-devotee-info">
                        <span class="chat-main-devotee-name">${name}</span>
                        <span class="chat-main-devotee-email">${email}</span>
                    </div>
                    ${endBtnHtml}
                </div>
                <div class="chat-area-messages" id="staffChatMessagesArea">
                    <div class="text-center text-muted small p-4">Loading messages...</div>
                </div>
                ${footerHtml}
            `);

            loadChatMessages();
            setTimeout(() => {
                const area = $('#staffChatMessagesArea');
                if (area.length > 0) area.scrollTop(area[0].scrollHeight);
            }, 300);
        });

        // Handle staff reply send
        $(document).on('click', '#staffChatSendBtn', function() {
            sendStaffReply();
        });

        $(document).on('keypress', '#staffChatInput', function(e) {
            if (e.which === 13) {
                sendStaffReply();
            }
        });

        function sendStaffReply() {
            const input = $('#staffChatInput');
            const text = input.val().trim();
            if (!text || !activeSessionId) return;

            input.val('');

            $.post(`${BASE_URL}/admin/chats/${activeSessionId}/reply`, {
                _token: '{{ csrf_token() }}',
                message: text
            }, function(res) {
                loadChatMessages();
                setTimeout(() => {
                    const area = $('#staffChatMessagesArea');
                    if (area.length > 0) area.scrollTop(area[0].scrollHeight);
                }, 100);
            });
        }

        // Handle staff end chat
        $(document).on('click', '#staffEndChatBtn', function() {
            if (confirm("Are you sure you want to resolve and end this devotee conversation?")) {
                $.post(`${BASE_URL}/admin/chats/${activeSessionId}/end`, {
                    _token: '{{ csrf_token() }}'
                }, function(res) {
                    activeSessionId = null;
                    $('#staffChatMain').html(`
                        <div class="chat-empty-state">
                            <i class="bi bi-chat-left-dots"></i>
                            <h5>Select a conversation</h5>
                            <p class="small text-muted">Choose an active devotee support session from the list to start replying.</p>
                        </div>
                    `);
                    loadSessionsList();
                });
            }
        });

        // Polling Setup
        loadSessionsList();
        sessionsPollInterval = setInterval(loadSessionsList, 5000);
        messagesPollInterval = setInterval(loadChatMessages, 3000);
        @endif
    });
</script>
@endsection