<?php

namespace App\Http\Middleware;

use App\Services\KioskDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every /kiosk/* API route except the pairing endpoint itself. Unlike every other
 * middleware in this directory, this validates a device credential, not an Auth::user()
 * session — a kiosk device in Phase 1 is not a logged-in human (no ordering UI exists yet to
 * need one; see the Phase 2 note in the kiosk feature plan for how this gets extended later).
 *
 * Always returns JSON — kiosk pages are fetch-driven, same convention the existing POS pages
 * already use for their own background polling.
 */
class AuthenticateKioskDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Kiosk-Device-Token');
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Device not registered.'], 401);
        }

        $device = KioskDeviceService::authenticate($token);
        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not authorised.'], 401);
        }

        $device->touchActivity($request->ip());
        $request->attributes->set('kiosk_device', $device);

        return $next($request);
    }
}
