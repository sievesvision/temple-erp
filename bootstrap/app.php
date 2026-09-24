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
            $kioskRoutes = ['admin.events.pos', 'admin.tickets.pos', 'kiosk.select'];
            if ($request->route() && in_array($request->route()->getName(), $kioskRoutes, true)) {
                return route('kiosk.login');
            }
            return route('login');
        });

        $middleware->appendToGroup('web', \App\Http\Middleware\RoleSwitchMiddleware::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\AddHstsHeader::class);
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
        // A 419 "Page Expired" is Laravel's default response to a CSRF token mismatch — the
        // stock error page is a dead end on a kiosk terminal (no navigation, no way back to a
        // working form) and is exactly what happens when a kiosk PIN pad or login form sits
        // open long enough for the session/token to go stale before anyone taps it (a counter
        // left idle overnight, well past SESSION_LIFETIME, is the normal case here, not an
        // edge case). Redirect back to a fresh, working login screen instead — the kiosk one
        // if the request was a kiosk route (PIN login/settings/select) or the kiosk's own
        // email panel (flagged via ?from=kiosk on that shared login.post action, since that
        // route is also posted to by the general login page), the general one otherwise.
        // Laravel's own Handler::prepareException() converts TokenMismatchException into a
        // plain HttpException(419, ..., $previous) BEFORE any custom render() callback gets
        // a chance to run — a closure type-hinted for TokenMismatchException itself would
        // never match. Check the status code instead (419 has no other source in this app).
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            $kioskRoutes = ['kiosk.pin-login', 'kiosk.pin.update', 'kiosk.select.choose'];
            $isKiosk = ($request->route() && in_array($request->route()->getName(), $kioskRoutes, true))
                || $request->query('from') === 'kiosk';

            return redirect()->route($isKiosk ? 'kiosk.login' : 'login')
                ->with('error', 'Your session timed out — please try again.');
        });
    })->create();
