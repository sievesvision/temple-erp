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
 * App\Services\EftTerminalAccess for exactly who that is. Only "set default" and "remove a
 * terminal" stay System-Admin-only, since those affect every other console's fallback
 * resolution, not just the caller's own event/module.
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
        $providerLabel = $validated['provider'] === 'cba_sci' ? 'mx51 Cloud' : 'Linkly Cloud';
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

    public function setDefault(Request $request, EftTerminal $terminal)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        EftTerminal::where('id', '!=', $terminal->id)->update(['is_default' => false]);
        $terminal->update(['is_default' => true]);

        AuditLogService::log("Set '{$terminal->label}' as the default EFT terminal");

        return redirect()->back()->with('success', "\"{$terminal->label}\" is now the default terminal.")->with('expandTerminalId', $terminal->id);
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
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($terminal->is_default) {
            return redirect()->back()->with('error', 'Cannot remove the default terminal — set another one as default first.')->with('expandTerminalId', $terminal->id);
        }

        if ($terminal->linklyTransactions()->exists() || $terminal->sciTransactions()->exists()) {
            return redirect()->back()->with('error', 'Cannot remove a terminal with recorded transactions — it stays in the registry for that history to remain readable.')->with('expandTerminalId', $terminal->id);
        }

        if ($terminal->isSciPaired()) {
            \App\Services\CbaSciService::unpair($terminal);
        }

        $label = $terminal->label;
        $terminal->delete();

        AuditLogService::log("Removed EFT terminal '{$label}'");

        return redirect()->back()->with('success', "\"{$label}\" removed.");
    }
}
