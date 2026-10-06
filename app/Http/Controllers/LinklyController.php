<?php

namespace App\Http\Controllers;

use App\Models\EftTerminal;
use App\Services\AuditLogService;
use App\Services\EftTerminalAccess;
use App\Services\LinklyConfigService;
use App\Services\LinklyEftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pairing management for the Linkly Cloud EFTPOS integration — lets a registered PIN pad be
 * (re)paired without a developer running a tinker command, e.g. after a new terminal is
 * issued or a pairing is lost. The actual transaction logic lives in LinklyEftService /
 * DonationController's EFT methods; this controller only manages the one-time secret. See
 * EftTerminalController for adding/removing/defaulting a terminal — this only pairs one that
 * already exists in the registry. Reachable from the standalone EFT Terminal Settings page
 * (see App\Services\EftTerminalAccess for exactly who that is — broader than 'settings'
 * permission, since an event-admin/ticket-admin already pairs terminals from their console).
 */
class LinklyController extends Controller
{
    public function pair(Request $request)
    {
        $user = Auth::user();
        $activeRole = $user ? session('active_role', $user->role) : null;
        if (!EftTerminalAccess::canManageRegistry($user, $activeRole)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'pair_code' => 'required|string|max:10',
            'terminal_id' => 'required|integer|exists:eft_terminals,id',
            'label' => 'nullable|string|max:255',
        ]);

        $terminal = EftTerminal::find($validated['terminal_id']);
        $result = LinklyEftService::pair($validated['pair_code'], $terminal);

        // Set alongside pairing, same as SCI's pairing nickname — there's no separate "rename"
        // control any more, this is the only place a Linkly terminal's name is set or changed.
        if ($result['success'] && !empty($validated['label'])) {
            $terminal->update(['label' => $validated['label']]);
        }

        return redirect()->back()
            ->with($result['success'] ? 'success' : 'error', $result['message'])
            ->with('expandTerminalId', $terminal->id);
    }

    /**
     * Clears this terminal's paired secret for whichever mode (sandbox/live) is currently
     * active — Linkly has no server-side "unpair" call to make (unlike mx51/SCI), it's purely
     * a local credential, so this is just EftTerminal::clearSecret() plus an audit entry.
     */
    public function unpair(Request $request)
    {
        $user = Auth::user();
        $activeRole = $user ? session('active_role', $user->role) : null;
        if (!EftTerminalAccess::canManageRegistry($user, $activeRole)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate(['terminal_id' => 'required|integer|exists:eft_terminals,id']);
        $terminal = EftTerminal::find($validated['terminal_id']);
        $wasPaired = $terminal->isPaired(LinklyConfigService::mode());
        $terminal->clearSecret(LinklyConfigService::mode());

        if ($wasPaired) {
            AuditLogService::log("Unpaired Linkly terminal '{$terminal->label}' ({$terminal->key})");
        }

        return redirect()->back()
            ->with($wasPaired ? 'success' : 'error', $wasPaired ? 'Terminal unpaired.' : 'This terminal was not paired.')
            ->with('expandTerminalId', $terminal->id);
    }
}
