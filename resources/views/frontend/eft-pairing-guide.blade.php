<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pairing an EFTPOS Terminal with SievesPOS | Linkly Cloud</title>
    <style>
        :root { color-scheme: light; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f7f5f2; color: #2a2a2a; margin: 0; padding: 0; line-height: 1.6; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 40px 20px 80px; }
        header { margin-bottom: 32px; }
        header h1 { font-size: 1.8rem; margin: 0 0 6px; color: #7a1f1f; }
        header p { margin: 0; color: #666; }
        .badge { display: inline-block; background: #7a1f1f; color: #fff; font-size: 0.75rem; font-weight: 700; padding: 4px 12px; border-radius: 20px; margin-bottom: 14px; letter-spacing: 0.03em; }
        .step { background: #fff; border: 1px solid #e5e0d8; border-radius: 12px; padding: 20px 24px; margin-bottom: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .step h2 { font-size: 1.05rem; margin: 0 0 8px; display: flex; align-items: center; gap: 10px; }
        .step-num { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #7a1f1f; color: #fff; font-weight: 700; font-size: 0.9rem; flex-shrink: 0; }
        .step p { margin: 6px 0; color: #333; }
        .note { background: #fff8ec; border: 1px solid #f0dfa8; border-radius: 8px; padding: 12px 16px; margin-top: 10px; font-size: 0.92rem; }
        .note strong { color: #8a6d1a; }
        code { background: #f0ede7; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
        footer { margin-top: 40px; font-size: 0.85rem; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <span class="badge">SievesPOS &middot; Linkly Cloud EFTPOS</span>
            <h1>Pairing an EFTPOS Terminal with SievesPOS</h1>
            <p>A step-by-step guide to connecting a CBA Essential Plus (or compatible) terminal to SievesPOS via Linkly Cloud, so card payments can be taken directly from the POS screen.</p>
        </header>

        <div class="step">
            <h2><span class="step-num">1</span> Generate a pairing code on the terminal</h2>
            <p>On the physical EFTPOS terminal (or the Linkly Virtual PIN Pad during testing), open the pairing/cloud setup menu and generate a new pairing code.</p>
            <div class="note"><strong>Note:</strong> the pairing code is only valid for a short window (around 3 minutes) — generate it immediately before the next step rather than in advance.</div>
        </div>

        <div class="step">
            <h2><span class="step-num">2</span> Open the EFTPOS pairing screen in SievesPOS</h2>
            <p>Sign in to SievesPOS as an administrator and go to <strong>Settings &rarr; EFT Terminal</strong> (or, for a per-event operator, the event's <strong>Console &rarr; EFTPOS</strong> pane).</p>
        </div>

        <div class="step">
            <h2><span class="step-num">3</span> Enter the pairing code</h2>
            <p>Type the code exactly as shown on the terminal into the <strong>Pairing Code</strong> field and submit.</p>
        </div>

        <div class="step">
            <h2><span class="step-num">4</span> Confirm pairing succeeded</h2>
            <p>SievesPOS will show a <strong>Paired</strong> status once the terminal has been linked. The EFT Terminal payment option is now available on the POS screen for taking card payments.</p>
        </div>

        <div class="step">
            <h2><span class="step-num">5</span> Re-pair if needed</h2>
            <p>If the terminal is replaced, factory reset, or the pairing is lost for any reason, simply repeat steps 1&ndash;4 with a freshly generated pairing code — this can be done at any time without affecting existing transaction history.</p>
        </div>

        <header style="margin-top: 44px;">
            <span class="badge">Multiple Terminals</span>
            <h1 style="font-size: 1.4rem;">Running More Than One Terminal at Once</h1>
            <p>SievesPOS can pair and run several EFTPOS terminals at the same time — for example, one for the Ticket Kiosk and a separate one for an event's donation POS, or two ticket-selling computers each on their own terminal, so neither queue has to wait for the other.</p>
        </header>

        <div class="step">
            <h2><span class="step-num">6</span> Register a new terminal</h2>
            <p>Go to <strong>Settings &rarr; EFT Terminals</strong> (Admin), the standalone <strong>EFT Terminal Settings</strong> page, or the <strong>Ticket Console &rarr; Settings</strong> pane — all three show the same shared list. Under <strong>Add Another Terminal</strong>, give it a short <strong>Key</strong> (no spaces, e.g. <code>ticket-counter-2</code>) and a friendly <strong>Label</strong> (e.g. "Ticket Counter 2"), then submit. This creates the terminal record but does not pair it yet.</p>
        </div>

        <div class="step">
            <h2><span class="step-num">7</span> Pair that specific terminal</h2>
            <p>Each registered terminal has its own <strong>Pairing Code</strong> field and its own Paired/Not Paired status — repeat steps 1&ndash;4 above for the new terminal's row specifically, using a pairing code generated on <em>that</em> physical (or virtual) PIN pad. Pairing one terminal never affects any other terminal's pairing.</p>
        </div>

        <div class="step">
            <h2><span class="step-num">8</span> Choose which terminal each computer uses</h2>
            <p>Which terminal a given computer actually charges to is set <strong>on that computer only</strong> — it is never a shared, server-wide setting, which is what lets two computers run two terminals at once without conflicting:</p>
            <p>&bull; On the <strong>Ticket Kiosk</strong> screen itself, tap the <strong>Terminal</strong> button in the top bar to see and choose from the registered, paired terminals.</p>
            <p>&bull; Or, from the <strong>Ticket Console &rarr; Settings</strong> pane, use <strong>This Computer's EFT Terminal</strong> to pick and save a terminal for whichever computer you're using at the time — it takes effect on the Ticket Kiosk on that same computer immediately.</p>
            <p>&bull; The <strong>Event POS</strong> donation screen has the same Terminal button in its top bar for choosing that station's terminal.</p>
            <div class="note"><strong>Note:</strong> this choice is stored only in that browser (not sent to SievesPOS's server), so it must be set again on each new computer or if that browser's data is cleared — the kiosk will use the registry's default terminal until it is.</div>
        </div>

        <div class="step">
            <h2><span class="step-num">9</span> Testing two terminals on <em>one</em> computer</h2>
            <p>If you're testing two virtual PIN pads by opening two browser tabs on the same computer rather than using two separate computers, use the <strong>Terminal</strong> button in each tab's own top bar to explicitly choose that tab's terminal — don't rely only on a default set from Settings. Each tab remembers its own explicit choice for as long as that tab stays open, even across a page refresh, and it is never silently changed by another tab picking something else.</p>
            <p>If a payment fails with a message like <em>"already has a payment in progress on another screen"</em>, or the terminal reports offline/auto-cancelled/an unexpected pairing, it means two tabs or computers ended up pointed at the <strong>same</strong> terminal at the same time — re-check the Terminal button in each tab and confirm they show two different terminals before trying again.</p>
        </div>

        <footer>
            SievesPOS is developed by SievesVision. For support, contact your SievesPOS administrator.
        </footer>
    </div>
</body>
</html>
