<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RoleSwitchMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // If active_role session key is not set, initialize it with primary role
            if (!$request->session()->has('active_role')) {
                $request->session()->put('active_role', $user->role);
            }
            
            // Auto create devotee profile if the active role is Devotee and profile doesn't exist
            if ($request->session()->get('active_role') === 'Devotee') {
                $devoteeExists = DB::table('devotees')->where('user_id', $user->id)->exists();
                if (!$devoteeExists) {
                    DB::table('devotees')->insert([
                        'user_id' => $user->id,
                        'address' => 'Auto-created Devotee Profile',
                        'gothra' => 'Not Specified',
                        'nakshatra' => 'Not Specified',
                        'gender' => 'Male',
                        'dob' => '2000-01-01',
                        'verified' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return $next($request);
    }
}
