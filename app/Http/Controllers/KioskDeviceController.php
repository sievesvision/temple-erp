<?php

namespace App\Http\Controllers;

use App\Models\EftTerminal;
use App\Models\Event;
use App\Models\KioskDevice;
use App\Services\KioskAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin management of kiosk devices — the standalone "Kiosk Devices" page. Mirrors
 * EftTerminalController's own shape (private gate method, AuditLogService call inside the
 * service layer itself rather than here, redirect()->back() for classic mutations). The one
 * action gated differently is updateConfiguration(), which needs KioskAccess::canConfigure()
 * (module/event-scoped) rather than the broader canManageRegistry() every other action here
 * uses — see App\Services\KioskAccess for why.
 */
class KioskDeviceController extends Controller
{
    private function canManageRegistry(): bool
    {
        $user = Auth::user();
        $activeRole = $user ? session('active_role', $user->role) : null;
        return KioskAccess::canManageRegistry($user, $activeRole);
    }

    public function index(Request $request)
    {
        if (!$this->canManageRegistry()) {
            abort(403, 'Unauthorized access.');
        }

        $devices = KioskDevice::orderBy('status')->orderBy('name')->get();
        $events = Event::orderBy('event_name')->get(['event_id', 'event_name']);
        $eftTerminals = EftTerminal::orderBy('label')->get(['id', 'label', 'is_default']);
        $canManageRegistryLevel = true;

        return view('admin.kiosk-devices', compact('devices', 'events', 'eftTerminals', 'canManageRegistryLevel'));
    }

    public function store(Request $request)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'module' => 'nullable|in:tickets,donations',
            'event_id' => 'nullable|integer|exists:events,event_id',
            'eft_terminal_id' => 'nullable|integer|exists:eft_terminals,id',
            'enabled_payment_methods' => 'nullable|array',
            'enabled_payment_methods.*' => 'string|in:Cash,UPI,Bank Transfer,Cheque,EFT Terminal',
        ]);

        \App\Services\KioskDeviceService::register(
            $validated['name'],
            $validated['label'] ?? null,
            $validated['module'] ?? null,
            $validated['event_id'] ?? null,
            $validated['eft_terminal_id'] ?? null,
            $validated['enabled_payment_methods'] ?? null,
            Auth::user()
        );

        return redirect()->back()->with('success', 'Kiosk device registered — generate a pairing code to activate it.');
    }

    public function updateConfiguration(Request $request, KioskDevice $kioskDevice)
    {
        $user = Auth::user();
        $activeRole = $user ? session('active_role', $user->role) : null;
        if (!KioskAccess::canConfigure($user, $activeRole, $kioskDevice)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'module' => 'nullable|in:tickets,donations',
            'event_id' => 'nullable|integer|exists:events,event_id',
            'eft_terminal_id' => 'nullable|integer|exists:eft_terminals,id',
            'enabled_payment_methods' => 'nullable|array',
            'enabled_payment_methods.*' => 'string|in:Cash,UPI,Bank Transfer,Cheque,EFT Terminal',
        ]);

        \App\Services\KioskDeviceService::updateConfiguration($kioskDevice, $validated, $user);

        return redirect()->back()->with('success', "Updated configuration for '{$kioskDevice->name}'.");
    }

    public function generatePairingCode(Request $request, KioskDevice $kioskDevice)
    {
        if (!$this->canManageRegistry()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $code = \App\Services\KioskDeviceService::generatePairingCode($kioskDevice, Auth::user());

        return response()->json(['success' => true, 'code' => $code, 'expires_in_minutes' => 10]);
    }

    public function activate(Request $request, KioskDevice $kioskDevice)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        \App\Services\KioskDeviceService::activate($kioskDevice, Auth::user());

        return redirect()->back()->with('success', "'{$kioskDevice->name}' activated.");
    }

    public function deactivate(Request $request, KioskDevice $kioskDevice)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        \App\Services\KioskDeviceService::deactivate($kioskDevice, Auth::user());

        return redirect()->back()->with('success', "'{$kioskDevice->name}' deactivated.");
    }

    public function revoke(Request $request, KioskDevice $kioskDevice)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        \App\Services\KioskDeviceService::revoke($kioskDevice, Auth::user());

        return redirect()->back()->with('success', "'{$kioskDevice->name}' revoked — it can no longer reach any kiosk route.");
    }

    public function rotateCredential(Request $request, KioskDevice $kioskDevice)
    {
        if (!$this->canManageRegistry()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $token = \App\Services\KioskDeviceService::rotateCredential($kioskDevice, Auth::user());

        return response()->json(['success' => true, 'token' => $token]);
    }

    public function destroy(Request $request, KioskDevice $kioskDevice)
    {
        if (!$this->canManageRegistry()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $name = $kioskDevice->name;
        $kioskDevice->delete();

        \App\Services\AuditLogService::log("Deleted kiosk device '{$name}'");

        return redirect()->back()->with('success', "'{$name}' deleted.");
    }
}
