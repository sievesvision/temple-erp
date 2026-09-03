<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to access this section.');
        }

        $user = Auth::user();
        $activeRole = $request->session()->get('active_role', $user->role);

        // Admin bypasses everything, but only while *actively* Admin — an Admin who has
        // switched to e.g. Committee should be gated exactly as Committee, not silently
        // retain full Admin power while the UI shows a restricted role.
        if ($activeRole === 'Admin') {
            return $next($request);
        }

        if (in_array($activeRole, $roles)) {
            return $next($request);
        }

        // If not authorized, redirect to their home role dashboard
        $redirectRoute = match ($activeRole) {
            'Admin' => 'admin.dashboard',
            'Devotee' => 'devotee.dashboard',
            'Priest' => 'priest.dashboard',
            'Trustee' => 'trustee.dashboard',
            'Staff' => 'staff.dashboard',
            'Accountant' => 'accountant.dashboard',
            'Committee' => 'committee.dashboard',
            default => 'home',
        };

        return redirect()->route($redirectRoute)->with('error', 'Unauthorized access to this module.');
    }
}
