<?php

namespace App\Http\Controllers;

use App\Models\EftTerminal;
use App\Models\RolePermission;
use App\Models\SciTransaction;
use App\Services\AuditLogService;
use App\Services\CbaSciService;
use App\Services\EftTerminalAccess;
use App\Services\EventCoordinatorLevel;
use App\Services\TicketControllerLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pairing actions for CBA Smart Terminal (mx51 Simple Cloud Integration) terminals —
 * mirrors EftTerminalController's own access gate exactly (same "who can manage the
 * registry" question, provider-agnostic). Every action redirects back to wherever the
 * form was actually submitted from (plain redirect()->back(), same as LinklyController::
 * pair() and EftTerminalController's own setDefault()/checkConnection()/destroy()) — this
 * pairing block is @include'd verbatim wherever the terminal registry appears (the
 * standalone EFT Terminal Settings page, both consoles, and Admin Settings), so there's no
 * fixed "home" route to send the browser back to.
 */
class CbaSciController extends Controller
{
    private function canManageRegistry(): bool
    {
        $user = Auth::user();
        $activeRole = $user ? session('active_role', $user->role) : null;
        return EftTerminalAccess::canManageRegistry($user, $activeRole);
    }

    /**
     * Same admin-tier gate as DonationController::canManageEftForEvent()/TicketController::
     * canManageTicketConsole() — Core Payments requires refunds be protected from
     * unauthorised use, never available at pos-entry/general-entry level even though those
     * levels CAN start a purchase (see DonationController::canUseEftTerminal()). Unified into
     * one helper (rather than split event/ticket methods like the two Linkly controllers)
     * since a single CbaSciController::refund() endpoint serves both contexts: $eventId
     * present means "this was an event donation, check Event Coordinator admin-level";
     * null means "this was a ticket sale, check Ticket Controller admin-level" instead.
     */
    private function canManageRefund($user, ?string $activeRole, $eventId): bool
    {
        if (!$user) {
            return false;
        }

        if ($activeRole === 'Admin' || RolePermission::can($activeRole, 'events', 'edit') || RolePermission::can($activeRole, 'tickets', 'edit')) {
            return true;
        }

        if ($eventId && $activeRole === 'Event Coordinator') {
            return EventCoordinatorLevel::atLeast(EventCoordinatorLevel::of((int) $eventId, $user->id), 'admin');
        }

        if (!$eventId && $activeRole === 'Ticket Controller') {
            return TicketControllerLevel::atLeast(TicketControllerLevel::of($user->id), 'admin');
        }

        return false;
    }

