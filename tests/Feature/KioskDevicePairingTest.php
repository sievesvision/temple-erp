<?php

namespace Tests\Feature;

use App\Models\KioskDevice;
use App\Models\KioskPairingCode;
use App\Models\User;
use App\Services\KioskDeviceService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The one-time pairing code lifecycle — generation, redemption, expiry, single-use
 * enforcement. See App\Services\KioskDeviceService::generatePairingCode()/
 * redeemPairingCode().
 */
class KioskDevicePairingTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function pendingDevice(): KioskDevice
    {
        return KioskDeviceService::register('Test Kiosk', null, 'tickets', null, null, null, $this->admin());
    }

    public function test_expired_pairing_code_is_rejected(): void
    {
        $device = $this->pendingDevice();
        $admin = $this->admin();
        $code = KioskDeviceService::generatePairingCode($device, $admin);

        // Force the just-generated code into the past.
        KioskPairingCode::where('kiosk_device_id', $device->id)->update(['expires_at' => now()->subMinute()]);

        $result = KioskDeviceService::redeemPairingCode($code, '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertEquals('pending', $device->fresh()->status);
    }

    public function test_already_redeemed_code_is_rejected(): void
    {
        $device = $this->pendingDevice();
        $code = KioskDeviceService::generatePairingCode($device, $this->admin());

        $first = KioskDeviceService::redeemPairingCode($code, '127.0.0.1');
        $this->assertTrue($first['success']);

        $second = KioskDeviceService::redeemPairingCode($code, '127.0.0.1');
        $this->assertFalse($second['success']);
    }

    public function test_successful_redemption_activates_the_device_and_issues_a_working_credential(): void
    {
        $device = $this->pendingDevice();
        $code = KioskDeviceService::generatePairingCode($device, $this->admin());

        $result = KioskDeviceService::redeemPairingCode($code, '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertEquals('active', $device->fresh()->status);
        $this->assertNotNull($result['token']);
        $this->assertSame($device->id, KioskDeviceService::authenticate($result['token'])->id);
    }

    public function test_pairing_code_plaintext_is_never_stored(): void
    {
        $device = $this->pendingDevice();
        $code = KioskDeviceService::generatePairingCode($device, $this->admin());

        $row = KioskPairingCode::where('kiosk_device_id', $device->id)->first();

        $this->assertNotEquals($code, $row->code_hash);
        $this->assertTrue(Hash::check($code, $row->code_hash));
    }

    // A wrong code and a genuinely expired code must be indistinguishable to the caller —
    // the exact same message either way, so neither case acts as an oracle for guessing.
    public function test_wrong_code_and_expired_code_produce_the_identical_message(): void
    {
        $wrongCodeResult = KioskDeviceService::redeemPairingCode('NOPE0000', '127.0.0.1');

        $device = $this->pendingDevice();
        $code = KioskDeviceService::generatePairingCode($device, $this->admin());
        KioskPairingCode::where('kiosk_device_id', $device->id)->update(['expires_at' => now()->subMinute()]);
        $expiredCodeResult = KioskDeviceService::redeemPairingCode($code, '127.0.0.1');

        $this->assertFalse($wrongCodeResult['success']);
        $this->assertFalse($expiredCodeResult['success']);
        $this->assertSame($wrongCodeResult['message'], $expiredCodeResult['message']);
    }
}
