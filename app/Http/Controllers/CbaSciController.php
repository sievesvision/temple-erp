<?php

namespace App\Http\Controllers;

use App\Models\EftTerminal;
use App\Models\RolePermission;
use App\Models\SciTransaction;
use App\Services\AuditLogService;
use App\Services\CbaSciService;
use App\Services\EftTerminalAccess;
use App\Services\TicketControllerLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pairing actions for CBA Smart Terminal (mx51 Simple Cloud Integration) terminals —
 * mirrors EftTerminalController's own access gate exactly (same "who can manage the
 * registry" question, provider-agnostic), and redirects back the same "return_context"
 * way, since this pairing block sits on the very same pages EftTerminalController's own
 * Linkly pairing form does.
 */
class CbaSciController extends Controller
{
    private function canManageRegistry(): bool
    {
        $user = Auth::user();
        $activeRole = $user ? session('active_role', $user->role) : null;
        return EftTerminalAccess::canManageRegistry($user, $activeRole);
    }

    private function redirectAfterAction(Request $request)
    {
        $context = $request->input('return_context');

        if ($context === 'ticket-console') {
            return redirect()->route('admin.tickets.index');
        }

        if (is_string($context) && str_starts_with($context, 'event-console:')) {
            $eventId = substr($context, strlen('event-console:'));
            if (ctype_digit($eventId)) {
                return redirect()->route('admin.events.console', ['event' => $eventId]);
            }
        }

        return redirect()->route('admin.eft-terminals.index');
    }

    public function pair(Request $request)
    {
        if (!$this->canManageRegistry()) {
            return $this->redirectAfterAction($request)->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'terminal_id' => 'required|exists:eft_terminals,id',
            'pairing_code' => 'required|string|max:20',
            'pairing_nickname' => 'nullable|string|max:255',
        ]);

        $terminal = EftTerminal::findOrFail($validated['terminal_id']);
        $result = CbaSciService::pair($validated['pairing_code'], $validated['pairing_nickname'] ?? null, $terminal);

        if ($result['success']) {
            AuditLogService::log("Paired CBA Smart Terminal '{$terminal->label}' ({$terminal->key})");
        }

        return $this->redirectAfterAction($request)->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function testPairing(Request $request)
    {
        if (!$this->canManageRegistry()) {
            return $this->redirectAfterAction($request)->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate(['terminal_id' => 'required|exists:eft_terminals,id']);
        $terminal = EftTerminal::findOrFail($validated['terminal_id']);
        $result = CbaSciService::testPairing($terminal);

        return $this->redirectAfterAction($request)->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function unpair(Request $request)
    {
        if (!$this->canManageRegistry()) {
            return $this->redirectAfterAction($request)->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate(['terminal_id' => 'required|exists:eft_terminals,id']);
        $terminal = EftTerminal::findOrFail($validated['terminal_id']);
        $result = CbaSciService::unpair($terminal);

        if ($result['success']) {
            AuditLogService::log("Unpaired CBA Smart Terminal '{$terminal->label}' ({$terminal->key})");
        }

        return $this->redirectAfterAction($request)->with($result['success'] ? 'success' : 'error', $result['message']);
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
            'amount' => 'required|numeric|min:1',
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
            return response()->json(['success' => false, 'message' => 'No CBA Smart Terminal is configured for this station.'], 422);
        }
        if (!$terminal->isSciPaired()) {
            return response()->json(['success' => false, 'message' => "\"{$terminal->label}\" is not paired yet."], 422);
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

        // The row's own last-seen version is the only trustworthy min_version — never the
        // client's, which could be stale (a second tab, a slow request) and would otherwise
        // risk re-processing an already-superseded response.
        $result = CbaSciService::pollTransaction($txn->eftTerminal, $transactionId, $txn->sci_version);

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

        $donationId = $this->createRecordIfApprovedAndUnrecorded($txn->fresh());

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
            return response()->json(['success' => true, 'message' => 'A result was already received for this transaction.', 'status' => $txn->status, 'result_financial_status' => $txn->result_financial_status]);
        }

        $txn->update([
            'status' => 'FINALISED',
            'result_financial_status' => $validated['outcome'] === 'approved' ? 'APPROVED' : 'UNKNOWN',
            'meta' => array_merge($txn->meta ?? [], [
                'override' => ['by' => $user->id, 'outcome' => $validated['outcome'], 'at' => now()->toIso8601String()],
            ]),
        ]);

        $donationId = $validated['outcome'] === 'approved' ? $this->createRecordIfApprovedAndUnrecorded($txn->fresh()) : null;

        AuditLogService::log(
            "Manually overrode CBA Smart Terminal transaction {$transactionId} as {$validated['outcome']}",
            $user->id,
            $txn->event_id
        );

        return response()->json(['success' => true, 'message' => 'Recorded.', 'donation_id' => $donationId]);
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
