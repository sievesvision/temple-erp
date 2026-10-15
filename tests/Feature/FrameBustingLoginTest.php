<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A session that expires while something's embedded in an iframe (EFT Terminal Settings,
 * the order-view receipt — see ticket-pos.blade.php and cash-settlement-pane.blade.php)
 * redirects that iframe's own next navigation to the login page, same as any other guest
 * request. Without breaking out, the login page renders nested inside that small iframe
 * instead of the real page the user needs.
 */
class FrameBustingLoginTest extends TestCase
{
    public function test_the_general_login_page_breaks_out_of_an_iframe(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('window.top !== window.self', false);
        $response->assertSee('window.top.location.href = window.location.href', false);
    }

    public function test_the_pos_login_page_breaks_out_of_an_iframe(): void
    {
        $response = $this->get(route('pos.login'));

        $response->assertOk();
        $response->assertSee('window.top !== window.self', false);
        $response->assertSee('window.top.location.href = window.location.href', false);
    }
}
