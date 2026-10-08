<?php

namespace Tests\Feature;

use App\Models\KioskDevice;
use App\Models\User;
use App\Services\KioskDeviceService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin-side management of the kiosk device registry (list, register, activate, deactivate,
 * revoke, rotate, delete) — gated by App\Services\KioskAccess::canManageRegistry(). See
 * KioskDeviceConfigurationAccessTest for the finer module/event-scoped canConfigure() gate.
 */
class KioskDeviceAdminTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function unprivilegedDevotee(): User
    {
        return User::factory()->create(['role' => 'Devotee', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function device(): KioskDevice
    {
        return KioskDeviceService::register('Admin Test Kiosk', null, 'tickets', null, null, null, $this->admin());
    }

    public function test_a_user_with_no_grant_gets_403_from_every_admin_route(): void
    {
        $user = $this->unprivilegedDevotee();
        $device = $this->device();

        $this->actingAs($user)->get('/admin/kiosk-devices')->assertStatus(403);
        $this->actingAs($user)->post('/admin/kiosk-devices', ['name' => 'X'])
            ->assertRedirect()->assertSessionHas('error');
        $this->actingAs($user)->post("/admin/kiosk-devices/{$device->id}/activate")
            ->assertRedirect()->assertSessionHas('error');
        $this->actingAs($user)->postJson("/admin/kiosk-devices/{$device->id}/pairing-code")
            ->assertStatus(403);
        $this->actingAs($user)->delete("/admin/kiosk-devices/{$device->id}")
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_admin_can_register_a_device(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/kiosk-devices', [
            'name' => 'New Kiosk', 'module' => 'tickets',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kiosk_devices', ['name' => 'New Kiosk', 'status' => 'pending']);
    }

    public function test_mutating_actions_write_audit_log_rows(): void
    {
        $admin = $this->admin();
        $device = $this->device();

        $this->actingAs($admin)->post("/admin/kiosk-devices/{$device->id}/activate");
        $this->assertTrue(DB::table('audit_logs')->where('action', 'like', "%Activated kiosk device%{$device->id}%")->exists());

        $this->actingAs($admin)->post("/admin/kiosk-devices/{$device->id}/revoke");
        $this->assertTrue(DB::table('audit_logs')->where('action', 'like', "%Revoked kiosk device%{$device->id}%")->exists());
    }

    public function test_pairing_code_endpoint_returns_json(): void
    {
        $admin = $this->admin();
        $device = $this->device();

        $response = $this->actingAs($admin)->postJson("/admin/kiosk-devices/{$device->id}/pairing-code");

        $response->assertOk()->assertJsonStructure(['success', 'code', 'expires_in_minutes']);
    }

    public function test_destroy_removes_the_device(): void
    {
        $admin = $this->admin();
        $device = $this->device();

        $this->actingAs($admin)->delete("/admin/kiosk-devices/{$device->id}")->assertRedirect();

        $this->assertDatabaseMissing('kiosk_devices', ['id' => $device->id]);
    }
}
