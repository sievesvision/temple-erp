<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\KioskDeviceService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A paired, active, donations-module kiosk device completing a real Cash donation end-to-end —
 * proves DonationController::storeGuestDonation() works completely unmodified when reached via
 * the kiosk's own route + Auth::onceUsingId() service-account session, and that the new
 * additive donation_id response key is present.
 */
class KioskDonationCheckoutTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Donation Checkout Test Event', 'event_date' => now()->toDateString(), 'status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function pairedDevice(int $eventId): string
    {
        $admin = $this->admin();
        $device = KioskDeviceService::register('Donation Checkout Kiosk', null, 'donations', $eventId, null, null, $admin);
        $code = KioskDeviceService::generatePairingCode($device, $admin);
        $result = KioskDeviceService::redeemPairingCode($code, '127.0.0.1');

        return $result['token'];
    }

    public function test_cash_donation_creates_a_real_row_and_returns_donation_id(): void
    {
        $eventId = $this->makeEvent();
        $token = $this->pairedDevice($eventId);

        $response = $this->postJson('/kiosk/api/donations/guest', [
            'donor_name' => 'Kiosk Donor',
            'event_id' => $eventId,
            'amount' => 51,
            'purpose' => 'General Donation',
            'payment_method' => 'Cash',
            'donation_date' => now()->toDateString(),
        ], ['X-Kiosk-Device-Token' => $token]);

        $response->assertOk()->assertJson(['success' => true]);
        $donationId = $response->json('donation_id');
        $this->assertNotNull($donationId);

        $row = DB::table('donations_without_logins')->find($donationId);
        $this->assertNotNull($row);
        $this->assertEquals('Kiosk Donor', $row->donor_name);
        $this->assertEquals(51, (float) $row->amount);
        $this->assertEquals($eventId, $row->event_id);
    }
}
