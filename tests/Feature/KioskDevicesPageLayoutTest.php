<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\KioskDeviceService;
use Tests\TestCase;

/**
 * The Kiosk Devices page used to be a completely standalone HTML document (its own
 * <!doctype html>, no @extends) — no sidebar, no way back to the dashboard short of the
 * browser's back button, visually disconnected from every other admin screen including
 * its own siblings (Portals, Registers). This covers the fix: it now renders inside the
 * shared admin layout, and shows the shared kiosk access URLs directly on each card.
 */
class KioskDevicesPageLayoutTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_the_page_renders_inside_the_shared_admin_layout(): void
    {
        $admin = $this->admin();
        KioskDeviceService::register('Lobby Kiosk', 'Entrance', 'tickets', null, null, null, $admin);

        $response = $this->actingAs($admin)->get(route('admin.kiosk-devices.index'));

        $response->assertOk();
        // Anything rendered by the shared layout's own sidebar proves this is no longer a
        // bare standalone document — Dashboard is a link every admin page's sidebar carries.
        $response->assertSee('Dashboard');
        $response->assertDontSee('<!doctype html>', false);
    }

    public function test_the_page_shows_the_pairing_and_kiosk_urls(): void
    {
        $admin = $this->admin();
        KioskDeviceService::register('Lobby Kiosk', 'Entrance', 'tickets', null, null, null, $admin);

        $response = $this->actingAs($admin)->get(route('admin.kiosk-devices.index'));

        $response->assertOk();
        $response->assertSee(url('/kiosk/pair'));
        $response->assertSee(url('/kiosk/welcome'));
    }

    public function test_the_page_shows_payment_methods_and_module_details(): void
    {
        $admin = $this->admin();
        KioskDeviceService::register('Donation Kiosk', null, 'tickets', null, null, ['Cash', 'UPI'], $admin);

        $response = $this->actingAs($admin)->get(route('admin.kiosk-devices.index'));

        $response->assertOk();
        $response->assertSee('Cash');
        $response->assertSee('UPI');
        $response->assertSee('Tickets');
    }
}
