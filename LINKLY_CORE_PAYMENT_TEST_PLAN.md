# Linkly Cloud REST — Core Payments Manual Test Plan

Run each test against Linkly's **sandbox** environment first (Settings → EFT Terminal, or
the Event Console's EFTPOS pane, `linkly_mode = sandbox`) with the virtual PIN pad, then
repeat the same script against a real **CBA Essential Plus** terminal in Linkly's test/
accreditation environment before submission. This plan maps to the requirements in
`LINKLY_CORE_PAYMENT_GAP_ANALYSIS.md` — it does not invent accreditation test cases beyond
what that gap analysis already identifies as in scope for Core Payments.

For every test, record the **Transaction Reference** (`pos_txn_ref`, shown in the EFTPOS
pane's table and copyable via the clipboard button) and the **Date/Time**, since Linkly's own
accreditation process asks for these to locate each test transaction.

---

### TEST 1 — Standard approved Purchase
**Required user:** pos-entry
**Prerequisites:** Terminal paired; event with EFT Terminal enabled as a payment method.
**POS steps:** Open the event's POS screen → enter donor + amount → select EFT Terminal → Pay → approve on the terminal (test card).
**Expected terminal behaviour:** Prompts for card, then PIN, then approves.
**Expected SievesPOS behaviour:** Modal shows each live prompt as it arrives (no fixed/guessed text); on approval shows "PAYMENT APPROVED" and the donation is saved as Paid.
**Evidence to record:** Transaction reference, date/time.
**PASS / FAIL:** ______

### TEST 2 — Declined Purchase
**Required user:** pos-entry
**POS steps:** Same as Test 1, using a test card/PIN that the sandbox declines.
**Expected terminal behaviour:** Declines with a reason.
**Expected SievesPOS behaviour:** Modal shows "PAYMENT DECLINED" with Linkly's own decline message; **no donation record is created**.
**Evidence to record:** Transaction reference, date/time, decline reason shown.
**PASS / FAIL:** ______

### TEST 3 — Single-line DisplayText
**Required user:** pos-entry
**POS steps:** Start a Purchase; observe the modal the moment the terminal shows a single-line prompt (e.g. before a card is presented).
**Expected SievesPOS behaviour:** Exactly that one line is shown, trimmed of padding, with no assumed/hard-coded alternative text.
**PASS / FAIL:** ______

### TEST 4 — Two-line DisplayText
**Required user:** pos-entry
**POS steps:** Use a test card that triggers an account-selection prompt (or any two-line terminal prompt the sandbox produces).
**Expected SievesPOS behaviour:** Both lines are shown together, not just the first.
**PASS / FAIL:** ______

### TEST 5 — CancelKeyFlag / Cancel via sendkey
**Required user:** pos-entry
**POS steps:** Start a Purchase; while the terminal is waiting (Cancel available), click "Cancel Payment" in the modal.
**Expected terminal behaviour:** The terminal itself backs out of the transaction (not just the browser closing its modal).
**Expected SievesPOS behaviour:** Toast confirms "Cancel sent to the terminal"; modal closes; no donation is recorded.
**Evidence to record:** Transaction reference, date/time, confirmation the terminal itself stopped.
**PASS / FAIL:** ______

### TEST 6 — Refund of an approved Purchase
**Required user:** event-coordinator-admin
**Prerequisites:** A completed approved Purchase from Test 1 (or a fresh one) visible in the EFTPOS pane's transaction table.
**POS steps:** Event Console → EFTPOS pane → click Refund on that row → confirm the amount → complete on the terminal (re-presenting the card if asked).
**Expected terminal behaviour:** Processes the refund and approves.
**Expected SievesPOS behaviour:** Modal shows live status, then "REFUND APPROVED"; a new row appears in the transactions table linked back to the original.
**Evidence to record:** Original transaction reference, refund transaction reference, date/time.
**PASS / FAIL:** ______

### TEST 7 — Duplicate refund is blocked
**Required user:** event-coordinator-admin
**Prerequisites:** The same purchase from Test 6, already refunded.
**POS steps:** Attempt to refund the same transaction again.
**Expected SievesPOS behaviour:** Blocked with a clear "already refunded" message; no second Linkly refund transaction is started.
**PASS / FAIL:** ______

### TEST 8 — Refund is not available to a pos-entry user
**Required user:** pos-entry (a cashier/entry-level account)
**POS steps:** Attempt to reach the Refund action (there is no Refund button on the POS kiosk screen at all; confirm the EFTPOS pane itself is not reachable for this account, and that directly requesting the refund URL is rejected).
**Expected SievesPOS behaviour:** No Refund UI is visible; a direct request is rejected (403) server-side.
**PASS / FAIL:** ______

### TEST 9 — Logon
**Required user:** event-coordinator-admin
**POS steps:** Event Console → EFTPOS pane → Logon.
**Expected terminal behaviour:** Terminal performs a logon exchange with the acquirer host.
**Expected SievesPOS behaviour:** Shows Linkly's actual result (success or failure) — never a faked "success".
**Evidence to record:** Date/time, result shown.
**PASS / FAIL:** ______

### TEST 10 — Reprint Receipt
**Required user:** event-coordinator-admin
**Prerequisites:** A completed Purchase or Refund transaction.
**POS steps:** EFTPOS pane → Reprint on that row.
**Expected terminal behaviour:** Terminal reprints its stored receipt.
**Expected SievesPOS behaviour:** Confirms the reprint request was accepted by Linkly.
**PASS / FAIL:** ______

### TEST 11 — Transaction Status / recovery check
**Required user:** event-coordinator-admin
**POS steps:** EFTPOS pane → "Check transaction status" on a completed row.
**Expected SievesPOS behaviour:** Re-confirms the stored result from Linkly's authoritative transaction status, without starting a new transaction.
**PASS / FAIL:** ______

### TEST 12 — Browser refresh during an in-progress Purchase (recovery)
**Required user:** pos-entry
**POS steps:** Start a Purchase; while the terminal is still waiting for the card/PIN, refresh the browser tab. Reload the POS page.
**Expected SievesPOS behaviour:** A banner appears: "A previous EFT Terminal payment did not finish… it may already be approved." Click "Resume Checking" — the modal reopens and resumes polling the *same* Linkly session (confirm via the transaction reference, unchanged). Complete the card/PIN entry on the terminal and confirm only **one** donation record is created (never two).
**Evidence to record:** Transaction reference (same before and after refresh), date/time.
**PASS / FAIL:** ______

### TEST 13 — Double-clicked Pay button
**Required user:** pos-entry
**POS steps:** Click Pay, then immediately click it again (or use browser dev tools to fire the request twice) before the terminal responds.
**Expected SievesPOS behaviour:** Only one Linkly transaction is started; the second click resumes the same one. Only one donation is ever recorded.
**PASS / FAIL:** ______

### TEST 14 — Unknown outcome is never treated as declined
**Required user:** pos-entry
**Prerequisites:** Simulated by leaving a transaction unresolved for several minutes (e.g. disconnect the terminal after Purchase starts, or let sandbox delay a response) — this may need to be arranged with Linkly's sandbox support, or driven by directly waiting out the ~3 minute window without completing the transaction on the terminal.
**Expected SievesPOS behaviour:** After the timeout, the modal shows "RESULT UNKNOWN — check before retrying", **not** "DECLINED"; the attempt is not automatically closed off, and no automatic retry/second charge occurs.
**PASS / FAIL:** ______

### TEST 15 — Unauthorised access to admin Linkly functions
**Required user:** pos-entry
**POS steps:** While logged in as a pos-entry-level account, attempt to directly request the Refund/Logon/Pair endpoints (e.g. via a saved link or browser console `fetch`).
**Expected SievesPOS behaviour:** Every one is rejected with 403, regardless of what the UI shows.
**PASS / FAIL:** ______

### TEST 16 — Pairing/repairing from the event console
**Required user:** event-coordinator-admin
**POS steps:** Generate a fresh pair code on the terminal/virtual PIN pad; enter it in the EFTPOS pane's Pair field; submit.
**Expected SievesPOS behaviour:** Confirms pairing succeeded; the Terminal/Pairing status updates to "Paired".
**PASS / FAIL:** ______

---

## Summary sheet (fill in after running all tests)

| Test | Transaction Ref | Date/Time | Result | Pass/Fail |
|---|---|---|---|---|
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |
| 4 | | | | |
| 5 | | | | |
| 6 | | | | |
| 7 | | | | |
| 8 | | | | |
| 9 | | | | |
| 10 | | | | |
| 11 | | | | |
| 12 | | | | |
| 13 | | | | |
| 14 | | | | |
| 15 | | | | |
| 16 | | | | |
