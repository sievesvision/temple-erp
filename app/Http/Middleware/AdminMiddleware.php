<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to access this section.');
        }

        $user = Auth::user();
        $activeRole = $request->session()->get('active_role', $user->role);

        // Admin has no pivot/grant table (see User::grantTables()) — active_role can only
        // ever be 'Admin' if users.role already is, so checking it alone is both correct
        // and consistent with the other role middlewares' simplified pattern.
        if ($activeRole === 'Admin') {
            return $next($request);
        }

        // Redirect to dashboard of their active mode
        $redirectRoute = match ($activeRole) {
            'Devotee' => 'devotee.dashboard',
            'Priest' => 'priest.dashboard',
            'Trustee' => 'trustee.dashboard',
            'Staff' => 'staff.dashboard',
            'Accountant' => 'accountant.dashboard',
            'Committee' => 'committee.dashboard',
            default => 'login',
        };

        return redirect()->route($redirectRoute)->with('error', 'Unauthorized access.');
    }
}
