<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PriestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to access this section.');
        }

        $user = Auth::user();
        $activeRole = $request->session()->get('active_role', $user->role);

        if ($activeRole === 'Priest' && $user->role === 'Priest') {
            return $next($request);
        }

        $redirectRoute = match ($activeRole) {
            'Admin' => 'admin.dashboard',
            'Devotee' => 'devotee.dashboard',
            'Trustee' => 'trustee.dashboard',
            'Staff' => 'staff.dashboard',
            'Accountant' => 'accountant.dashboard',
            default => 'login',
        };

        return redirect()->route($redirectRoute)->with('error', 'Unauthorized access.');
    }
}
