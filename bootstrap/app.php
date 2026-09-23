<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A session that times out while on one of the POS kiosk pages returns to the kiosk
        // login, not the general devotee/management one — decided by which route the expired
        // request was hitting (the user/session is already gone by the time this fires, so it
        // can't be role-based). Must be set here, not via Authenticate::redirectUsing() in a
        // service provider — ApplicationBuilder::withMiddleware() (Laravel's own bootstrap)
        // unconditionally calls ->redirectGuestsTo(fn () => route('login')) on every kernel
        // resolution, silently overwriting anything a provider's boot() registers; this
        // closure runs immediately after that default, so it's the only place a custom
        // callback actually sticks. Only affects a full-page redirect — the POS pages' own
        // background fetch() polling gets a plain 401 JSON body from Laravel's default
        // unauthenticated() handling (expectsJson() short-circuits before this ever runs) and
        // reloads itself client-side instead; see the fetch wrapper in
        // event-pos-donation.blade.php / ticket-pos.blade.php.
        $middleware->redirectGuestsTo(function ($request) {
            $kioskRoutes = ['admin.events.pos', 'admin.tickets.pos'];
            if ($request->route() && in_array($request->route()->getName(), $kioskRoutes, true)) {
                return route('kiosk.login');
            }
            return route('login');
        });

        $middleware->appendToGroup('web', \App\Http\Middleware\RoleSwitchMiddleware::class);
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'admin/eft/webhook/*',
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'role.admin' => \App\Http\Middleware\AdminMiddleware::class,
            'role.priest' => \App\Http\Middleware\PriestMiddleware::class,
            'role.trustee' => \App\Http\Middleware\TrusteeMiddleware::class,
            'role.staff' => \App\Http\Middleware\StaffMiddleware::class,
            'role.accountant' => \App\Http\Middleware\AccountantMiddleware::class,
            'role.devotee' => \App\Http\Middleware\DevoteeMiddleware::class,
            'role.committee' => \App\Http\Middleware\CommitteeMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
