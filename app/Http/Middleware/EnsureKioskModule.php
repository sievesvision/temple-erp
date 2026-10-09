<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A donations-module device's credential must never be able to trigger a ticket sale (or vice
 * versa), even if someone crafts the right POST body — this checks the authenticated device's
 * own configured `module` column against a route-level expectation (never the request body),
 * parametrized like the existing RoleMiddleware (`kiosk.module:tickets` / `kiosk.module:donations`).
 */
class EnsureKioskModule
{
    public function handle(Request $request, Closure $next, string $required): Response
    {
        $device = $request->attributes->get('kiosk_device');
        if (!$device || $device->module !== $required) {
            return response()->json(['success' => false, 'message' => 'This device is not configured for that.'], 403);
        }

        return $next($request);
    }
}
