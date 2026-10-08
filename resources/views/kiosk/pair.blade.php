<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Pair This Kiosk</title>
    <style>
        :root {
            --maroon: #6B0F1A;
            --gold: #C9952E;
            --cream: #FFF9EE;
            --border: #C7D0DA;
            --text-primary: #24282B;
            --text-secondary: #5B6673;
            --error: #D92D20;
            --success: #12B76A;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: var(--cream); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            color: var(--text-primary); padding: 24px;
        }
        .pair-card {
            width: 100%; max-width: 420px; background: #fff; border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12); padding: 40px 32px; text-align: center;
        }
        .pair-icon { font-size: 2.6rem; margin-bottom: 12px; }
        h1 { font-size: 1.4rem; margin: 0 0 8px; color: var(--maroon); }
        p.hint { color: var(--text-secondary); font-size: 0.95rem; margin: 0 0 28px; line-height: 1.5; }
        input#codeInput {
            width: 100%; font-size: 2rem; letter-spacing: 0.3em; text-align: center; text-transform: uppercase;
            padding: 16px 8px; border: 2px solid var(--border); border-radius: 14px; font-family: 'Courier New', monospace;
            margin-bottom: 20px; background: var(--cream);
        }
        input#codeInput:focus { outline: none; border-color: var(--gold); }
        button#pairBtn {
            width: 100%; padding: 18px; font-size: 1.1rem; font-weight: 700; color: #fff;
            background: var(--maroon); border: none; border-radius: 14px; cursor: pointer;
        }
        button#pairBtn:disabled { opacity: 0.6; cursor: not-allowed; }
        #message { margin-top: 18px; font-size: 0.95rem; min-height: 1.3em; }
        #message.error { color: var(--error); }
        #message.success { color: var(--success); }
    </style>
</head>
<body>
    <div class="pair-card">
        <div class="pair-icon">&#128241;</div>
        <h1>Pair This Kiosk</h1>
        <p class="hint">Enter the one-time code shown on the admin's screen. It stays valid for 10 minutes.</p>
        <input type="text" id="codeInput" maxlength="8" placeholder="XXXXXXXX" autocomplete="off" autocapitalize="characters" inputmode="text">
        <button type="button" id="pairBtn">Pair Device</button>
        <p id="message"></p>
    </div>

    <script>
        (function () {
            var STORAGE_KEY = 'ssvkKioskDeviceToken';
            var codeInput = document.getElementById('codeInput');
            var pairBtn = document.getElementById('pairBtn');
            var message = document.getElementById('message');

            // Already paired on this device — no need to go through this screen again.
            try {
                if (localStorage.getItem(STORAGE_KEY)) {
                    message.textContent = 'This device is already paired.';
                    message.className = 'success';
                }
            } catch (e) {}

            codeInput.addEventListener('input', function () {
                codeInput.value = codeInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });

            function pair() {
                var code = codeInput.value.trim();
                if (code.length < 4) {
                    message.textContent = 'Enter the full code first.';
                    message.className = 'error';
                    return;
                }
                pairBtn.disabled = true;
                message.textContent = 'Pairing...';
                message.className = '';

                fetch('{{ route('kiosk.pair.redeem') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ code: code }),
                })
                    .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                    .then(function (result) {
                        pairBtn.disabled = false;
                        if (!result.data.success) {
                            message.textContent = result.data.message || 'Could not pair this device.';
                            message.className = 'error';
                            return;
                        }
                        try { localStorage.setItem(STORAGE_KEY, result.data.token); } catch (e) {}
                        message.textContent = 'Paired as "' + result.data.device_name + '".';
                        message.className = 'success';
                        codeInput.value = '';
                    })
                    .catch(function () {
                        pairBtn.disabled = false;
                        message.textContent = 'Network error — please try again.';
                        message.className = 'error';
                    });
            }

            pairBtn.addEventListener('click', pair);
            codeInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { pair(); } });
        })();
    </script>
</body>
</html>
