<?php

namespace App\Http\Controllers;

use App\Models\EftTerminal;
use App\Services\EftTerminalAccess;
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
        ]);

        $terminal = EftTerminal::find($validated['terminal_id']);
        $result = LinklyEftService::pair($validated['pair_code'], $terminal);

        return redirect()->back()
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
