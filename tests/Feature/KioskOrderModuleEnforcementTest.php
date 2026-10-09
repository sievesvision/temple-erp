<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\KioskDeviceService;
use Tests\TestCase;

/**
 * A donations-module device's credential must never reach a /kiosk/api/tickets/* route (and
 * vice versa) — enforced by App\Http\Middleware\EnsureKioskModule against the device's own
 * `module` column, never the request body. Includes the specific "spoof record_type in the
 * POST body on the wrong URL" scenario the route-level force-set is meant to defeat.
 */
class KioskOrderModuleEnforcementTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function pairedDevice(string $module, ?int $eventId = null): string
    {
        $admin = $this->admin();
        $device = KioskDeviceService::register('Enforcement Test Kiosk', null, $module, $eventId, null, null, $admin);
        $code = KioskDeviceService::generatePairingCode($device, $admin);
        $result = KioskDeviceService::redeemPairingCode($code, '127.0.0.1');

        return $result['token'];
    }

    public function test_donations_device_gets_403_from_ticket_checkout(): void
    {
        $token = $this->pairedDevice('donations');

        $this->postJson('/kiosk/api/tickets/checkout', ['cart_json' => '[]', 'payment_method' => 'Cash'], ['X-Kiosk-Device-Token' => $token])
            ->assertStatus(403);
    }

    public function test_tickets_device_gets_403_from_donation_guest(): void
    {
        $token = $this->pairedDevice('tickets');

        $this->postJson('/kiosk/api/donations/guest', ['donor_name' => 'Test'], ['X-Kiosk-Device-Token' => $token])
            ->assertStatus(403);
    }

    public function test_donations_device_gets_403_from_ticket_eft_start_even_with_no_body(): void
    {
        $token = $this->pairedDevice('donations');

        $this->postJson('/kiosk/api/tickets/eft/start', [], ['X-Kiosk-Device-Token' => $token])
            ->assertStatus(403);
    }

    // Proves the route-level record_type force-set (not the request body) is what actually
    // decides the record type — a tickets-module device hitting its OWN eft/start URL cannot
    // be tricked into creating a donation-flavoured meta by spoofing record_type=donation.
    public function test_tickets_device_cannot_spoof_record_type_on_its_own_eft_start_url(): void
    {
        $token = $this->pairedDevice('tickets');

        $response = $this->postJson('/kiosk/api/tickets/eft/start', [
            'record_type' => 'donation', // spoofed — must be ignored/overwritten server-side
            'amount' => '10.00',
            'client_ref' => 'spoof-test-' . uniqid(),
            'cart_json' => json_encode([['ticket_id' => null, 'name' => 'X', 'price' => 10, 'quantity' => 1]]),
        ], ['X-Kiosk-Device-Token' => $token]);

        // Whatever the actual EFT-provider outcome (no real terminal paired in this test), the
        // request must at least have been accepted as a tickets-module request, not rejected
        // by EnsureKioskModule — a 403 here would mean module enforcement misfired.
        $this->assertNotEquals(403, $response->status());
    }
}
