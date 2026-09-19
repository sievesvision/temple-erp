<?php

namespace App\Http\Controllers;

use App\Models\EftTerminal;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-only management of the EFT terminal registry (add/remove a terminal, set which one
 * is the default) — the Settings page is the one central place terminals are created, so the
 * per-console EFTPOS panes (Event Console, Ticket Console) only ever choose *among* what's
 * already registered here, never invent a new one. Pairing itself is handled per-console (or
 * from here too, via LinklyController::pair()) since a non-Admin event-admin/ticket-admin can
 * still pair any registered terminal — only adding/removing/defaulting is Admin-only.
 */
class EftTerminalController extends Controller
{
    private function isAdmin(): bool
    {
        $user = Auth::user();
        return $user && $user->role === 'Admin';
    }

    public function store(Request $request)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'key' => 'required|string|max:40|alpha_dash|unique:eft_terminals,key',
            'label' => 'required|string|max:255',
        ]);

        $terminal = EftTerminal::create([
            'key' => $validated['key'],
            'label' => $validated['label'],
            'pos_id' => EftTerminal::generatePosId(),
            'is_default' => !EftTerminal::query()->exists(),
        ]);

        AuditLogService::log("Added EFT terminal '{$terminal->label}' ({$terminal->key})");

        return redirect()->route('admin.settings')->with('success', "Terminal \"{$terminal->label}\" added — pair it below.");
    }

    public function setDefault(Request $request, EftTerminal $terminal)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        EftTerminal::where('id', '!=', $terminal->id)->update(['is_default' => false]);
        $terminal->update(['is_default' => true]);

        AuditLogService::log("Set '{$terminal->label}' as the default EFT terminal");

        return redirect()->back()->with('success', "\"{$terminal->label}\" is now the default terminal.");
    }

    public function destroy(Request $request, EftTerminal $terminal)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($terminal->is_default) {
            return redirect()->back()->with('error', 'Cannot remove the default terminal — set another one as default first.');
        }

        if ($terminal->linklyTransactions()->exists()) {
            return redirect()->back()->with('error', 'Cannot remove a terminal with recorded transactions — it stays in the registry for that history to remain readable.');
        }

        $label = $terminal->label;
        $terminal->delete();

        AuditLogService::log("Removed EFT terminal '{$label}'");

        return redirect()->back()->with('success', "\"{$label}\" removed.");
    }
}
