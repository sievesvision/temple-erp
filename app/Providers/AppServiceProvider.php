<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(
            ['admin.*', 'devotee.*', 'priest.*', 'staff.*', 'trustee.*', 'accountant.*', 'committee.*', 'auth.*', 'emails.*', 'frontend.ehundi'],
            function ($view) {
                $view->with('temple', Setting::templeBranding());
            }
        );

        // BCC every outgoing email to the configured system notification address (System
        // Settings > General) so admins have a running record of what the system has sent,
        // without having to add it to every Mailable individually. Never BCC the sending
        // mailbox itself — that turns every send into the account receiving its own mail,
        // doubling its real traffic for no benefit (its own Sent folder already has this),
        // and is exactly the kind of self-inflicted volume that gets a mailbox flagged.
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $bccEmail = Setting::get('system_notification_email');
            $fromAddress = config('mail.from.address');
            if ($bccEmail && (!$fromAddress || strcasecmp($bccEmail, $fromAddress) !== 0)) {
                $event->message->addBcc($bccEmail);
            }

            // Hard safety net: this temple's real usage is a handful of donations/logins a
            // day, so anything bursting past a generous per-minute ceiling is a bug (a retry
            // loop, a runaway job) rather than legitimate traffic — block it outright rather
            // than let it run and risk the sending mailbox being suspended for "spam-like"
            // behaviour again. Returning false from a MessageSending listener cancels the send.
            $windowKey = 'outgoing_mail_count_' . now()->format('YmdHi');
            $sentThisMinute = (int) Cache::get($windowKey, 0) + 1;
            Cache::put($windowKey, $sentThisMinute, now()->addMinutes(2));

            if ($sentThisMinute > 15) {
                Log::warning('Outgoing email blocked by rate-limit safety net', [
                    'minute' => now()->format('Y-m-d H:i'),
                    'count' => $sentThisMinute,
                    'to' => implode(',', array_map(fn ($a) => $a->getAddress(), $event->message->getTo() ?? [])),
                ]);
                return false;
            }
        });
    }
}
