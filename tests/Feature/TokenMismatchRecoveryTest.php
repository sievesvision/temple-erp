<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * A 419 "Page Expired" (CSRF token mismatch) is the normal outcome of a kiosk PIN pad or
 * login form sitting open past SESSION_LIFETIME before anyone taps it — not an edge case on
 * a counter terminal left idle overnight. The stock Laravel error page is a dead end there
 * (no navigation at all), so bootstrap/app.php registers a render() handler that bounces
 * back to a working login screen instead — the kiosk one for a kiosk-originated request, the
 * general one otherwise — with a message explaining why, rather than silently dropping the
 * user on a blank screen.
 *
 * PHPUnit runs with CSRF verification itself disabled (Illuminate's VerifyCsrfToken skips
 * enforcement under runningUnitTests()), so an actual mismatched-token POST can't be
 * exercised through the HTTP kernel here — instead this invokes the registered exception
 * render() callback directly, the same way Laravel's own exception handler would dispatch to
 * it for a real TokenMismatchException.
 */
class TokenMismatchRecoveryTest extends TestCase
{
    private function renderTokenMismatchFor(string $url)
    {
        $request = Request::create($url, 'POST');
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);

        return app(ExceptionHandler::class)->render($request, new TokenMismatchException());
    }

    public function test_a_csrf_mismatch_on_the_pin_login_route_redirects_to_kiosk_login_with_a_message(): void
    {
        $response = $this->renderTokenMismatchFor(route('kiosk.pin-login'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('kiosk.login'), $response->headers->get('Location'));
        $this->assertSame('Your session timed out — please try again.', session('error'));
    }

    public function test_a_csrf_mismatch_on_the_pin_settings_update_redirects_to_kiosk_login(): void
    {
        $response = $this->renderTokenMismatchFor(route('kiosk.pin.update'));

        $this->assertSame(route('kiosk.login'), $response->headers->get('Location'));
    }

    public function test_a_csrf_mismatch_on_the_select_grid_choice_redirects_to_kiosk_login(): void
    {
        $response = $this->renderTokenMismatchFor(route('kiosk.select.choose'));

        $this->assertSame(route('kiosk.login'), $response->headers->get('Location'));
    }

    public function test_a_csrf_mismatch_on_the_kiosk_email_panel_submission_redirects_to_kiosk_login(): void
    {
        $response = $this->renderTokenMismatchFor(route('login.post', ['from' => 'kiosk']));

        $this->assertSame(route('kiosk.login'), $response->headers->get('Location'));
    }

    public function test_a_csrf_mismatch_on_the_general_login_page_redirects_to_the_general_login(): void
    {
        $response = $this->renderTokenMismatchFor(route('login.post'));

        $this->assertSame(route('login'), $response->headers->get('Location'));
        $this->assertSame('Your session timed out — please try again.', session('error'));
    }
}
