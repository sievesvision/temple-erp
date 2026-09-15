<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
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
        // without having to add it to every Mailable individually.
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $bccEmail = Setting::get('system_notification_email');
            if ($bccEmail) {
                $event->message->addBcc($bccEmail);
            }
        });
    }
}
