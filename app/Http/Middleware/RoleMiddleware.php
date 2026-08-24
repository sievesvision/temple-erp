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

        // Admin can access everything
        if ($user->role === 'Admin') {
            return $next($request);
        }

        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        // If not authorized, redirect to their home role dashboard
        $redirectRoute = match ($user->role) {
            'Admin' => 'admin.dashboard',
            'Devotee' => 'devotee.dashboard',
            'Priest' => 'priest.dashboard',
            'Trustee' => 'trustee.dashboard',
            'Staff' => 'staff.dashboard',
            'Accountant' => 'accountant.dashboard',
            default => 'home',
        };

        return redirect()->route($redirectRoute)->with('error', 'Unauthorized access to this module.');
    }
}
