<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Services\EscPosPrinterService;
use App\Services\ThermalPrinterSettings;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Raw ESC/POS network printing — the auto-print replacement for the old window.print()
 * browser popup (see sci-action-framework.js's printText()). Two call sites feed this:
 * the mx51 merchant/customer receipt text the POS already has in hand after a transaction,
 * and the Admin > Settings "Test Print" button used to verify the printer's IP/port while
 * setting it up. Ticket stub auto-print is a separate endpoint on TicketController (it needs
 * the TicketOrder model, not just a block of text) but shares the same EscPosPrinterService.
 */
class ThermalPrintController extends Controller
{
    /**
     * Prints a block of receipt text verbatim — used for mx51's merchant/customer receipt,
     * which the frontend already received as plain text via the charge status poll
     * (CbaSciController::poll()); this never re-fetches or re-derives it server-side. Same
     * role gate as the mx51 charge lifecycle routes it's reached from (see routes/web.php).
     * Always returns 200 + {success:false,...} rather than an HTTP error status on a failed
     * print, so the frontend's existing fetch().then(json) handling (no catch needed for this
     * specific failure mode) can fall back to the browser-print popup.
     */
    public function printReceipt(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:10000',
        ]);

        if (!ThermalPrinterSettings::isConfigured()) {
            return response()->json(['success' => false, 'fallback' => true, 'message' => 'Thermal printer not configured.']);
        }

        try {
            (new EscPosPrinterService())->printReceiptText($validated['text']);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'fallback' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Admin > Settings "Test Print" button — accepts ip/port straight from the still-unsaved
     * form fields (see EscPosPrinterService::connect()) so setup can be verified before
     * clicking the main "Save Settings" button at all.
     */
    public function test(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'settings', 'edit')) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'ip' => 'required|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
        ]);

        try {
            (new EscPosPrinterService())->printTestPage($validated['ip'], $validated['port'] ?? 9100);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
