<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Pooja;
use App\Models\Priest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C of the Pooja Bookings plan: the public, no-login booking page. See the plan's
 * own Tests section — mandatory-field validation, and that it goes through the same
 * BookingService rather than its own divergent insert.
 */
class PublicPoojaBookingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function activePooja(): Pooja
    {
        return Pooja::create([
            'pooja_name' => 'Public Test Archana',
            'pooja_fee' => 50,
            'duration' => '15',
            'status' => 'Active',
            'online_allowed' => false,
        ]);
    }

    private function activePriest(): Priest
    {
        $user = User::factory()->create(['role' => 'Priest']);

        return Priest::create([
            'priest_id' => 'PRIEST' . $user->id,
            'user_id' => $user->id,
            'employment_status' => 'Active',
        ]);
    }

    public function test_the_public_page_loads_and_lists_active_poojas(): void
    {
        $pooja = $this->activePooja();

        $response = $this->get(route('booking.public.show'));

        $response->assertOk();
        $response->assertSee($pooja->pooja_name);
    }

    public function test_a_guest_can_book_a_pooja_without_logging_in(): void
    {
        $this->activePriest();
        $pooja = $this->activePooja();

        $response = $this->post(route('booking.public.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Public Guest',
            'devotee_mobile' => '0433221100',
            'devotee_email' => 'public.guest@example.com',
            'payment_method' => 'Bank',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success_booking');

        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertSame('Pending', $booking->booking_status);
        $this->assertSame(1, User::where('email', 'public.guest@example.com')->count());
    }

    public function test_name_mobile_and_email_are_all_mandatory(): void
    {
        $this->activePriest();
        $pooja = $this->activePooja();

        $response = $this->post(route('booking.public.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'payment_method' => 'Bank',
            // devotee_name/devotee_mobile/devotee_email omitted
        ]);

        $response->assertSessionHasErrors(['devotee_name', 'devotee_mobile', 'devotee_email']);
        $this->assertSame(0, Booking::count());
    }

    public function test_a_repeat_guest_with_the_same_email_reuses_their_account(): void
    {
        $this->activePriest();
        $pooja = $this->activePooja();

        $this->post(route('booking.public.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '09:00:00',
            'devotee_name' => 'Repeat Guest',
            'devotee_mobile' => '0455443322',
            'devotee_email' => 'repeat.guest@example.com',
            'payment_method' => 'Cash',
        ]);

        $this->post(route('booking.public.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'booking_time' => '11:00:00',
            'devotee_name' => 'Repeat Guest',
            'devotee_mobile' => '0455443322',
            'devotee_email' => 'repeat.guest@example.com',
            'payment_method' => 'Cash',
        ]);

        $this->assertSame(1, User::where('email', 'repeat.guest@example.com')->count());
        $this->assertSame(2, Booking::count());
    }

    public function test_online_booking_type_is_rejected_from_the_public_form(): void
    {
        $this->activePriest();
        $pooja = $this->activePooja();

        $response = $this->post(route('booking.public.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Guest',
            'devotee_mobile' => '0466554433',
            'devotee_email' => 'guest.online@example.com',
            'payment_method' => 'UPI',
        ]);

        $response->assertSessionHasErrors(['payment_method']);
    }
}
