<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EhundiController extends Controller
{
    /**
     * Show the immersive e-Hundi page.
     */
    public function show()
    {
        if (Auth::check() && (session('active_role', Auth::user()->role) === 'Devotee' || Auth::user()->role === 'Devotee')) {
            return view('devotee.ehundi');
        }
        return view('frontend.ehundi');
    }

    /**
     * Store the hundi offering.
     */
    public function offer(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $devoteeId = null;
        if (Auth::check()) {
            $devotee = DB::table('devotees')->where('user_id', Auth::id())->first();
            if ($devotee) {
                $devoteeId = $devotee->devotee_id;
            }
        }

        DB::table('ehundis')->insert([
            'devotee_id' => $devoteeId,
            'amount' => $request->amount,
            'payment_status' => 'Paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your offering of ₹' . number_format($request->amount, 2) . ' was placed successfully.'
        ]);
    }
}
