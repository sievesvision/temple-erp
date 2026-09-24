<?php

namespace Tests\Feature;

use App\Http\Middleware\AddHstsHeader;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * See AddHstsHeader's own docblock: tells a browser that has reached the site securely once
 * to always use HTTPS from then on, so it never falls into Hostinger's edge CDN's broken
 * HTTP->HTTPS redirect (which strands a browser on plain HTTP, where the secure-only session/
 * CSRF cookies can never round-trip, causing every kiosk login attempt to fail identically).
 */
class AddHstsHeaderTest extends TestCase
{
    public function test_the_header_is_set_on_a_secure_request(): void
    {
        $middleware = new AddHstsHeader();
        $request = Request::create('https://example.com/kiosk/login');

        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame('max-age=86400', $response->headers->get('Strict-Transport-Security'));
    }

    public function test_the_header_is_not_set_on_a_plain_http_request(): void
    {
        $middleware = new AddHstsHeader();
        $request = Request::create('http://example.com/kiosk/login');

        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }
}
