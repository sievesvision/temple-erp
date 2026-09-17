<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Password;

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
}
