<?php

namespace Tests\Feature;

use App\Models\KioskDevice;
use App\Models\User;
use App\Services\KioskDeviceService;
use Tests\TestCase;

/**
 * Who/what can actually reach a /kiosk/* API route — App\Http\Middleware\
 * AuthenticateKioskDevice, backed by App\Services\KioskDeviceService::authenticate(). No
 * Auth::user() session is involved here at all; this is pure device-credential auth.
 */
class KioskDeviceAuthTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function pairedDevice(string $name = 'Test Kiosk'): array
    {
        $device = KioskDeviceService::register($name, null, 'tickets', null, null, null, $this->admin());
        $code = KioskDeviceService::generatePairingCode($device, $this->admin());
        $result = KioskDeviceService::redeemPairingCode($code, '127.0.0.1');

        return [$result['device'], $result['token']];
    }

    public function test_no_header_is_denied(): void
    {
        $this->getJson('/kiosk/ping')->assertStatus(401);
    }

    public function test_garbage_token_is_denied(): void
    {
        $this->getJson('/kiosk/ping', ['X-Kiosk-Device-Token' => 'not-a-real-token'])->assertStatus(401);
    }

    public function test_active_device_with_valid_token_is_authenticated(): void
    {
        [$device, $token] = $this->pairedDevice();

        $response = $this->getJson('/kiosk/ping', ['X-Kiosk-Device-Token' => $token]);

        $response->assertOk()->assertJson(['device' => $device->name]);
        $this->assertNotNull($device->fresh()->last_activity_at);
    }

    public function test_revoked_device_is_denied_and_credential_is_actually_nulled(): void
    {
        [$device, $token] = $this->pairedDevice();

        KioskDeviceService::revoke($device, $this->admin());

        $this->assertNull($device->fresh()->credential_token);
        $this->getJson('/kiosk/ping', ['X-Kiosk-Device-Token' => $token])->assertStatus(401);
    }

    public function test_deactivated_device_is_denied(): void
    {
        [$device, $token] = $this->pairedDevice();

        KioskDeviceService::deactivate($device, $this->admin());

        $this->getJson('/kiosk/ping', ['X-Kiosk-Device-Token' => $token])->assertStatus(401);
    }

    // The adapted "cross-branch" scenario — strict 1:1 token-to-device binding, no collision.
    public function test_a_credential_issued_for_one_device_never_authenticates_as_another(): void
    {
        [$deviceA, $tokenA] = $this->pairedDevice('Device A');
        [$deviceB, $tokenB] = $this->pairedDevice('Device B');

        $this->getJson('/kiosk/ping', ['X-Kiosk-Device-Token' => $tokenA])
            ->assertOk()->assertJson(['device' => 'Device A']);
        $this->getJson('/kiosk/ping', ['X-Kiosk-Device-Token' => $tokenB])
            ->assertOk()->assertJson(['device' => 'Device B']);

        $this->assertNotEquals($deviceA->id, $deviceB->id);
    }
}
