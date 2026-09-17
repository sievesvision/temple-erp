<?php

namespace App\Http\Controllers;

use App\Services\LinklyEftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-only pairing management for the Linkly Cloud EFTPOS integration — lets the PIN pad
 * be (re)paired without a developer running a tinker command, e.g. after a new terminal is
 * issued or the pairing is lost. The actual transaction logic lives in LinklyEftService /
 * DonationController::chargeEftTerminal(); this controller only manages the one-time secret.
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
        ]);

        $result = LinklyEftService::pair($validated['pair_code']);

        return redirect()->route('admin.settings')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
