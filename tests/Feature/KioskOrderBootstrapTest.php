<?php

namespace Tests\Feature;

use App\Models\KioskDevice;
use App\Models\Ticket;
use App\Models\User;
use App\Services\KioskDeviceService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Http\Controllers\KioskOrderController::bootstrap() — the kiosk's one read-only, module-
 * aware endpoint. Device-auth only (no session-login needed), see routes/web.php.
 */
class KioskOrderBootstrapTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function pairedDevice(string $module, ?int $eventId = null): array
    {
        $admin = $this->admin();
        $device = KioskDeviceService::register('Bootstrap Test Kiosk', null, $module, $eventId, null, null, $admin);
        $code = KioskDeviceService::generatePairingCode($device, $admin);
        $result = KioskDeviceService::redeemPairingCode($code, '127.0.0.1');

        return [$result['device'], $result['token']];
    }

    private function makeEvent(string $status = 'Active'): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Bootstrap Test Event', 'event_date' => now()->toDateString(), 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_tickets_module_device_gets_the_active_ticket_catalog(): void
    {
        Ticket::create(['name' => 'Visible Ticket', 'price' => 5.00, 'status' => 'Active']);
        Ticket::create(['name' => 'Hidden Ticket', 'price' => 5.00, 'status' => 'Inactive']);
        [, $token] = $this->pairedDevice('tickets');

        $response = $this->getJson('/kiosk/api/bootstrap', ['X-Kiosk-Device-Token' => $token]);

        $response->assertOk()->assertJson(['module' => 'tickets']);
        $names = collect($response->json('catalog'))->pluck('name');
        $this->assertTrue($names->contains('Visible Ticket'));
        $this->assertFalse($names->contains('Hidden Ticket'));
    }

    public function test_donations_module_device_gets_its_own_events_options(): void
    {
        $eventId = $this->makeEvent();
        DB::table('event_donation_options')->insert([
            'event_id' => $eventId, 'label' => 'Test Tier', 'amount' => 21, 'allow_quantity' => false,
            'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        [, $token] = $this->pairedDevice('donations', $eventId);

        $response = $this->getJson('/kiosk/api/bootstrap', ['X-Kiosk-Device-Token' => $token]);

        $response->assertOk()->assertJson(['module' => 'donations']);
        $this->assertEquals($eventId, $response->json('event.id'));
        $this->assertCount(1, $response->json('donation_options'));
    }

    public function test_donations_module_device_reports_closed_for_a_completed_event(): void
    {
        $eventId = $this->makeEvent('Completed');
        [, $token] = $this->pairedDevice('donations', $eventId);

        $response = $this->getJson('/kiosk/api/bootstrap', ['X-Kiosk-Device-Token' => $token]);

        $response->assertOk()->assertJson(['module' => 'donations', 'closed' => true]);
    }

    public function test_unauthenticated_request_is_still_denied(): void
    {
        $this->getJson('/kiosk/api/bootstrap')->assertStatus(401);
    }
}
