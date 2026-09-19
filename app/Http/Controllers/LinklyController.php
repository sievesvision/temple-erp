<?php

namespace App\Http\Controllers;

use App\Models\EftTerminal;
use App\Services\LinklyEftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-only pairing management for the Linkly Cloud EFTPOS integration — lets a registered
 * PIN pad be (re)paired without a developer running a tinker command, e.g. after a new
 * terminal is issued or a pairing is lost. The actual transaction logic lives in
 * LinklyEftService / DonationController's EFT methods; this controller only manages the
 * one-time secret. See EftTerminalController for adding/removing/defaulting a terminal —
 * this only pairs one that already exists in the registry. The pairing UI itself lives as
 * its own panel on the main Settings page, not a separate one.
 */
class LinklyController extends Controller
{
    public function pair(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'pair_code' => 'required|string|max:10',
            'terminal_id' => 'required|integer|exists:eft_terminals,id',
        ]);

        $terminal = EftTerminal::find($validated['terminal_id']);
        $result = LinklyEftService::pair($validated['pair_code'], $terminal);

        return redirect()->route('admin.settings')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
