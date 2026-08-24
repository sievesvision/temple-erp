<!-- TOPBAR -->
  <header class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
      </button>
      <h4><i class="bi bi-grid-1x2-fill"></i> Dashboard</h4>
    </div>
    <div class="topbar-actions">
      <div class="dropdown me-2">
        <button class="btn-notif" data-bs-toggle="dropdown" aria-expanded="false" style="border:none; background:transparent; position:relative;">
          <i class="bi bi-bell-fill fs-5" style="color:#5a4e3e;"></i>
          @php
            $user = Auth::user();
            $notifications = DB::table('notifications')
                ->where('user_id', $user->id)
                ->where('is_read', false)
                ->orderBy('created_at', 'desc')
                ->get();
            $totalNotifCount = $notifications->count();
          @endphp
          @if($totalNotifCount > 0)
          <span class="badge bg-danger rounded-circle position-absolute top-0 start-100 translate-middle p-1 small" style="font-size: 0.65rem;">
            {{ $totalNotifCount }}
          </span>
          @endif
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 p-2" style="border-radius: 20px; width: 320px; max-height: 400px; overflow-y: auto;">
          <li class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
            <span class="fw-bold" style="color:#2d1f0e;">Notifications</span>
            @if($totalNotifCount > 0)
              <button class="btn btn-sm btn-link text-warning text-decoration-none fw-semibold p-0" id="markAllReadBtn" style="font-size: 0.75rem;">Mark all as read</button>
            @endif
          </li>
          
          @forelse($notifications as $notif)
          <li>
            <div class="dropdown-item px-3 py-2 rounded-3 mt-1 text-wrap small" style="color: #3e3e4a;">
              <div class="d-flex align-items-start gap-2">
                <span class="fs-5">🔔</span>
                <div>
                  <div class="fw-semibold text-dark">{{ $notif->message }}</div>
                  <div class="text-muted" style="font-size: 0.7rem;">{{ date('d M Y h:i A', strtotime($notif->created_at)) }}</div>
                </div>
              </div>
            </div>
          </li>
          @empty
          <li class="px-3 py-4 text-center text-muted">
            <i class="bi bi-bell-slash fs-4 text-warning mb-2 d-block"></i>
            <span class="small fw-semibold">No pending notifications</span>
          </li>
          @endforelse
        </ul>
      </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const markReadBtn = document.getElementById('markAllReadBtn');
    if (markReadBtn) {
        markReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fetch('{{ route('admin.notifications.mark-read') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(err => console.error(err));
        });
    }
});
</script>
      <div class="dropdown">
        <button class="profile-toggle dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="bi bi-person-circle"></i>
          <span>Admin</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="border-radius: 20px; padding: 8px;">
          <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}?tab=profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
          <li><a class="dropdown-item" href="#"><i class="bi bi-key me-2"></i>Change Password</a></li>
          <li><a class="dropdown-item" href="{{ route('admin.settings') }}"><i class="bi bi-gear me-2"></i>Settings</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" id="topbarLogoutBtn" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#logoutModal" style="cursor:pointer;"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </header>