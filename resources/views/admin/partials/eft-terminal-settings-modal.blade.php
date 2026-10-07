{{-- A same-page popup for the EFT Terminal Settings page, loaded in an iframe — kiosk-mode
     browsers and single-window POS setups can't open a new tab, so this is how a console or
     POS page reaches pairing without navigating away from itself. Include this once per page
     and trigger it with openEftTerminalSettingsModal(), e.g. from a <button onclick="...">. --}}
<div id="eftSettingsModalOverlay" style="display:none; position:fixed; inset:0; background:rgba(17,17,17,0.55); z-index:2000; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; width:min(1150px, 100%); height:min(88vh, 920px); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 70px rgba(0,0,0,0.35);">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 18px; background:linear-gradient(135deg,#6B0F1A,#4A0A12); color:#fff; flex-shrink:0;">
            <strong style="font-size:1.02rem;"><i class="bi bi-pc-display me-2"></i>EFT Terminal Settings</strong>
            <button type="button" id="eftSettingsModalClose" aria-label="Close" style="background:rgba(255,255,255,0.12); border:none; color:#fff; width:32px; height:32px; border-radius:8px; font-size:1.3rem; line-height:1; cursor:pointer;">&times;</button>
        </div>
        <iframe id="eftSettingsModalFrame" src="about:blank" title="EFT Terminal Settings" style="flex:1; border:0; width:100%; background:#FAFAF8;"></iframe>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('eftSettingsModalOverlay');
    var frame = document.getElementById('eftSettingsModalFrame');
    var closeBtn = document.getElementById('eftSettingsModalClose');
    if (!overlay || !frame || !closeBtn) { return; }

    window.openEftTerminalSettingsModal = function () {
        frame.src = '{{ route('admin.eft-terminals.index') }}?embedded=1';
        overlay.style.display = 'flex';
    };
    // A full reload on close is the simplest reliable way to reflect whatever changed in the
    // popup (a new terminal, a fresh pairing) in this page's own terminal list/picker, which
    // is otherwise baked into server-rendered HTML or a JS array from page load.
    var somethingMayHaveChanged = false;
    frame.addEventListener('load', function () {
        if (frame.src !== 'about:blank') { somethingMayHaveChanged = true; }
    });
    function closeModal() {
        overlay.style.display = 'none';
        frame.src = 'about:blank';
        if (somethingMayHaveChanged) { window.location.reload(); }
    }
    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) { closeModal(); }
    });
})();
</script>
