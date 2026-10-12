<?php

namespace App\Http\Middleware;

use App\Services\RegisterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads the register_token cookie if present and stamps the resolved Register onto the
 * request — never blocking. A missing or invalid cookie is never an error: every POS page
 * must keep working exactly as it does today with no register paired (see
 * EftTerminal::defaultForContext()'s own fallback chain). Registered globally on the 'web'
 * group, same as RoleSwitchMiddleware, since a register's identity is independent of which
 * staff member happens to be logged in.
 */
class IdentifyRegister
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(RegisterService::COOKIE_NAME);

        if ($token) {
            $register = RegisterService::authenticate($token);
            if ($register) {
                $request->attributes->set('register', $register);
                $register->touchActivity();
            }
        }

        return $next($request);
    }
}
