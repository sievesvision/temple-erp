<?php

namespace App\Http\Controllers;

use App\Models\EftTerminal;
use App\Models\LinklyTransaction;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\EftTerminalAccess;
use App\Services\LinklyEftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The standalone "EFT Terminal Settings" page — deliberately its own page rather than a
 * panel on the main Settings page, since the main Settings page is gated by the 'settings'
 * RolePermission resource (typically Admin/Committee only), while an event-admin coordinator
 * or ticket-admin controller already has full pairing/refund/logon rights over any terminal
 * from their own console and needs to be able to register a *new* one too — see
 * App\Services\EftTerminalAccess for exactly who that is. "Set default" and "remove a
 * terminal" now share that same broader access too (anyone who can manage the registry can
 * change or remove any terminal in it — the registry is still one shared, global list, not
 * scoped per event/Tickets), since the destinations that rely on a terminal already trust
 * those same roles for its day-to-day operation. System-Admin-only is now exclusively about
 * the provider-wide sandbox/live switch (updateMode()) — going live moves real money for
 * every terminal of that provider, which is a different order of consequence entirely.
 */
class EftTerminalController extends Controller
{
    private function isAdmin(): bool
    {
        $user = Auth::user();
        return $user && $user->role === 'Admin';
    }

    private function canManageRegistry(): bool
    {
        $user = Auth::user();
        $activeRole = $user ? session('active_role', $user->role) : null;
        return EftTerminalAccess::canManageRegistry($user, $activeRole);
    }

