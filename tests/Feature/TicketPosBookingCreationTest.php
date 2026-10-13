<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Pooja;
use App\Models\Priest;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase E of the Pooja Bookings plan: the Ticket POS "Book Pooja" topbar button + modal,
 * posting to the same admin.bookings.store route Phase D's Pooja Portal modal uses. See
 * the plan's own Tests section.
 */
class TicketPosBookingCreationTest extends TestCase
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

    private function activePooja(): Pooja
    {
        return Pooja::create([
            'pooja_name' => 'POS Test Archana',
            'pooja_fee' => 45,
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

    public function test_the_book_pooja_button_shows_for_a_role_with_bookings_add_permission(): void
    {
        $admin = $this->admin();
        $this->activePooja();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('Book Pooja');
        $response->assertSee('bookPoojaModal', false);
    }

    public function test_the_book_pooja_button_is_hidden_without_bookings_add_permission(): void
    {
        RolePermission::where('role', 'Committee')->where('resource', 'bookings')->update(['can_add' => false]);
        $committee = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);
        $this->activePooja();

        $response = $this->actingAs($committee)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertDontSee('bookPoojaModal', false);
    }

    public function test_the_ticket_pos_booking_form_creates_a_confirmed_booking_via_ajax(): void
    {
        $admin = $this->admin();
        $this->activePriest();
        $pooja = $this->activePooja();

        $response = $this->actingAs($admin)->postJson(route('admin.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'POS Devotee',
            'devotee_mobile' => '0488112233',
            'devotee_email' => 'pos.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertSame('Confirmed', $booking->booking_status);
    }
}
