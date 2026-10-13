<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Pooja;
use App\Models\Priest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase F of the Pooja Bookings plan: the Priest Portal's "Book a Pooja" form — same
 * single-screen fields as Phases D/E, but priest_id is pre-filled to the logged-in
 * priest and posts to its own priest.bookings.store route (not RolePermission-gated,
 * since it's their own slot). See the plan's own Tests section.
 */
class PriestPortalBookingCreationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function activePooja(): Pooja
    {
        return Pooja::create([
            'pooja_name' => 'Priest Portal Test Archana',
            'pooja_fee' => 55,
            'duration' => '15',
            'status' => 'Active',
            'online_allowed' => false,
        ]);
    }

    private function loggedInPriest(): array
    {
        $user = User::factory()->create(['role' => 'Priest']);
        $priest = Priest::create([
            'priest_id' => 'PRIEST' . $user->id,
            'user_id' => $user->id,
            'employment_status' => 'Active',
        ]);

        return [$user, $priest];
    }

    public function test_the_priest_dashboard_poojas_tab_shows_the_book_a_pooja_button(): void
    {
        [$user] = $this->loggedInPriest();
        $this->activePooja();

        $response = $this->actingAs($user)->get(route('priest.dashboard', ['tab' => 'poojas']));

        $response->assertOk();
        $response->assertSee('Book a Pooja');
        $response->assertSee('priestBookPoojaModal', false);
    }

    public function test_a_priest_can_book_a_pooja_for_themselves_and_it_is_auto_confirmed(): void
    {
        [$user, $priest] = $this->loggedInPriest();
        $pooja = $this->activePooja();

        $response = $this->actingAs($user)->post(route('priest.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Priest Portal Devotee',
            'devotee_mobile' => '0499001122',
            'devotee_email' => 'priest.portal.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertSame('Confirmed', $booking->booking_status);
        $this->assertSame($priest->priest_id, $booking->priest_id);
    }

    public function test_a_priest_cannot_book_themselves_for_a_slot_they_are_already_on_leave_for(): void
    {
        [$user, $priest] = $this->loggedInPriest();
        $pooja = $this->activePooja();
        $date = now()->addDay()->toDateString();

        \DB::table('leave_requests')->insert([
            'priest_id' => $priest->priest_id,
            'start_date' => $date,
            'end_date' => $date,
            'status' => 'Approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('priest.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => $date,
            'booking_time' => '10:00:00',
            'devotee_name' => 'On Leave Devotee',
            'devotee_mobile' => '0499001133',
            'devotee_email' => 'on.leave.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, Booking::count());
    }
}
