<?php

namespace App\Providers;

use App\Models\Setting;
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
            ['admin.*', 'devotee.*', 'priest.*', 'staff.*', 'trustee.*', 'accountant.*', 'auth.*', 'emails.*', 'frontend.ehundi'],
            function ($view) {
                $view->with('temple', Setting::templeBranding());
            }
        );
    }
}
