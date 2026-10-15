<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Models\Booking;
use App\Models\Pooja;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pooja bookings can be paid on the EFT terminal, reusing the same charge-start/poll
 * machinery (and 'record_type' dispatch) tickets and donations already use — see
 * DonationController::startEftCharge()/createDonationIfApprovedPurchaseUnrecorded() and
 * BookingController::createBookingFromLedgerMeta(), the new booking-specific branch.
 */
class PoojaBookingEftPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_createBookingFromLedgerMeta_creates_a_paid_confirmed_booking(): void
    {
        $admin = $this->admin();
        $pooja = Pooja::create(['pooja_name' => 'EFT Meta Pooja', 'pooja_fee' => 75, 'status' => 'Active']);

        $meta = [
            'record_type' => 'pooja_booking',
            'pooja_id' => $pooja->pooja_id,
            'custom_pooja_label' => null,
            'custom_fee' => null,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'priest_option' => 'auto',
            'preferred_priest_id' => null,
            'devotee_name' => 'EFT Meta Devotee',
            'devotee_mobile' => '0433998877',
            'devotee_email' => 'eft.meta.devotee@example.com',
            'participant_details' => 'Also booking for grandmother — Vishwamitra gothram, Ashwini nakshatram',
            'created_by_role' => 'Admin',
            'created_by_user_id' => $admin->id,
        ];

        $bookingId = (new BookingController())->createBookingFromLedgerMeta($meta, 'TXNREF001');

        $this->assertNotNull($bookingId);
        $booking = Booking::find($bookingId);
        $this->assertSame('EFT Terminal', $booking->payment_method);
        $this->assertSame('Paid', $booking->payment_status);
        $this->assertSame('Confirmed', $booking->booking_status);
        $this->assertEquals(75, (float) $booking->amount);
        $this->assertSame('Also booking for grandmother — Vishwamitra gothram, Ashwini nakshatram', $booking->participant_details);
    }

    public function test_createBookingFromLedgerMeta_handles_a_custom_pooja(): void
    {
        $admin = $this->admin();
        $anchorId = (int) Setting::get('custom_pooja_id');

        $meta = [
            'record_type' => 'pooja_booking',
            'pooja_id' => $anchorId,
            'custom_pooja_label' => 'EFT Custom Pooja Request',
            'custom_fee' => 123.45,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '11:00:00',
            'priest_option' => 'auto',
            'preferred_priest_id' => null,
            'devotee_name' => 'EFT Custom Devotee',
            'devotee_mobile' => '0433998866',
            'devotee_email' => 'eft.custom.devotee@example.com',
            'created_by_role' => 'Admin',
            'created_by_user_id' => $admin->id,
        ];

        $bookingId = (new BookingController())->createBookingFromLedgerMeta($meta, 'TXNREF002');

        $booking = Booking::find($bookingId);
        $this->assertSame('EFT Custom Pooja Request', $booking->custom_pooja_label);
        $this->assertEquals(123.45, (float) $booking->amount);
        $this->assertSame('Paid', $booking->payment_status);
    }

    public function test_createBookingFromLedgerMeta_returns_null_without_enough_data(): void
    {
        $bookingId = (new BookingController())->createBookingFromLedgerMeta(['record_type' => 'pooja_booking'], 'TXNREF003');

        $this->assertNull($bookingId);
        $this->assertSame(0, Booking::count());
    }

    public function test_start_eft_charge_requires_bookings_add_permission(): void
    {
        // Trustee has no role_permissions row for 'bookings' at all (can_add defaults to
        // false) — Staff, by contrast, is seeded with it by default (see
        // 2026_08_25_000000_create_role_permissions_table.php), so it can't stand in here.
        $trustee = User::factory()->create(['role' => 'Trustee', 'mobile' => fake()->unique()->numerify('04########')]);
        $pooja = Pooja::create(['pooja_name' => 'EFT Perm Pooja', 'pooja_fee' => 50, 'status' => 'Active']);

        $response = $this->actingAs($trustee)->postJson(route('admin.eft.charge.start'), [
            'record_type' => 'pooja_booking',
            'amount' => 50,
            'client_ref' => 'ref-' . uniqid(),
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Perm Test',
            'devotee_mobile' => '0433998855',
            'devotee_email' => 'perm.test@example.com',
        ]);

        $response->assertForbidden();
    }
}
