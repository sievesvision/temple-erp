<?php

namespace App\Http\Controllers;

use App\Models\EftTerminal;
use App\Services\AuditLogService;
use App\Services\CbaSciService;
use App\Services\EftTerminalAccess;
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
}
