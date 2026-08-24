<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DevoteeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to access this section.');
        }

        $user = Auth::user();
        $activeRole = $request->session()->get('active_role', $user->role);

        // Every authenticated user can access devotee features if their active mode is Devotee
        if ($activeRole === 'Devotee') {
            return $next($request);
        }

        $redirectRoute = match ($activeRole) {
            'Admin' => 'admin.dashboard',
            'Priest' => 'priest.dashboard',
            'Trustee' => 'trustee.dashboard',
            'Staff' => 'staff.dashboard',
            'Accountant' => 'accountant.dashboard',
            default => 'login',
        };

        return redirect()->route($redirectRoute)->with('error', 'Unauthorized access.');
    }
}
