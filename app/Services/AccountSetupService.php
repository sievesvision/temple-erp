<?php

namespace App\Services;

use App\Mail\PosPinSetupLinkMail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Replaces the old "generate a random password, then show/email it in plaintext" pattern
 * used by every "Add [Role]" controller. A newly created account instead gets an unusable
 * random password (nobody is ever told it) plus a standard Laravel password-reset token —
 * the same expiring, single-use link mechanism already used for "Send Reset Link" actions —
 * so the new user sets their own password instead of being handed one.
 */
class AccountSetupService
{
    /**
     * Creates a set-password link for a brand-new account and, depending on the system's
     * email settings, either emails it (via User::sendPasswordResetNotification(), the same
     * branded template as every other reset-link email) or leaves it for the caller to show
     * on-screen — Testing Mode commonly uses unreachable test addresses that can't receive
     * real mail, so the UI still needs *some* way to reach the account; a one-time expiring
     * link is a reasonable thing to display directly, unlike a persistent plaintext password.
     *
     * @return array{url: string, emailed: bool, show_link: bool}
     */
    public static function sendPasswordSetupLink(User $user): array
    {
        $token = Password::broker()->createToken($user);
        $url = route('admin-reset.form', ['token' => $token, 'email' => $user->email]);

        $systemMode = Setting::get('system_mode', 'Testing Mode');
        $emailHandling = Setting::get('testing_email_handling', 'Do Not Send Emails');
        $sendEmail = $systemMode !== 'Testing Mode' || $emailHandling === 'Send Emails';

        $emailed = false;
        if ($sendEmail) {
            try {
                $user->sendPasswordResetNotification($token);
                $emailed = true;
            } catch (\Exception $e) {
                // Swallow — the account still exists; showing the link (Testing Mode) or a
                // manual "Forgot Password" attempt afterwards still gets them in.
            }
        }

        return [
            'url' => $url,
            'emailed' => $emailed,
            'show_link' => $systemMode === 'Testing Mode',
        ];
    }

    /**
     * The only path by which `users.pos_pin` is ever written on someone else's behalf —
     * an Organisation Admin or an event-admin-level coordinator can trigger this, but
     * neither ever sees or types the PIN itself; only the account holder, opening this
     * one-time link, sets it. See `pos_pin_setup_tokens` migration for why this uses its
     * own token store rather than Laravel's password-reset broker.
     *
     * A 30-minute expiry (versus 60 for password setup) — this is meant to be opened
     * within minutes, often handed to someone standing at the counter, not a
     * general-purpose reset window.
     *
     * @return array{url: string, emailed: bool, show_link: bool}
     */
    public static function sendPosPinSetupLink(User $user): array
    {
        $plainToken = Str::random(40);

        // Only one outstanding link per user at a time — triggering a new one invalidates
        // any earlier unused link rather than leaving several simultaneously valid.
        DB::table('pos_pin_setup_tokens')->where('user_id', $user->id)->delete();
        DB::table('pos_pin_setup_tokens')->insert([
            'user_id' => $user->id,
            'token' => Hash::make($plainToken),
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
        ]);

        $url = route('pos.pin.activate', ['user' => $user->id, 'token' => $plainToken]);

        $systemMode = Setting::get('system_mode', 'Testing Mode');
        $emailHandling = Setting::get('testing_email_handling', 'Do Not Send Emails');
        $sendEmail = $systemMode !== 'Testing Mode' || $emailHandling === 'Send Emails';

        $emailed = false;
        if ($sendEmail) {
            try {
                Mail::to($user->email)->send(new PosPinSetupLinkMail($user->name, $url));
                $emailed = true;
            } catch (\Exception $e) {
                // Swallow — the token still exists; the link can still be handed to them
                // directly (Testing Mode) or the action retried.
            }
        }

        return [
            'url' => $url,
            'emailed' => $emailed,
            'show_link' => $systemMode === 'Testing Mode',
        ];
    }
}
