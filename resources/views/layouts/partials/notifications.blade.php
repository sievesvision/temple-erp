<!-- Global Notification Container -->
<div id="global-notifications" style="position: fixed; top: 24px; right: 24px; z-index: 99999; display: flex; flex-direction: column; gap: 12px; max-width: 420px; width: calc(100% - 48px); pointer-events: none;">
    @if(session('success'))
        <div class="custom-toast toast-success" data-duration="3000" style="pointer-events: auto;">
            <div class="toast-icon-box">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="toast-content-box">
                <h6 class="toast-title">Success</h6>
                <p class="toast-message">{{ session('success') }}</p>
            </div>
            <button type="button" class="toast-close-btn" onclick="closeToast(this.parentElement)">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="custom-toast toast-error" data-duration="5000" style="pointer-events: auto;">
            <div class="toast-icon-box">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="toast-content-box">
                <h6 class="toast-title">Error</h6>
                <p class="toast-message">{{ session('error') }}</p>
            </div>
            <button type="button" class="toast-close-btn" onclick="closeToast(this.parentElement)">&times;</button>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        @foreach($errors->all() as $error)
            <div class="custom-toast toast-error" data-duration="5000" style="pointer-events: auto;">
                <div class="toast-icon-box">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="toast-content-box">
                    <h6 class="toast-title">Validation Error</h6>
                    <p class="toast-message">{{ $error }}</p>
                </div>
                <button type="button" class="toast-close-btn" onclick="closeToast(this.parentElement)">&times;</button>
            </div>
        @endforeach
    @endif
</div>

<style>
/* Toast Animations */
@keyframes toastFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes toastFadeOut {
    from {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    to {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
        margin-bottom: -72px; /* Smooth collapse */
    }
}

.custom-toast {
    display: flex;
    align-items: flex-start;
    padding: 16px 20px;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 10px 30px rgba(45, 31, 14, 0.08), 0 1px 3px rgba(45, 31, 14, 0.02);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    position: relative;
    border: 1px solid rgba(184, 134, 58, 0.12);
    animation: toastFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    transition: all 0.4s ease;
}

.toast-success {
    border-left: 5px solid #b8863a;
    background: linear-gradient(135deg, #ffffff, #fffdf9);
}

.toast-error {
    border-left: 5px solid #d32f2f;
    background: linear-gradient(135deg, #ffffff, #fffafa);
}

.toast-icon-box {
    margin-right: 14px;
    font-size: 1.35rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 2px;
}

.toast-success .toast-icon-box {
    color: #b8863a;
}

.toast-error .toast-icon-box {
    color: #d32f2f;
}

.toast-content-box {
    flex-grow: 1;
    margin-right: 12px;
}

.toast-title {
    font-weight: 700;
    font-size: 0.95rem;
    margin: 0 0 3px 0;
    color: #2d1f0e;
}

.toast-message {
    font-size: 0.85rem;
    margin: 0;
    color: #5a4e3e;
    line-height: 1.4;
    font-weight: 500;
}

.toast-close-btn {
    background: none;
    border: none;
    font-size: 1.3rem;
    color: #a0907e;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
    margin-top: -2px;
}

.toast-close-btn:hover {
    color: #2d1f0e;
}
</style>

<script>
function closeToast(toastElement) {
    if (toastElement) {
        toastElement.style.animation = 'toastFadeOut 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards';
        setTimeout(() => {
            toastElement.remove();
        }, 400);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const toasts = document.querySelectorAll('.custom-toast');
    toasts.forEach(toast => {
        const duration = parseInt(toast.getAttribute('data-duration')) || 3000;
        setTimeout(() => {
            closeToast(toast);
        }, duration);
    });
});
</script>
