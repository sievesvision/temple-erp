<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chained after 'kiosk.device' — authenticates the device's own per-device service-account
 * User for this request only, via Auth::onceUsingId(), never Auth::login(). A kiosk is a
 * shared physical browser; Auth::login() persists via the session cookie, which could collide
 * with a staff member's own session on the same machine. Auth::onceUsingId() never touches
 * the session, fitting the kiosk's own per-request device-token model exactly — every request
 * already re-proves itself via the X-Kiosk-Device-Token header, so there's nothing to persist.
 *
 * This is what lets every existing order/donation-creation controller method (TicketController::
 * storeOrder(), DonationController::storeGuestDonation()/startEftCharge(), CbaSciController::
 * startPurchase(), etc.) be reused completely unmodified — they just see an authenticated
 * Auth::user() with the right RolePermission grant, exactly as they already do for a human
 * POS operator.
 */
class AuthenticateKioskDeviceSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->attributes->get('kiosk_device');
        if (!$device || !$device->service_user_id) {
            return response()->json(['success' => false, 'message' => 'Device not fully set up.'], 403);
        }

        Auth::onceUsingId($device->service_user_id);
        session(['active_role' => 'Kiosk Device']);

        return $next($request);
    }
}