    public function index(Request $request)
    {
        if (!$this->canManageRegistry()) {
            abort(403, 'Unauthorized access.');
        }

        ['eftTerminals' => $eftTerminals, 'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode] = \App\Services\EftTerminalRegistryView::fetch();
        $canManageRegistryLevel = $this->canManageRegistry();
        $isSystemAdmin = $this->isAdmin();
        // Embedded in an iframe popup from a POS page (see eft-terminal-settings-modal.blade.
        // php) — kiosk-mode browsers and single-window POS setups can't open a new tab, so
        // this is how they reach pairing without leaving the page they're on. The consoles and
        // Admin Settings no longer use this iframe at all — they @include the same registry
        // partial this page renders, directly in their own template — so $embedded now only
        // ever means "inside that POS-page popup," and the whole topbar (not just Back/
        // Logout) is hidden in favour of the popup's own title bar and close control.
        $embedded = $request->boolean('embedded');

        // Viewing this page is one of the two moments mx51's own certification checklist
        // requires a live pairing-info check (the other is just before a transaction starts,
        // see CbaSciController::startPurchase()) — a mx51 terminal marked paired locally gets
        // silently self-corrected here if it's actually been unpaired on mx51's own side. This
        // is deliberately only done here, not inside EftTerminalRegistryView::data() itself —
        // a live mx51 API round-trip per terminal isn't something the consoles or Admin
        // Settings should pay for on every ordinary page load.
        foreach ($eftTerminals as $eftTerminal) {
            if ($eftTerminal->provider === 'cba_sci' && $eftTerminal->isSciPaired()) {
                \App\Services\CbaSciService::refreshPairingStatus($eftTerminal);
            }
        }

        ['activeTerminals' => $activeTerminals, 'inactiveTerminals' => $inactiveTerminals, 'allOperational' => $allOperational]
            = \App\Services\EftTerminalRegistryView::groups($eftTerminals, $linklyMode);

        // The Add Terminal wizard re-fetches just the registry list (not a full page
        // navigation) once it's done — same data this method already builds above, just
        // rendered as the bare partial instead of the full page shell.
        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('admin.partials.eft-terminal-registry', compact('activeTerminals', 'inactiveTerminals', 'linklyMode', 'cbaSciMode', 'canManageRegistryLevel', 'isSystemAdmin', 'allOperational'))->render(),
            ]);
        }

        return view('admin.eft-terminal-settings', compact('activeTerminals', 'inactiveTerminals', 'linklyMode', 'cbaSciMode', 'canManageRegistryLevel', 'isSystemAdmin', 'allOperational', 'embedded'));
    }

    /**
     * Switches a provider's sandbox/live environment — System-Admin-only, since going live
     * means every subsequent transaction on that provider's terminals moves real money.
     * Deliberately provider-scoped rather than one global switch: Linkly and mx51 already
     * carry their own independent mode (see index()'s $cbaSciMode vs $linklyMode), so one
     * provider can go live while the other stays in sandbox for ongoing testing.
     */
    public function updateMode(Request $request)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'provider' => 'required|in:linkly,cba_sci',
            'mode' => 'required|in:sandbox,live',
        ]);

        $settingKey = $validated['provider'] === 'cba_sci' ? 'cba_sci_mode' : 'linkly_mode';
        $providerLabel = $validated['provider'] === 'cba_sci' ? 'SCI' : 'Linkly Cloud';
        Setting::set($settingKey, $validated['mode']);

        AuditLogService::log("Switched {$providerLabel} to " . strtoupper($validated['mode']) . ' mode');

        return redirect()->back()->with('success', "{$providerLabel} is now in " . strtoupper($validated['mode']) . ' mode.');
    }

    /**
     * Receipt printing / signature-verification preferences — common storage across every EFT
     * provider (see App\Services\EftReceiptSettings), sent to mx51 on every transaction it
     * creates (CbaSciService::createTransaction()). Linkly doesn't take these yet. System-
     * Admin-only, same tier as the sandbox/live switch above — this changes how every
     * station's receipts and signature verification behave, not a single terminal's own
     * setup.
     */
    public function updateReceiptSettings(Request $request)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        Setting::set('eft_print_merchant_receipt_on_terminal', $request->boolean('print_merchant_receipt_on_terminal') ? '1' : '0');
        Setting::set('eft_prompt_customer_receipt_on_terminal', $request->boolean('prompt_customer_receipt_on_terminal') ? '1' : '0');
        Setting::set('eft_verify_signature_on_terminal', $request->boolean('verify_signature_on_terminal') ? '1' : '0');
        Setting::set('eft_pos_auto_print_signature_receipt', $request->boolean('pos_auto_print_signature_receipt') ? '1' : '0');

        AuditLogService::log('Updated EFT receipt printing / signature verification settings');

        return redirect()->back()->with('success', 'Receipt printing settings saved.');
    }

    /**
     * The minimum amount an operator can start an EFT Terminal purchase for (see
     * App\Services\EftTransactionLimits) — was a hardcoded "must be at least 1" on both
     * providers' own start-purchase validation. System-Admin-only, same tier as the other
     * settings cards here.
     */
    public function updateTransactionLimits(Request $request)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'minimum_transaction_amount' => 'required|numeric|min:0',
        ]);

        Setting::set('eft_minimum_transaction_amount', (string) $validated['minimum_transaction_amount']);

        AuditLogService::log('Updated the minimum EFT Terminal transaction amount to ' . $validated['minimum_transaction_amount']);

        return redirect()->back()->with('success', 'Transaction limits saved.');
    }

    public function store(Request $request)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'key' => 'required|string|max:40|alpha_dash|unique:eft_terminals,key',
            'label' => 'required|string|max:255',
            'provider' => 'nullable|in:linkly,cba_sci',
        ]);

        $terminal = EftTerminal::create([
            'key' => $validated['key'],
            'label' => $validated['label'],
            'provider' => $validated['provider'] ?? 'linkly',
            'pos_id' => EftTerminal::generatePosId(),
            'is_default' => !EftTerminal::query()->exists(),
        ]);

        AuditLogService::log("Added EFT terminal '{$terminal->label}' ({$terminal->key})");

        // The terminal's own detail view (pairing form etc.) is collapsed by default on this
        // page — expand just the one you were actually working with instead of leaving you to
        // find and re-click it.
        return redirect()->back()
            ->with('success', "Terminal \"{$terminal->label}\" added — pair it below.")
            ->with('expandTerminalId', $terminal->id);
    }

    /**
     * One-step "Add Terminal" — combines terminal creation with an immediate pairing attempt,
     * mirroring mx51's own merchant-portal UX (choose an integration type, enter pairing
     * details, press Pair once) rather than the older two-step create-then-separately-pair
     * flow store() above still backs for any other caller. Always responds JSON — this is a
     * new endpoint with no legacy form-POST caller to stay compatible with. On any failure the
     * just-created row is deleted, so a failed pairing attempt never leaves a dangling
     * "key already taken" terminal behind for the next retry.
     */
    public function addAndPair(Request $request)
    {
        if (!$this->canManageRegistry()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'key' => 'required|string|max:40|alpha_dash|unique:eft_terminals,key',
            'pairing_nickname' => 'nullable|string|max:255',
            'provider' => 'required|in:linkly,cba_sci',
            'pairing_code' => 'required|string|max:20',
        ]);

        $pairingNickname = $validated['pairing_nickname'] ?? null;

        $terminal = EftTerminal::create([
            'key' => $validated['key'],
            'label' => $pairingNickname !== null && $pairingNickname !== '' ? $pairingNickname : $validated['key'],
            'provider' => $validated['provider'],
            'pos_id' => EftTerminal::generatePosId(),
            'is_default' => !EftTerminal::query()->exists(),
        ]);

        $result = $validated['provider'] === 'cba_sci'
            ? \App\Services\CbaSciService::pair($validated['pairing_code'], $pairingNickname, $terminal)
            : LinklyEftService::pair($validated['pairing_code'], $terminal);

        if (!$result['success']) {
            // forceDelete(), not delete() — this row never succeeded at anything (no
            // transaction, no completed pairing), so there's no history worth a soft delete's
            // protection (see EftTerminal's SoftDeletes trait / EftTerminalController::
            // destroy()'s own docblock). It also has to be a real delete: the 'key' field's
            // unique validation above doesn't know about soft-deletes, so a merely
            // soft-deleted row would keep blocking the exact retry this comment describes.
            $terminal->forceDelete();
            return response()->json(['success' => false, 'message' => $result['message']]);
        }

        AuditLogService::log("Added and paired EFT terminal '{$terminal->label}' ({$terminal->key})");

        $terminal->refresh();

        return response()->json([
            'success' => true,
            'terminal_id' => $terminal->id,
            'label' => $terminal->label,
            // mx51 pairing isn't considered confirmed until the admin visually cross-checks
            // the confirmation code against the terminal and presses Test — Linkly's pairing
            // API has no such concept and is already fully live at this point.
            'requires_confirmation' => $validated['provider'] === 'cba_sci',
            'confirmation_code' => $terminal->sci_confirmation_code,
            'tid' => $terminal->sci_tid,
        ]);
    }

    /**
     * Cancel button on the new Add Terminal wizard's mx51 confirmation screen — calls Unpair
     * (per mx51's own certification checklist, SCIPAIRING07, cancelling a pairing attempt must
     * call Unpair too) then deletes the terminal entirely, since this row only exists as part
     * of this one still-unconfirmed wizard run. Uses the broader canManageRegistry() tier
     * rather than destroy()'s System-Admin-only gate, since anyone who could start this wizard
     * should be able to back out of it — safe because it only ever removes a terminal with
     * zero recorded transactions, true by construction for a brand new row.
     */
    public function cancelNewTerminal(Request $request, EftTerminal $terminal)
    {
        if (!$this->canManageRegistry()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        if ($terminal->linklyTransactions()->exists() || $terminal->sciTransactions()->exists()) {
            return response()->json(['success' => false, 'message' => 'This terminal already has recorded activity and cannot be cancelled this way.'], 422);
        }

        if ($terminal->isSciPaired()) {
            \App\Services\CbaSciService::unpair($terminal);
        }

        // forceDelete() — the guard above already proved zero recorded transactions, so unlike
        // destroy()'s soft delete there's no history worth preserving here, and the 'key'
        // field needs to be genuinely free again for a retry (its unique validation in
        // addAndPair()/store() doesn't know about soft-deletes).
        $label = $terminal->label;
        $terminal->forceDelete();

        AuditLogService::log("Cancelled pairing and removed EFT terminal '{$label}'");

        return response()->json(['success' => true]);
    }

    /**
     * Renames a terminal's display label — added after mx51's certification review flagged
     * that a terminal's own label (e.g. "mx51 Certification Terminal") had no way to be
     * changed, and was leaking into customer-facing error messages that embedded it (see
     * startPurchase()'s "No active pairings found" fix). The terminal Key stays immutable —
     * it's a stable identifier referenced elsewhere, unlike the Label, which is purely a
     * display name.
     */
    public function update(Request $request, EftTerminal $terminal)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $oldLabel = $terminal->label;
        $terminal->update(['label' => $validated['label']]);

        AuditLogService::log("Renamed EFT terminal '{$oldLabel}' to '{$terminal->label}' ({$terminal->key})");

        return redirect()->back()
            ->with('success', "Terminal renamed to \"{$terminal->label}\".")
            ->with('expandTerminalId', $terminal->id);
    }

    public function setDefault(Request $request, EftTerminal $terminal)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        EftTerminal::where('id', '!=', $terminal->id)->update(['is_default' => false]);
        $terminal->update(['is_default' => true]);

        AuditLogService::log("Set '{$terminal->label}' as the default EFT terminal");

        return redirect()->back()->with('success', "\"{$terminal->label}\" is now the default terminal.")->with('expandTerminalId', $terminal->id);
    }

    /**
     * Fired by a POS page's terminal picker (event-pos-donation.blade.php / ticket-pos.
     * blade.php) the moment an operator picks a terminal there — saved here, against their
     * own account, alongside that picker's existing browser-storage save, so the choice
     * follows the *user* rather than the device: two staff sharing one POS computer, or one
     * staff member moving between computers, each still land on their own terminal next time.
     * Also the one-write fallback for EftTerminal::resolveOrDefault() (every charge-starting
     * controller action) — this fetch is a fast path, not the only path, so even a client
     * that skips it still gets remembered correctly the moment a charge actually starts.
     *
     * Deliberately canManageRegistry(), not isAdmin() — same gate as the rest of this picker
     * (including 'pos'-level Event Coordinators and Ticket Controllers), since this only ever
     * writes the CALLING user's own row and so carries none of setDefault()'s cross-user
     * blast radius.
     */
    public function selectForMe(Request $request, EftTerminal $terminal)
    {
        if (!$this->canManageRegistry()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        Auth::user()->update(['preferred_eft_terminal_id' => $terminal->id]);

        return response()->json(['success' => true]);
    }

    /**
     * A real live check for a Linkly terminal — unlike mx51's Test button (a cheap metadata
     * call to mx51's cloud), Linkly has no such thing: the only genuine connectivity check is
     * a Logon, which round-trips to the physical terminal and visibly wakes it up. That's why
     * this is its own explicit button rather than something run silently every time this page
     * loads (see index()'s mx51 self-heal, which has no such side effect). Records a real
     * LinklyTransaction, same as the Logon button on the event console, so lastKnownStatus()
     * immediately reflects the fresh result.
     */
    public function checkConnection(Request $request, EftTerminal $terminal)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($terminal->provider !== 'linkly') {
            return redirect()->back()->with('error', 'Connection checks are only available for Linkly terminals.')->with('expandTerminalId', $terminal->id);
        }

        $result = LinklyEftService::logon($terminal);

        LinklyTransaction::create([
            'pos_txn_ref' => 'LGN' . now()->format('mdHis') . rand(100, 999),
            'txn_type' => 'logon',
            'eft_terminal_id' => $terminal->id,
            'status' => $result['success'] ? 'approved' : 'failed',
            'response_text' => $result['message'],
            'initiated_by' => Auth::id(),
            'authorised_by' => Auth::id(),
        ]);

        AuditLogService::log("Checked connection for EFT terminal '{$terminal->label}' ({$terminal->key}): {$result['message']}");

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message'])->with('expandTerminalId', $terminal->id);
    }

    public function destroy(Request $request, EftTerminal $terminal)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($terminal->is_default) {
            return redirect()->back()->with('error', 'Cannot remove the default terminal — set another one as default first.')->with('expandTerminalId', $terminal->id);
        }

        if ($terminal->isSciPaired()) {
            \App\Services\CbaSciService::unpair($terminal);
        }

        // A soft delete (see EftTerminal's SoftDeletes trait) — a terminal with recorded
        // transactions used to be impossible to remove at all, which was the real complaint:
        // its history stays fully intact (eft_terminal_id on every linkly_transactions/
        // sci_transactions row still points at this exact row, just no longer returned by the
        // registry/pickers/EftTerminal::default()'s normal queries), and LinklyTransaction::
        // eftTerminal()/SciTransaction::eftTerminal() are withTrashed() specifically so that
        // history keeps showing which physical terminal was used.
        $label = $terminal->label;
        $terminal->delete();

        AuditLogService::log("Removed EFT terminal '{$label}'");

        return redirect()->back()->with('success', "\"{$label}\" removed.");
    }
}
