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
        $middleware->appendToGroup('web', \App\Http\Middleware\RoleSwitchMiddleware::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'role.admin' => \App\Http\Middleware\AdminMiddleware::class,
            'role.priest' => \App\Http\Middleware\PriestMiddleware::class,
            'role.trustee' => \App\Http\Middleware\TrusteeMiddleware::class,
            'role.staff' => \App\Http\Middleware\StaffMiddleware::class,
            'role.accountant' => \App\Http\Middleware\AccountantMiddleware::class,
            'role.devotee' => \App\Http\Middleware\DevoteeMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
