<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\KioskDeviceService;
use Tests\TestCase;

/**
 * A paired, active, tickets-module kiosk device completing a real Cash checkout end-to-end —
 * proves TicketController::storeOrder()/createOrderFromCart() work completely unmodified when
 * reached via the kiosk's own route + Auth::onceUsingId() service-account session.
 */
class KioskTicketCheckoutTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function pairedDevice(): array
    {
        $admin = $this->admin();
        $device = KioskDeviceService::register('Checkout Test Kiosk', null, 'tickets', null, null, null, $admin);
        $code = KioskDeviceService::generatePairingCode($device, $admin);
        $result = KioskDeviceService::redeemPairingCode($code, '127.0.0.1');

        return [$result['device'], $result['token']];
    }

    public function test_cash_checkout_creates_a_real_order_attributed_to_the_devices_service_user(): void
    {
        $ticket = Ticket::create(['name' => 'Kiosk Test Ticket', 'price' => 7.50, 'status' => 'Active']);
        [$device, $token] = $this->pairedDevice();

        $response = $this->postJson('/kiosk/api/tickets/checkout', [
            'cart_json' => json_encode([['ticket_id' => $ticket->id, 'name' => $ticket->name, 'price' => 7.50, 'quantity' => 3]]),
            'payment_method' => 'Cash',
        ], ['X-Kiosk-Device-Token' => $token]);

        $response->assertOk()->assertJson(['success' => true]);
        $orderId = $response->json('order_id');

        $order = TicketOrder::with('items.stubs')->find($orderId);
        $this->assertNotNull($order);
        $this->assertEquals($device->service_user_id, $order->sold_by);
        $this->assertEquals(22.50, (float) $order->total_amount);
        $this->assertEquals(3, $order->items->first()->stubs->count());
    }

    public function test_empty_cart_is_rejected(): void
    {
        [, $token] = $this->pairedDevice();

        $this->postJson('/kiosk/api/tickets/checkout', [
            'cart_json' => '[]',
            'payment_method' => 'Cash',
        ], ['X-Kiosk-Device-Token' => $token])->assertStatus(422);
    }
}
