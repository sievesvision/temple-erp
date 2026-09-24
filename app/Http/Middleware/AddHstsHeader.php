<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Tells a browser that has successfully reached this site over HTTPS to always use HTTPS
 * for it going forward, never plain HTTP again — without this, a browser visiting via plain
 * http:// (a typed/bookmarked/shared link missing "https://") hits Hostinger's edge CDN's
 * own broken HTTP->HTTPS redirect (it returns a relative Location header instead of an
 * absolute https:// one, so the browser stays on HTTP and the request eventually times out
 * rather than reaching the app at all) — and since the session/CSRF cookies are correctly
 * marked secure-only, a browser stuck on HTTP can never actually hold one, so every kiosk
 * login attempt from there looks like a fresh, cookie-less request and fails identically
 * every time. This can't fix the very first visit (that's a hosting-platform config issue,
 * outside this codebase), but it stops the SAME browser from ever falling into it again once
 * it has reached the site securely at least once. A short max-age (a day, not the usual
 * year+preload) so a real TLS/cert problem self-heals quickly rather than locking visitors
 * out of the site entirely until it's fixed.
 */
class AddHstsHeader
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=86400');
        }

        return $response;
    }
}
