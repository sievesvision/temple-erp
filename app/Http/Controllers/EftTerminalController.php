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

        // Viewing this page is one of the two moments mx51's own certification checklist
        // requires a live pairing-info check (the other is just before a transaction starts,
        // see CbaSciController::startPurchase()) — a mx51 terminal marked paired locally gets
        // silently self-corrected here if it's actually been unpaired on mx51's own side. See
        // EftTerminalRegistryView::selfHealSciPairings()'s own docblock for the other place
        // this same check now runs (the event/ticket consoles' own EFT Terminal Settings pane).
        [
            'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode,
            'activeTerminals' => $activeTerminals, 'inactiveTerminals' => $inactiveTerminals, 'allOperational' => $allOperational,
        ] = \App\Services\EftTerminalRegistryView::data(selfHeal: true);
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
     * just-created row is deleted outright, so a failed attempt never leaves a dangling
     * terminal behind for the next retry. The same physical device can end up registered under
     * more than one row this way (re-added without noticing one already exists, testing,
     * etc.) — that's allowed; this never merges or deletes another terminal just because its
     * TID matches (see CbaSciService::pair()).
     */
    public function addAndPair(Request $request)
    {
        if (!$this->canManageRegistry()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'pairing_nickname' => 'nullable|string|max:255',
            'provider' => 'required|in:linkly,cba_sci',
            'pairing_code' => 'required|string|max:20',
        ]);

        $pairingNickname = $validated['pairing_nickname'] ?? null;

        // No admin-typed "Unique Terminal Code" any more — the physical terminal itself is
        // what should identify it. For SCI, CbaSciService::pair() overwrites this placeholder
        // with a TID-derived key the moment pairing confirms which physical device this is.
        // Linkly has no server-verified device id at pairing time, so its key stays
        // nickname-derived (or this placeholder) — it was always just an internal label, never
        // something Linkly itself validates.
        $placeholderKey = $pairingNickname ? \Illuminate\Support\Str::slug($pairingNickname) . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)) : 'terminal-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));

        $terminal = EftTerminal::create([
            'key' => $placeholderKey,
            'label' => $pairingNickname !== null && $pairingNickname !== '' ? $pairingNickname : 'New Terminal',
            'provider' => $validated['provider'],
            'pos_id' => EftTerminal::generatePosId(),
            'is_default' => !EftTerminal::query()->exists(),
        ]);

        $result = $validated['provider'] === 'cba_sci'
            ? \App\Services\CbaSciService::pair($validated['pairing_code'], $pairingNickname, $terminal)
            : LinklyEftService::pair($validated['pairing_code'], $terminal);

        if (!$result['success']) {
            // This row never succeeded at anything (no transaction, no completed pairing), so
            // it's removed outright — nothing it has is worth keeping, and the 'key' field
            // needs to be genuinely free again for a retry.
            $terminal->delete();
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
     * of this one still-unconfirmed wizard run and is a genuinely new row with zero history by
     * construction. Uses the broader canManageRegistry() tier rather than destroy()'s
     * System-Admin-only gate, since anyone who could start this wizard should be able to back
     * out of it.
     *
     * The "has recorded activity" branch below is purely defensive — nothing in the normal
     * wizard flow should ever reach it — but if it's somehow called against a terminal that
     * isn't actually this fresh placeholder, unpairing instead of deleting at least avoids
     * destroying a real terminal's history.
     */
    public function cancelNewTerminal(Request $request, EftTerminal $terminal)
    {
        if (!$this->canManageRegistry()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        if ($terminal->linklyTransactions()->exists() || $terminal->sciTransactions()->exists()) {
            if ($terminal->isSciPaired()) {
                \App\Services\CbaSciService::unpair($terminal);
            }
            AuditLogService::log("Cancelled a pairing attempt on existing EFT terminal '{$terminal->label}' ({$terminal->key})");
            return response()->json(['success' => true]);
        }

        if ($terminal->isSciPaired()) {
            \App\Services\CbaSciService::unpair($terminal);
        }

        $label = $terminal->label;
        $terminal->delete();

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

        if ($terminal->isSciPaired()) {
            \App\Services\CbaSciService::unpair($terminal);
        }

        // A genuine, permanent delete — eft_terminal_id on linkly_transactions/sci_transactions
        // is ->nullOnDelete(), so removing a terminal never breaks or orphans any past
        // transaction's own data, it just stops naming which terminal processed it (a display
        // detail only; see EftConsoleTransactionHistoryTest etc.). Nothing worth losing.
        $label = $terminal->label;
        $wasDefault = $terminal->is_default;
        $terminal->delete();

        // Removing the default terminal no longer blocks outright — the next paired terminal
        // (if any) is promoted automatically instead, so the registry is never left pointing at
        // a default that no longer exists.
        if ($wasDefault) {
            EftTerminal::ensureUsableDefault();
        }

        AuditLogService::log("Removed EFT terminal '{$label}'");

        return redirect()->back()->with('success', "\"{$label}\" removed.");
    }
}