    public function pair(Request $request)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'terminal_id' => 'required|exists:eft_terminals,id',
            'pairing_code' => 'required|string|max:20',
            'pairing_nickname' => 'nullable|string|max:255',
        ]);

        $terminal = EftTerminal::findOrFail($validated['terminal_id']);
        $result = CbaSciService::pair($validated['pairing_code'], $validated['pairing_nickname'] ?? null, $terminal);

        if ($result['success']) {
            AuditLogService::log("Paired mx51 Cloud terminal '{$terminal->label}' ({$terminal->key})");
        }

        // The re-pair widget on an already-registered terminal's own card (see
        // cba-sci-pairing.blade.php) calls this over fetch() so it can show the same
        // interactive confirmation-code step the Add Terminal wizard has, instead of landing
        // straight on the static "Paired successfully" view — CbaSciService::pair() itself is
        // unchanged, only the response format branches.
        if ($request->wantsJson()) {
            return response()->json(array_merge($result, [
                'confirmation_code' => $terminal->sci_confirmation_code,
            ]));
        }

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message'])->with('expandTerminalId', $terminal->id);
    }

    public function testPairing(Request $request)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate(['terminal_id' => 'required|exists:eft_terminals,id']);
        $terminal = EftTerminal::findOrFail($validated['terminal_id']);
        $result = CbaSciService::testPairing($terminal);

        // Deliberately does NOT auto-unpair on `still_paired === false` here, unlike
        // refreshPairingStatus() — mx51 returns the exact same no_active_pairings_found
        // response both for a pairing that's genuinely gone AND for one that's still waiting
        // on the admin to confirm the code on the physical terminal (no separate error code
        // exists to tell the two apart). Since Test is a manually-pressed, human-present
        // action — often pressed deliberately seconds after pairing, before the terminal has
        // even been touched — auto-unpairing here could destroy a pairing that's still mid
        // confirmation. The passive self-heal (refreshPairingStatus(), run on page load and
        // before a transaction, per SCIPAIRING10/SCIMULTI03) still clears a genuinely dead
        // pairing on its own; this button is purely informational.

        // The new Add Terminal wizard's confirmation screen calls this same endpoint over
        // fetch() rather than a form POST — the underlying CbaSciService::testPairing() call
        // above is unchanged either way, only the response format branches.
        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message'])->with('expandTerminalId', $terminal->id);
    }

    /**
     * Shared by two buttons on the pairing UI: the real "Unpair" action on an already-paired
     * terminal, and "Cancel" on the pairing form itself (see mx51's own certification
     * checklist, SCIPAIRING07 — cancelling must call this same Unpair endpoint and leave no
     * incomplete pairing record behind). Both end up here because CbaSciService::unpair()
     * already handles "nothing to unpair" gracefully; the only difference is the flash
     * message and whether it's audit-logged, decided by whether there was really a live
     * pairing to remove.
     */
    public function unpair(Request $request)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate(['terminal_id' => 'required|exists:eft_terminals,id']);
        $terminal = EftTerminal::findOrFail($validated['terminal_id']);
        $wasPaired = $terminal->isSciPaired();
        $result = CbaSciService::unpair($terminal);

        if ($result['success'] && $wasPaired) {
            AuditLogService::log("Unpaired mx51 Cloud terminal '{$terminal->label}' ({$terminal->key})");
        }

        $message = $result['success'] && !$wasPaired ? 'Pairing cancelled.' : $result['message'];

        // The re-pair widget's own Cancel button calls this over fetch() too (same mx51
        // certification requirement, SCIPAIRING07, as the Add Terminal wizard's Cancel).
        if ($request->wantsJson()) {
            return response()->json(['success' => $result['success'], 'message' => $message]);
        }

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $message)->with('expandTerminalId', $terminal->id);
    }

    /**
     * Starts a purchase — mirrors DonationController::startEftCharge()'s validation/
     * authorization/record_type shape closely so a future frontend can treat "which EFT
     * provider" as an implementation detail behind one consistent request/response contract.
     */
    public function startPurchase(Request $request)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        $recordType = $request->input('record_type', 'donation') === 'ticket_order' ? 'ticket_order' : 'donation';

        if (!$user || !app(DonationController::class)->canUseEftTerminal($user, $activeRole, $request->input('event_id'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            // Admin-configurable (EFT Terminal Settings) rather than a hardcoded 1 — see
            // App\Services\EftTransactionLimits.
            'amount' => 'required|numeric|min:' . \App\Services\EftTransactionLimits::minimumAmount(),
            'client_ref' => 'required|string|max:64',
            'event_id' => 'nullable|exists:events,event_id',
            'donor_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'purpose' => 'nullable|string|max:100',
            'purpose_details' => 'nullable|string|max:2000',
            'cart_json' => 'nullable|string',
            'terminal_id' => 'nullable|integer|exists:eft_terminals,id',
        ]);

        $terminal = EftTerminal::resolveOrDefault($validated['terminal_id'] ?? null);
        if (!$terminal || $terminal->provider !== 'cba_sci') {
            return response()->json(['success' => false, 'message' => 'No mx51 Cloud terminal is configured for this station.'], 422);
        }
        // The other of the two moments mx51's certification checklist requires a live
        // pairing-info check (see EftTerminalController::index() for the other one) — never
        // start charging against a pairing that's already been revoked on mx51's own side.
        if ($terminal->isSciPaired()) {
            \App\Services\CbaSciService::refreshPairingStatus($terminal);
        }
        if (!$terminal->isSciPaired()) {
            // mx51's own certification checklist (SCITX01) requires exactly this text — not a
            // paraphrase, and never the terminal's own label (which could itself carry mx51's
            // name, as "mx51 Certification Terminal" did here, leaking it into a customer-
            // facing error).
            return response()->json(['success' => false, 'message' => 'No active pairings found'], 422);
        }

        // Resume-in-place: an existing non-final attempt for this exact client_ref (a
        // refresh, or Pay clicked twice before the first request returned) hands back the
        // same mx51 transaction instead of starting a second one — identical reasoning to
        // startEftCharge()'s own resume check.
        $existing = SciTransaction::where('client_ref', $validated['client_ref'])
            ->whereNotIn('status', SciTransaction::FINAL_STATUSES)
            ->latest('id')
            ->first();
        if ($existing && $existing->sci_transaction_id) {
            return response()->json([
                'success' => true, 'message' => 'Resuming existing transaction.', 'resumed' => true,
                'transaction_id' => $existing->sci_transaction_id, 'version' => $existing->sci_version,
                'status' => $existing->status, 'pos_instructions' => $existing->pos_instructions,
            ]);
        }

        $result = CbaSciService::createPurchase($terminal, (float) $validated['amount']);

        if ($result['success']) {
            $meta = $recordType === 'ticket_order'
                ? [
                    'record_type' => 'ticket_order',
                    'cart' => json_decode($validated['cart_json'] ?? '[]', true) ?: [],
                    'customer_name' => $validated['donor_name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'mobile' => $validated['mobile'] ?? null,
                ]
                : [
                    'record_type' => 'donation',
                    'donor_name' => $validated['donor_name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'mobile' => $validated['mobile'] ?? null,
                    'purpose' => $validated['purpose'] ?? null,
                    'purpose_details' => $validated['purpose_details'] ?? null,
                ];

            SciTransaction::create([
                'client_ref' => $validated['client_ref'],
                'sci_transaction_id' => $result['transaction_id'],
                'sci_version' => $result['version'],
                'event_id' => $validated['event_id'] ?? null,
                'eft_terminal_id' => $terminal->id,
                'txn_type' => 'purchase',
                'amount' => $validated['amount'],
                'status' => $result['status'],
                'pos_instructions' => $result['pos_instructions'],
                'message' => $result['message'],
                'initiated_by' => $user->id,
                'meta' => $meta,
            ]);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Resolves the terminal from the SciTransaction row itself, never the caller's current
     * selection — same defensive reasoning as DonationController::resolveTerminalForSession().
     */
    private function resolveSciTransaction(string $transactionId): ?SciTransaction
    {
        return SciTransaction::where('sci_transaction_id', $transactionId)->latest('id')->first();
    }

    public function poll(Request $request, string $transactionId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$user || !app(DonationController::class)->canUseEftTerminal($user, $activeRole, $request->input('event_id'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $txn = $this->resolveSciTransaction($transactionId);
        if (!$txn || !$txn->eftTerminal) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        // Never re-query mx51 for a transaction already finalised locally (a normal poll
        // reaching FINALISED, or a manual override) — mirrors override()'s own "never let a
        // later call contradict an existing result" guard. Without this, a stray poll (a
        // request already in flight when the client stopped polling, another open tab, a
        // resumed session) could get back something like DEVICE_NOT_CONNECTED for a
        // transaction mx51 itself has already forgotten about, and silently un-finalise a
        // transaction that was correctly resolved — reopening exactly the stuck-popup/
        // resume-loop class of bug this is meant to prevent.
        if (in_array($txn->status, SciTransaction::FINAL_STATUSES, true)) {
            return response()->json([
                'done' => true,
                'success' => $txn->result_financial_status === 'APPROVED',
                'status' => $txn->status,
                'message' => $txn->message,
                'pos_instructions' => $txn->pos_instructions,
                'result_financial_status' => $txn->result_financial_status,
                'transient_error' => false,
                'donation_id' => $txn->donation_id,
            ]);
        }

        // mx51's own documented rule: min_version is always the last CONFIRMED version + 1
        // (never self-incremented from a previous request value) — sci_version stores the
        // actual last-known version, not a pre-computed min_version, so that +1 happens here
        // on every call rather than compounding across polls. The row's own last-seen
        // version is the only trustworthy basis for it — never the client's, which could be
        // stale (a second tab, a slow request) and would otherwise risk re-processing an
        // already-superseded response.
        $result = CbaSciService::pollTransaction($txn->eftTerminal, $transactionId, $txn->sci_version + 1);

        $txn->update(array_filter([
            'sci_version' => $result['version'],
            'status' => $result['status'] ?? $txn->status,
            'pos_instructions' => $result['pos_instructions'],
            'message' => $result['message'],
            'result_financial_status' => $result['result_financial_status'],
            'result_amounts' => $result['result_amounts'],
            'result_card_details' => $result['result_card_details'],
            'merchant_receipt' => $result['merchant_receipt'],
            'customer_receipt' => $result['customer_receipt'],
        ], fn ($v) => $v !== null));

        $fresh = $txn->fresh();
        if ($fresh->txn_type === 'refund') {
            $this->markDonationCancelledIfRefundJustApproved($fresh);
            $donationId = $fresh->donation_id;
        } else {
            $donationId = $this->createRecordIfApprovedAndUnrecorded($fresh);
        }

        return response()->json([
            'done' => $result['done'],
            'success' => $result['success'],
            'status' => $result['status'],
            'message' => $result['message'],
            'pos_instructions' => $result['pos_instructions'],
            'result_financial_status' => $result['result_financial_status'],
            'transient_error' => $result['transient_error'],
            'donation_id' => $donationId,
        ]);
    }

    /**
     * Requests mx51 cancel a transaction still in progress — see CbaSciService::
     * cancelTransaction()'s docblock for why this alone doesn't resolve the outcome; the
     * frontend keeps polling afterward exactly as it already does for any other transaction.
     */
    public function cancel(Request $request, string $transactionId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$user || !app(DonationController::class)->canUseEftTerminal($user, $activeRole, $request->input('event_id'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $txn = $this->resolveSciTransaction($transactionId);
        if (!$txn || !$txn->eftTerminal) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        $result = CbaSciService::cancelTransaction($txn->eftTerminal, $transactionId);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Starts a refund for a completed mx51 purchase — mirrors DonationController::
     * refundEftCharge()/TicketController::refundEftCharge() exactly (admin-only gate,
     * duplicate-refund protection, amount capped at the original, forced back through the
     * SAME terminal that took the original payment), but unified across event/ticket
     * contexts since CbaSciService::createRefund() is provider-generic. Runs through the
     * same async start+poll+Action-Framework modal flow as a purchase (see startPurchase()/
     * poll()), since a refund is a real terminal transaction, not a database edit.
     */
    public function refund(Request $request, string $transactionId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        $original = SciTransaction::where('sci_transaction_id', $transactionId)
            ->where('txn_type', 'purchase')
            ->latest('id')
            ->first();
        if (!$original) {
            return response()->json(['success' => false, 'message' => 'Original transaction not found.'], 404);
        }

        if (!$this->canManageRefund($user, $activeRole, $original->event_id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        if ($original->status !== 'FINALISED' || $original->result_financial_status !== 'APPROVED') {
            return response()->json(['success' => false, 'message' => 'Only an approved purchase can be refunded.'], 422);
        }

        // Duplicate-refund protection: block a second attempt while one is already in
        // flight (not yet FINALISED) or has already succeeded (FINALISED + APPROVED) for
        // this same purchase — mirrors the Linkly controllers' own
        // initiated/in_progress/approved check, translated to mx51's own status vocabulary.
        $alreadyRefunded = SciTransaction::where('original_transaction_id', $original->id)
            ->where(function ($q) {
                $q->whereNotIn('status', SciTransaction::FINAL_STATUSES)
                    ->orWhere('result_financial_status', 'APPROVED');
            })
            ->exists();
        if ($alreadyRefunded) {
            return response()->json(['success' => false, 'message' => 'This transaction has already been refunded, or a refund is already in progress.'], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . (float) $original->amount,
            'client_ref' => 'required|string|max:64',
        ]);

        // A refund must return through the SAME terminal that took the original payment —
        // never whatever terminal the caller happens to have selected right now.
        $terminal = $original->eftTerminal;
        if (!$terminal) {
            return response()->json(['success' => false, 'message' => 'No EFT terminal is configured yet.'], 422);
        }

        $result = CbaSciService::createRefund($terminal, (float) $validated['amount']);

        if ($result['success']) {
            SciTransaction::create([
                'client_ref' => $validated['client_ref'],
                'sci_transaction_id' => $result['transaction_id'],
                'sci_version' => $result['version'],
                'event_id' => $original->event_id,
                'eft_terminal_id' => $terminal->id,
                'donation_type' => $original->donation_type,
                'donation_id' => $original->donation_id,
                'original_transaction_id' => $original->id,
                'txn_type' => 'refund',
                'amount' => $validated['amount'],
                'status' => $result['status'],
                'pos_instructions' => $result['pos_instructions'],
                'message' => $result['message'],
                'initiated_by' => $user->id,
                'authorised_by' => $user->id,
            ]);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function submitAction(Request $request, string $transactionId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$user || !app(DonationController::class)->canUseEftTerminal($user, $activeRole, $request->input('event_id'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'submit_url' => 'required|string|url',
            'form_values' => 'nullable|array',
        ]);

        $txn = $this->resolveSciTransaction($transactionId);
        if (!$txn || !$txn->eftTerminal) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        $result = CbaSciService::submitAction($txn->eftTerminal, $validated['submit_url'], $validated['form_values'] ?? []);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * The transaction-recovery "override" flow (mx51's own docs have no API endpoint for
     * this — it's explicitly a POS-local decision): when polling has failed or timed out for
     * too long, the merchant is asked "Was the transaction successful?" and answers on the
     * POS's behalf. "Yes" records the sale exactly as a normal approval would; "No" leaves it
     * genuinely unresolved (never a hard decline — the card may still have been charged) so a
     * later real answer from mx51, or a human reconciling the statement, isn't contradicted
     * by a confident-looking but manufactured DECLINED.
     */
    public function override(Request $request, string $transactionId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$user || !app(DonationController::class)->canUseEftTerminal($user, $activeRole, $request->input('event_id'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate(['outcome' => 'required|in:approved,unresolved']);

        $txn = $this->resolveSciTransaction($transactionId);
        if (!$txn) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        if (in_array($txn->status, SciTransaction::FINAL_STATUSES, true)) {
            // A real answer arrived after all — never let a manual override contradict it.
            return response()->json([
                'success' => true, 'message' => 'A result was already received for this transaction.',
                'status' => $txn->status, 'result_financial_status' => $txn->result_financial_status,
                'donation_id' => $txn->donation_id,
            ]);
        }

        $txn->update([
            'status' => 'FINALISED',
            'result_financial_status' => $validated['outcome'] === 'approved' ? 'APPROVED' : 'UNKNOWN',
            'meta' => array_merge($txn->meta ?? [], [
                'override' => ['by' => $user->id, 'outcome' => $validated['outcome'], 'at' => now()->toIso8601String()],
            ]),
        ]);

        $fresh = $txn->fresh();
        if ($validated['outcome'] !== 'approved') {
            $donationId = null;
        } elseif ($fresh->txn_type === 'refund') {
            $this->markDonationCancelledIfRefundJustApproved($fresh);
            $donationId = $fresh->donation_id;
        } else {
            $donationId = $this->createRecordIfApprovedAndUnrecorded($fresh);
        }

        AuditLogService::log(
            "Manually overrode mx51 Cloud transaction {$transactionId} as {$validated['outcome']}",
            $user->id,
            $txn->event_id
        );

        return response()->json(['success' => true, 'message' => 'Recorded.', 'donation_id' => $donationId]);
    }

    /**
     * Marks the underlying donation/order row 'Cancelled' once a refund transaction is
     * confirmed approved — exact mirror of DonationController::
     * markDonationCancelledIfRefundJustApproved(), including reusing the existing
     * 'Cancelled' status rather than inventing a new 'Refunded' one (see that method's own
     * docblock for why). Called from poll() right after the row is updated with mx51's
     * latest result, so the temple's own totals stop counting it without a separate step.
     */
    private function markDonationCancelledIfRefundJustApproved(SciTransaction $txn): void
    {
        if ($txn->status !== 'FINALISED' || $txn->result_financial_status !== 'APPROVED') {
            return;
        }
        if (!$txn->donation_type || !$txn->donation_id) {
            return;
        }

        $table = match ($txn->donation_type) {
            'devotee' => 'donations',
            'ticket_order' => 'ticket_orders',
            default => 'donations_without_logins',
        };
        DB::table($table)->where('id', $txn->donation_id)->update(['payment_status' => 'Cancelled', 'updated_at' => now()]);
    }

    /**
     * Server-side safety net mirroring DonationController::createDonationIfApprovedPurchase
     * Unrecorded() exactly — an approved transaction whose ledger row has no linked donation/
     * order yet (the browser never got the chance to record it) gets recorded here instead,
     * from the donor/cart details captured at startPurchase() time. Runs on every poll and
     * on a "Yes" override, not just once, so an approved charge is never left unrecorded.
     */
    private function createRecordIfApprovedAndUnrecorded(SciTransaction $txn): ?int
    {
        if ($txn->status !== 'FINALISED' || $txn->result_financial_status !== 'APPROVED') {
            return null;
        }
        if ($txn->donation_id) {
            return $txn->donation_id;
        }

        $meta = $txn->meta ?? [];
        $transactionRef = $txn->sci_transaction_id;

        if (($meta['record_type'] ?? 'donation') === 'ticket_order') {
            if (empty($meta['cart'])) {
                return null;
            }
            $orderId = app(TicketController::class)->createOrderFromLedgerMeta($meta, $transactionRef);
            if ($orderId) {
                $txn->update(['donation_type' => 'ticket_order', 'donation_id' => $orderId]);
            }
            return $orderId;
        }

        if (empty($meta['donor_name'])) {
            return null;
        }

        $donationId = app(DonationController::class)->insertGuestDonationRecord([
            'donor_name' => $meta['donor_name'],
            'event_id' => $txn->event_id,
            'email' => $meta['email'] ?? null,
            'mobile' => $meta['mobile'] ?? null,
            'amount' => $txn->amount,
            'purpose' => $meta['purpose'] ?? 'General Donation',
            'purpose_details' => $meta['purpose_details'] ?? null,
            'payment_method' => 'EFT Terminal',
            'payment_status' => 'Paid',
            'transaction_id' => $transactionRef,
            'donation_date' => now()->toDateString(),
        ]);
        $txn->update(['donation_type' => 'guest', 'donation_id' => $donationId]);

        return $donationId;
    }
}
