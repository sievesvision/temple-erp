<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Welcome</title>
    <style>
        :root {
            --ink: #16211B;
            --accent: #2F6F4E;
            --accent-light: #E8F3EC;
            --gold: #C9952E;
            --cream: #FBF8F1;
            --muted: #5B6B60;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; margin: 0; }
        body {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: radial-gradient(circle at top, var(--accent-light), var(--cream) 60%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            color: var(--ink); text-align: center; padding: 24px; overflow: hidden;
            user-select: none;
        }
        #brandLogo { width: 92px; height: 92px; border-radius: 50%; object-fit: cover; margin-bottom: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
        #brandName { font-size: clamp(1.4rem, 4vw, 2.1rem); font-weight: 800; margin: 0 0 4px; }
        #brandSubtitle { font-size: 1rem; color: var(--muted); margin: 0 0 40px; }
        #startBtn {
            width: min(72vw, 360px); height: min(72vw, 360px); border-radius: 50%;
            background: linear-gradient(145deg, var(--accent), #1F4E37); color: #fff; border: none;
            font-size: clamp(1.3rem, 3.5vw, 1.8rem); font-weight: 800; letter-spacing: 0.02em;
            box-shadow: 0 18px 50px rgba(47,111,78,0.35); cursor: pointer;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;
            animation: pulse 2.6s ease-in-out infinite;
        }
        #startBtn .tap-icon { font-size: 2.6rem; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.035); } }
        #payIcons { margin-top: 36px; display: flex; gap: 14px; align-items: center; color: var(--muted); font-size: 0.85rem; }
        #payIcons span { display: flex; align-items: center; gap: 6px; background: #fff; padding: 7px 14px; border-radius: 999px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        #promo { margin-top: 28px; max-width: 480px; color: var(--muted); font-size: 0.92rem; line-height: 1.5; }
        #unavailable { display: none; max-width: 420px; color: var(--muted); font-size: 1.05rem; line-height: 1.6; }
    </style>
</head>
<body>
    <img id="brandLogo" src="" alt="" onerror="this.style.display='none'">
    <h1 id="brandName">Loading…</h1>
    <p id="brandSubtitle"></p>
    <button type="button" id="startBtn"><span class="tap-icon">&#128073;</span><span>Touch to Start</span></button>
    <div id="payIcons"></div>
    <p id="promo"></p>
    <div id="unavailable">
        <p>This kiosk isn't accepting orders right now. Please see a staff member for assistance.</p>
    </div>

    <script src="{{ asset('js/print-agent.js') }}?v={{ @filemtime(public_path('js/print-agent.js')) }}"></script>
    <script>
        (function () {
            var STORAGE_KEY = 'ssvkKioskDeviceToken';
            var token = null;
            try { token = localStorage.getItem(STORAGE_KEY); } catch (e) {}

            if (!token) {
                window.location.href = '{{ route('kiosk.pair') }}';
                return;
            }

            var startBtn = document.getElementById('startBtn');
            var unavailable = document.getElementById('unavailable');

            fetch('{{ route('kiosk.api.bootstrap') }}', { headers: { 'X-Kiosk-Device-Token': token, 'Accept': 'application/json' } })
                .then(function (res) {
                    if (res.status === 401) { window.location.href = '{{ route('kiosk.pair') }}'; return Promise.reject(); }
                    return res.json();
                })
                .then(function (data) {
                    var temple = data.temple || {};
                    document.getElementById('brandLogo').src = temple.logo || '';
                    document.getElementById('brandName').textContent = temple.brand_title || temple.name || 'Welcome';
                    document.getElementById('brandSubtitle').textContent = temple.subtitle || '';

                    if (!data.module || (data.module === 'donations' && data.closed)) {
                        startBtn.style.display = 'none';
                        unavailable.style.display = 'block';
                        return;
                    }

                    var methods = data.payment_methods || [];
                    var icons = { 'Cash': '&#128181; Cash', 'UPI': '&#128241; UPI', 'Bank Transfer': '&#127974; Bank Transfer', 'Cheque': '&#128196; Cheque', 'EFT Terminal': '&#128179; Card' };
                    document.getElementById('payIcons').innerHTML = methods.map(function (m) { return '<span>' + (icons[m] || m) + '</span>'; }).join('');

                    if (data.module === 'donations' && data.event) {
                        document.getElementById('promo').textContent = 'Donate towards ' + data.event.name;
                    }
                })
                .catch(function () {});

            startBtn.addEventListener('click', function () {
                window.location.href = '{{ route('kiosk.order') }}';
            });
        })();
    </script>
</body>
</html>
