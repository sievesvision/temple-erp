<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Pooja;
use App\Models\Priest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase D of the Pooja Bookings plan: the Pooja Portal's (Ticket Console's embedded
 * Manage Bookings page) "Create Booking" modal, posting to the new admin.bookings.store
 * route shared with Phase E (Ticket POS). See the plan's own Tests section.
 */
class PoojaPortalBookingCreationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function activePooja(): Pooja
    {
        return Pooja::create([
            'pooja_name' => 'Portal Test Archana',
            'pooja_fee' => 80,
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

    public function test_manage_bookings_page_shows_the_create_booking_button_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->activePooja();

        $response = $this->actingAs($admin)->get(route('admin.bookings.index'));

        $response->assertOk();
        $response->assertSee('Create Booking');
        $response->assertSee('createBookingModal', false);
    }

    public function test_manage_bookings_page_hides_the_create_booking_button_without_add_permission(): void
    {
        // The route itself is Admin/Committee-only, and Committee is seeded with can_add
        // = true for 'bookings' by default — so to exercise the "no add permission" UI
        // branch at all, revoke it from Committee directly, the same state an admin could
        // reach via the Role Permissions screen.
        \App\Models\RolePermission::where('role', 'Committee')->where('resource', 'bookings')->update(['can_add' => false]);
        $committee = User::factory()->create(['role' => 'Committee']);
        $this->activePooja();

        $response = $this->actingAs($committee)->get(route('admin.bookings.index'));

        $response->assertOk();
        $response->assertDontSee('createBookingModal', false);
    }

    public function test_admin_can_create_a_booking_via_the_shared_store_route(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->activePriest();
        $pooja = $this->activePooja();

        $response = $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Portal Devotee',
            'devotee_mobile' => '0477001122',
            'devotee_email' => 'portal.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertSame('Confirmed', $booking->booking_status);
    }

    public function test_a_role_without_add_permission_is_rejected(): void
    {
        $trustee = User::factory()->create(['role' => 'Trustee']);
        $this->activePriest();
        $pooja = $this->activePooja();

        $response = $this->actingAs($trustee)->post(route('admin.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Blocked Devotee',
            'devotee_mobile' => '0477001133',
            'devotee_email' => 'blocked.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, Booking::count());
    }

    public function test_an_ajax_caller_gets_json_back(): void
    {
        $ticketController = User::factory()->create(['role' => 'Ticket Controller']);
        $this->activePriest();
        $pooja = $this->activePooja();

        $response = $this->actingAs($ticketController)->postJson(route('admin.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'AJAX Devotee',
            'devotee_mobile' => '0477001144',
            'devotee_email' => 'ajax.devotee@example.com',
            'payment_method' => 'Bank',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame('Confirmed', Booking::first()->booking_status);
    }

    public function test_preferred_priest_who_is_unavailable_is_rejected_with_a_clear_error(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $priest = $this->activePriest();
        $pooja = $this->activePooja();
        $date = now()->addDay()->toDateString();

        // Book the priest solid for that exact slot first via the same shared endpoint.
        $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => $date,
            'booking_time' => '10:00:00',
            'priest_option' => 'preferred',
            'preferred_priest_id' => $priest->priest_id,
            'devotee_name' => 'First Devotee',
            'devotee_mobile' => '0477001155',
            'devotee_email' => 'first.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => $date,
            'booking_time' => '10:00:00',
            'priest_option' => 'preferred',
            'preferred_priest_id' => $priest->priest_id,
            'devotee_name' => 'Second Devotee',
            'devotee_mobile' => '0477001166',
            'devotee_email' => 'second.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, Booking::count());
    }
}
