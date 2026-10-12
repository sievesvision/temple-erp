<?php

namespace App\Http\Controllers;

use App\Models\EftTerminal;
use App\Models\Register;
use App\Services\AuditLogService;
use App\Services\LinklyConfigService;
use App\Services\RegisterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

/**
 * Organisation Admin's register management — create/rename/unpair a register and
 * configure its per-event / ticket-sales default EFT terminal (see RegisterService and
 * the registers/register_pairing_codes/register_eft_terminal_defaults migrations for the
 * full design). The pairing page itself (showPair()/submitPair()) is deliberately public
 * — the short code IS the credential, same reasoning as the kiosk pairing page.
 */
class RegisterController extends Controller
{
    private function isAdmin(): bool
    {
        $user = Auth::user();
        return $user && $user->role === 'Admin';
    }

    public function index()
    {
        if (!$this->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $registers = Register::orderBy('name')->get();

        return view('admin.registers', compact('registers'));
    }

    public function store(Request $request)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate(['name' => 'required|string|max:255|unique:registers,name']);

        RegisterService::create($request->name, Auth::user());

        return redirect()->route('admin.registers.index')->with('success', 'Register created.');
    }

    public function rename(Request $request, Register $register)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate(['name' => 'required|string|max:255|unique:registers,name,' . $register->id]);

        RegisterService::rename($register, $request->name, Auth::user());

        return redirect()->route('admin.registers.index')->with('success', 'Register renamed.');
    }

    /**
     * Shows the plaintext code exactly once, the same Testing-Mode-independent way the
     * kiosk pairing flow does (there's no email to fall back to here — this is always
     * shown on-screen to the admin, who reads/hands it to whoever is at the register).
     */
    public function generateCode(Request $request, Register $register)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $code = RegisterService::generatePairingCode($register, Auth::user());

        return redirect()->route('admin.registers.index')
            ->with('success', 'Pairing code generated for ' . $register->name . '.')
            ->with('register_pairing_code', ['register_id' => $register->id, 'name' => $register->name, 'code' => $code]);
    }

    public function unpair(Request $request, Register $register)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        RegisterService::unpair($register, Auth::user());

        return redirect()->route('admin.registers.index')->with('success', $register->name . ' has been unpaired.');
    }

    public function destroy(Request $request, Register $register)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $name = $register->name;
        $register->delete();
        AuditLogService::log("Deleted register '{$name}'.");

        return redirect()->route('admin.registers.index')->with('success', $name . ' has been deleted.');
    }

    /**
     * Every open event plus "Ticket Sales" as rows, each with a dropdown of currently
     * paired terminals — the answer to "each event and ticket could have a different
     * default terminal" directly.
     */
    public function terminals(Register $register)
    {
        if (!$this->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $linklyMode = LinklyConfigService::mode();
        $pairedTerminals = EftTerminal::orderBy('label')->get()->filter(fn ($t) => $t->isPairedFor($linklyMode))->values();

        $events = DB::table('events')->orderByDesc('event_date')->select('event_id', 'event_name')->get();

        $defaults = DB::table('register_eft_terminal_defaults')->where('register_id', $register->id)
            ->get()->keyBy(fn ($row) => $row->context_type . ':' . ($row->context_id ?? ''));

        return view('admin.register-terminals', compact('register', 'pairedTerminals', 'events', 'defaults'));
    }

    public function setTerminal(Request $request, Register $register)
    {
        if (!$this->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'context_type' => 'required|in:event,tickets',
            'context_id' => 'required_if:context_type,event|nullable|integer',
            'eft_terminal_id' => 'nullable|exists:eft_terminals,id',
        ]);

        $contextId = $request->context_type === 'event' ? (int) $request->context_id : null;

        RegisterService::setDefaultTerminal(
            $register, $request->context_type, $contextId,
            $request->eft_terminal_id ? (int) $request->eft_terminal_id : null,
            Auth::user()
        );

        return redirect()->back()->with('success', 'Default terminal updated.');
    }

    /**
     * Public pairing page — no auth, the short code is the credential (same reasoning as
     * the kiosk pairing flow). Visited once on the physical register's own browser.
     */
    public function showPair()
    {
        return view('auth.register-pair', ['error' => null]);
    }

    public function submitPair(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $result = RegisterService::redeemPairingCode(strtoupper(trim($request->code)), $request->ip());

        if (!$result['success']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        Cookie::queue(Cookie::make(
            RegisterService::COOKIE_NAME,
            $result['token'],
            RegisterService::COOKIE_LIFETIME_MINUTES,
            null, null, null, true // httpOnly — never read/sent by client JS
        ));

        return redirect()->route('register.pair')->with('success', 'This device is now paired as "' . $result['register']->name . '".');
    }
}
