<?php

namespace App\Http\Controllers;

use App\Services\KioskDeviceService;
use Illuminate\Http\Request;

/**
 * The one /kiosk route reachable with no device credential yet — redeems a short-lived,
 * admin-generated one-time pairing code and hands back the device's own long-lived bearer
 * token. See KioskDeviceService::redeemPairingCode() for the actual verification/issuance
 * logic; this controller is a thin HTTP wrapper around it.
 */
class KioskPairingController extends Controller
{
    public function redeem(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
        ]);

        $result = KioskDeviceService::redeemPairingCode($validated['code'], $request->ip());

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json([
            'success' => true,
            'token' => $result['token'],
            'device_name' => $result['device']->name,
        ]);
    }
}
