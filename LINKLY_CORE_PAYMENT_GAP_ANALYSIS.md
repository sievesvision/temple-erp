# Linkly Cloud REST — Core Payments Gap Analysis

**Product:** SievesPOS (this Laravel application's EFT Terminal / donation POS functionality)
**Integration type:** Linkly Cloud / REST API
**Feature set:** Core Payments only — no Tipping, Surcharging, Pay @ Table, or PLB@POS
**Target terminal:** CBA Essential Plus
**Status of this document:** Reflects the implementation as of this pass. Items marked
**LINKLY CONFIRMATION REQUIRED** are built on the best available documentation but have not
been independently verified against Linkly's current official accreditation script — confirm
with Linkly (or your onboarding contact) before submitting for accreditation.

## Where everything lives (Step 1 audit map)

| Concern | File |
|---|---|
| Linkly HTTP client (pairing, token, purchase, refund, logon, sendkey, reprint) | `app/Services/LinklyEftService.php` |
| Sandbox/Live credential + mode resolution | `app/Services/LinklyConfigService.php` |
| Terminal pairing (Settings page, Admin-only) | `app/Http/Controllers/LinklyController.php` |
| Purchase/poll/cancel/refund/logon/reprint/pairing orchestration + webhook | `app/Http/Controllers/DonationController.php` (`startEftCharge`, `pollEftCharge`, `cancelEftCharge`, `refundEftCharge`, `logonLinkly`, `reprintEftReceipt`, `pairEftFromConsole`, `linklyWebhook`) |
| Accreditation ledger (transaction references, audit trail) | `app/Models/LinklyTransaction.php`, migration `2026_09_30_000000_create_linkly_transactions_table.php` |
| POS kiosk purchase UI (pos-entry) | `resources/views/admin/event-pos-donation.blade.php` |
| Event Console EFTPOS pane (event-coordinator-admin) | `resources/views/admin/event-console.blade.php` (pane `pane-eftpos`) |
| Per-event coordinator level (pos/entry/admin) | `app/Services/EventCoordinatorLevel.php` |
| Resulting donation records | `donations` table (devotee), `donations_without_logins` table (guest) |
| Automated tests | `tests/Unit/LinklyDisplayParsingTest.php`, `tests/Feature/LinklyWebhookTest.php`, `tests/Feature/LinklyCorePaymentsTest.php` |

## Permission mapping

This app has no roles literally named `pos-entry` / `event-coordinator-admin`. Per your
instruction not to invent new roles, these map onto the existing per-event coordinator tiers
(`app/Services/EventCoordinatorLevel.php`), already used identically for every other
console capability:

- **pos-entry** → whoever can already reach the POS kiosk page and start a Purchase:
  Admin, Committee/Accountant (via the `donations`/`add` grid grant), or an Event
  Coordinator at `pos`/`entry` level for that specific event. Gate:
  `DonationController::canRecordDonation()`.
- **event-coordinator-admin** → Admin, Committee (via the `events`/`edit` grid grant), or
  an Event Coordinator at `admin` level for that specific event. Gate:
  `DonationController::canManageEftForEvent()` (new). This is the same tier that already
  gates the console's Settings/Coordinators/Logs panes — Refund, Logon, Reprint and
  terminal pairing now live in a new **EFTPOS** pane at that same tier, per your explicit
  instruction to put refund only on the event console page.

## Gap analysis by requirement

| # | Requirement | Status | Notes |
|---|---|---|---|
| 1 | Purchase (start → display → final result → receipt → donation record) | **PASS** | Async purchase flow already existed and was verified against real sandbox traffic in an earlier phase; unchanged here. |
| 2 | Unique POS-generated transaction reference per transaction | **PASS** (was MISSING) | `startEftCharge()`/`refundEftCharge()` now generate a `TxnRef` and persist it as `linkly_transactions.pos_txn_ref` (DB-unique). Previously generated but discarded after the call. |
| 3 | Double-click / duplicate-transaction protection | **PASS** (was PARTIAL) | JS already disabled the Pay button synchronously; now backed server-side by a client-generated `client_ref` idempotency key (`LinklyTransaction` lookup in `startEftCharge()`) — a resubmission with the same key resumes the existing Linkly session instead of starting a second one. Covered by `test_duplicate_start_with_same_client_ref_resumes_without_calling_linkly_again`. |
| 4 | Live DisplayText (async notifications, no hard-coded messages) | **PASS** | Built and confirmed live against the sandbox in an earlier phase (see prior session's production log evidence). Unchanged here. |
| 5 | Operator key flags (Cancel/OK/Yes/No/Authorise) captured and exposed | **PASS** (capture) / **PARTIAL** (exposure) | All five flags are parsed from the webhook and returned to the browser as `controls`. Only **Cancel** is wired to an actual button end-to-end (sendkey `"0"`); OK/Yes/No/Authorise are captured but have no UI/send-key action, since Core Payments' minimum only requires Cancel to work and building the other three without a concrete test scenario would be speculative. |
| 6 | Cancel uses the real Linkly sendkey operation, not just closing the modal | **PASS** | `LinklyEftService::sendKey()`/`cancel()` POST `Key: "0"` to `/v1/sessions/{sessionId}/sendkey`. |
| 7 | Refund, protected from unauthorised use | **PASS** (was MISSING) | `refundEftCharge()` requires event-coordinator-admin (`canManageEftForEvent()`), enforced server-side independent of any UI. Only an `approved` purchase can be refunded; blocked if already refunded or a refund is in flight. Runs a real Linkly transaction (TxnType `R`) with the original purchase's `TxnRef` sent back as the PAD `RFN` tag. |
| 8 | Refund audit trail (original payment, refund reference, amount, initiating/authorising user, result, timestamp) | **PASS** | All recorded on the new `linkly_transactions` row (`original_transaction_id`, `initiated_by`, `authorised_by`, `amount`, `status`, `response_code`/`response_text`/`auth_code`/`rrn`, `created_at`). In this minimal implementation the acting event-admin is recorded as both initiator and authoriser — there is no separate two-person maker/checker approval step. |
| 9 | Duplicate refund protection | **PASS** | Blocked server-side if a non-declined/failed/cancelled refund already exists for that purchase. Covered by `test_duplicate_refund_is_blocked`. |
| 10 | Logon | **PASS, but LINKLY CONFIRMATION REQUIRED** | Implemented (`LinklyEftService::logon()`, TxnType `L`, synchronous) and reachable from the EFTPOS pane. Linkly's published minimums (as found) list Purchase, Refund, Reprint Receipt and Transaction Status as Core Payments minimums but do not clearly list Logon as mandatory — built anyway since you asked for it, but its exact request body was inferred from the general request envelope pattern (Merchant/TxnType/Application), not independently confirmed. |
| 11 | Transaction reference visible in the admin view | **PASS** | EFTPOS pane's "Recent Linkly Transactions" table shows Type/Amount/Reference/Date-Time/Result/Response Code/Session ID with a one-click copy button (reference + timestamp), per your Step 14 spec. |
| 12 | Transaction recovery after browser/network interruption (never auto-decline, never double-charge) | **PASS** (was MISSING) | The same `client_ref` mechanism used for double-click protection also survives a page refresh: the POS page persists the in-flight attempt to `sessionStorage`, and on reload shows a "Resume Checking" banner rather than silently starting anything. Resuming reuses the same Linkly session. A transaction with no final result ~200s past Linkly's own window is reported `unknown` (not declined) and stops the poll loop; a fresh Purchase is not started automatically. **MANUAL TEST REQUIRED**: the actual browser-refresh UX (banner appears, Resume correctly re-attaches) needs to be exercised by hand — see the test plan. |
| 13 | Receipts (minimum Core Payments handling) | **PARTIAL** | Async `ResponseType: "receipt"` postbacks are now received and cached (`LinklyEftService::recordReceipt()`, PAN-pattern-masked defensively) rather than silently discarded as before. **Reprint Receipt** (`/reprintreceipt`) is implemented and reachable from the EFTPOS pane. What is **not** built: displaying the cached receipt text anywhere in the UI (the reprint button only confirms the terminal accepted the reprint request; the printed output is on the physical terminal, matching how a normal EFTPOS receipt reprint works). **LINKLY CONFIRMATION REQUIRED** on the exact `/reprintreceipt` request/response shape — built from documentation, not independently tested against the sandbox. |
| 14 | Basket Data | **NOT APPLICABLE** | Per the documentation available, Basket Data is not a Core Payments accreditation minimum for a POS that doesn't need itemised terminal receipts/loyalty. Not implemented — consistent with "do not implement optional features." |
| 15 | Mandatory Purchase Analysis Data (PAD) tags | **PASS, but LINKLY CONFIRMATION REQUIRED** | `OPR` (operator id\|name), `AMT` (total sale amount in cents, equal to `AmtPurchase` since this integration has no tips/surcharges), and `PCM` (POS Capabilities Matrix) are now sent on every Purchase and Refund (`LinklyEftService::corePad()`). `PCM` is hard-coded to `"0000"` (Linkly's own documented default for "no optional capabilities") — the exact meaning of each digit was not in the pages this was built from; re-confirm before accreditation. Refunds additionally carry `RFN` (the original purchase's own TxnRef). |
| 16 | Cloud security/connectivity (HTTPS/TLS, credentials server-side only) | **PASS** | All Linkly calls go through `Http::` server-side from `LinklyEftService`; base URLs are hard-coded `https://` Linkly Cloud hostnames; credentials/secret live in `.env`/`Setting`, never sent to the browser. No custom certificate handling — relies on PHP/cURL's default CA trust store (standard, not overridden anywhere in this codebase). |
| 17 | Never expose/log PAN, PIN, track data, credentials | **PASS** | Webhook handler only ever logs cleaned `DisplayText`; `transaction`/`receipt` postback bodies are never logged wholesale. Receipt text is additionally masked (8+ consecutive digits → `[MASKED]`) before caching, as defence in depth on top of Linkly's own receipt masking. Credentials never appear in any `Log::` call. |
| 18 | Minimal accreditation admin screen | **PASS** | Built as a new pane inside the existing Event Console (not a separate dashboard), per your explicit "refund is only on the event console page" instruction and Step 14's "do not build a large dashboard": Terminal/Pairing/Environment/Cloud ID, Pair/Repair, Logon, link to the POS Purchase screen, and the recent-transactions table with Refund/Reprint/Check-Status actions. |
| 19 | Pairing/repairing from the event-admin UI (not just system Settings) | **PASS** (was MISSING — explicit user request) | `pairEftFromConsole()` reuses the same `LinklyEftService::pair()` as the existing Admin-only Settings page, now also reachable by an event-admin coordinator from the EFTPOS pane. Pairing remains a single, whole-terminal action (one physical/virtual PIN pad, not one per event) — an event-admin for *any* event can re-pair the one shared terminal; this is a deliberate consequence of there being one physical terminal, not a scoping bug. |
| 20 | Cashier (pos-entry) vs admin (event-coordinator-admin) separation, enforced server-side | **PASS** | Every new endpoint (Refund/Logon/Reprint/Pair) checks `canManageEftForEvent()` independent of what any UI shows; a pos-entry-level user gets a 403 even if they somehow reach the URL directly. Covered by `test_entry_level_coordinator_cannot_refund` / `..._cannot_logon_or_pair`. |

## Explicitly not implemented (by design, per your instructions)

- Tipping, Surcharging, Pay @ Table, PLB@POS — not touched.
- OK/Yes/No/Authorise send-key buttons — flags captured, no send-key wiring (see row 5).
- A two-person maker/checker refund approval flow — only single-actor, permission-gated
  authorisation.
- Displaying Linkly's own receipt text anywhere in this app's UI — only reprinting to the
  physical terminal.
- Basket Data — confirmed not to be a Core Payments minimum requirement.
