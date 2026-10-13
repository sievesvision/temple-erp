<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Pooja;
use App\Models\Priest;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Three follow-up requests on top of the Pooja Bookings feature:
 * 1. Priest assignment is never mandatory — a booking must still succeed when nobody is
 *    available (or nobody picked), left unassigned (priest_id null) rather than blocked.
 * 2. A "custom pooja" — something not in the catalog — lets the counter enter its own
 *    description and fee at booking time (BookingService::create()'s custom_pooja_label/
 *    custom_fee, anchored to the permanent "Custom Pooja" catalog row).
 * 3. Cash/Counter payment at the counter (Pooja Portal, Ticket POS, Priest Portal, Staff
 *    counter) marks the booking Paid immediately rather than leaving it Pending.
 */
class PoojaBookingFlexibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function activePooja(): Pooja
    {
        return Pooja::create([
            'pooja_name' => 'Flex Test Archana',
            'pooja_fee' => 50,
            'duration' => '15',
            'status' => 'Active',
            'online_allowed' => false,
        ]);
    }

    public function test_a_booking_succeeds_with_no_priests_in_the_system_at_all(): void
    {
        $pooja = $this->activePooja();

        $booking = (new BookingService())->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'No Priest Devotee',
            'devotee_mobile' => '0411000001',
            'devotee_email' => 'no.priest.devotee@example.com',
        ], 'Staff', null, false);

        $this->assertNotNull($booking->booking_id);
        $this->assertNull($booking->priest_id);
        $this->assertSame('Confirmed', $booking->booking_status);
    }

    public function test_an_explicitly_preferred_but_unavailable_priest_still_lets_the_booking_through_unassigned(): void
    {
        $priestUser = User::factory()->create(['role' => 'Priest']);
        $priest = Priest::create(['priest_id' => 'PRIESTX1', 'user_id' => $priestUser->id, 'employment_status' => 'Active']);
        $pooja = $this->activePooja();
        $date = now()->addDay()->toDateString();

        // Put the priest on leave for that date so they're unavailable.
        \DB::table('leave_requests')->insert([
            'priest_id' => $priest->priest_id, 'start_date' => $date, 'end_date' => $date,
            'status' => 'Approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $booking = (new BookingService())->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => $date,
            'booking_time' => '10:00:00',
            'priest_option' => 'preferred',
            'preferred_priest_id' => $priest->priest_id,
            'devotee_name' => 'Unavailable Priest Devotee',
            'devotee_mobile' => '0411000002',
            'devotee_email' => 'unavailable.priest.devotee@example.com',
        ], 'Staff', null, false);

        $this->assertNotNull($booking->booking_id);
        $this->assertNull($booking->priest_id);
    }

    public function test_manage_bookings_still_lists_an_unassigned_booking(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $pooja = $this->activePooja();
        $devotee = \App\Models\Devotee::create(['user_id' => User::factory()->create()->id, 'verified' => 1]);

        Booking::create([
            'devotee_id' => $devotee->devotee_id,
            'pooja_id' => $pooja->pooja_id,
            'priest_id' => null,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'amount' => 50,
            'total_amount' => 50,
            'payment_status' => 'Pending',
            'booking_status' => 'Pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.bookings.index'));

        $response->assertOk();
        $response->assertSee('Not yet assigned');
    }

    public function test_custom_pooja_overrides_the_catalog_name_and_fee(): void
    {
        $booking = (new BookingService())->create([
            'pooja_id' => (int) Setting::get('custom_pooja_id'),
            'custom_pooja_label' => 'Special Family Request Pooja',
            'custom_fee' => 77.50,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Custom Pooja Devotee',
            'devotee_mobile' => '0411000003',
            'devotee_email' => 'custom.pooja.devotee@example.com',
        ], 'Staff', null, false);

        $this->assertSame('Special Family Request Pooja', $booking->custom_pooja_label);
        $this->assertEquals(77.50, (float) $booking->amount);
        $this->assertEquals(77.50, (float) $booking->total_amount);
    }

    public function test_custom_pooja_skips_membership_discount(): void
    {
        $membership = \DB::table('memberships')->insertGetId([
            'membership_name' => 'Gold', 'discount_percentage' => 20, 'status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $devoteeUser = User::factory()->create();
        $devotee = \App\Models\Devotee::create(['user_id' => $devoteeUser->id, 'verified' => 1, 'membership_id' => $membership]);

        $booking = (new BookingService())->create([
            'pooja_id' => (int) Setting::get('custom_pooja_id'),
            'custom_pooja_label' => 'Discount Test Custom Pooja',
            'custom_fee' => 100,
            'devotee_id' => $devotee->devotee_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
        ], 'Staff', null, true);

        $this->assertEquals(0, (float) $booking->discount_amount);
        $this->assertEquals(100, (float) $booking->total_amount);
    }

    public function test_pooja_portal_custom_pooja_booking_via_http(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'custom_pooja' => '1',
            'custom_pooja_label' => 'Portal Custom Pooja',
            'custom_fee' => 123.45,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Portal Custom Devotee',
            'devotee_mobile' => '0411000004',
            'devotee_email' => 'portal.custom.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $booking = Booking::first();
        $this->assertSame('Portal Custom Pooja', $booking->custom_pooja_label);
        $this->assertEquals(123.45, (float) $booking->amount);
        $this->assertSame('Paid', $booking->payment_status);
    }

    public function test_cash_payment_at_the_pooja_portal_marks_the_booking_paid_immediately(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $pooja = $this->activePooja();

        $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Cash Devotee',
            'devotee_mobile' => '0411000005',
            'devotee_email' => 'cash.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $this->assertSame('Paid', Booking::first()->payment_status);
    }

    public function test_bank_transfer_at_the_pooja_portal_stays_pending(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $pooja = $this->activePooja();

        $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Bank Devotee',
            'devotee_mobile' => '0411000006',
            'devotee_email' => 'bank.devotee@example.com',
            'payment_method' => 'Bank',
        ]);

        $this->assertSame('Pending', Booking::first()->payment_status);
    }

    public function test_ticket_pos_custom_pooja_booking_via_ajax_is_marked_paid_for_cash(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->postJson(route('admin.bookings.store'), [
            'custom_pooja' => '1',
            'custom_pooja_label' => 'POS Custom Pooja',
            'custom_fee' => 60,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'POS Custom Devotee',
            'devotee_mobile' => '0411000007',
            'devotee_email' => 'pos.custom.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $booking = Booking::first();
        $this->assertSame('POS Custom Pooja', $booking->custom_pooja_label);
        $this->assertSame('Paid', $booking->payment_status);
    }

    public function test_staff_counter_custom_pooja_is_paid_immediately(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($staff)->postJson(route('staff.counter.book-pooja'), [
            'custom_pooja' => '1',
            'custom_pooja_label' => 'Counter Custom Pooja',
            'custom_fee' => 33,
            'devotee_name' => 'Counter Custom Devotee',
            'mobile' => '0411000008',
            'email' => 'counter.custom.devotee@example.com',
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $booking = Booking::first();
        $this->assertSame('Counter Custom Pooja', $booking->custom_pooja_label);
        $this->assertEquals(33, (float) $booking->amount);
        $this->assertSame('Paid', $booking->payment_status);
    }

    public function test_priest_portal_cash_booking_is_paid_immediately(): void
    {
        $priestUser = User::factory()->create(['role' => 'Priest']);
        Priest::create(['priest_id' => 'PRIESTX2', 'user_id' => $priestUser->id, 'employment_status' => 'Active']);
        $pooja = $this->activePooja();

        $this->actingAs($priestUser)->post(route('priest.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Priest Portal Cash Devotee',
            'devotee_mobile' => '0411000009',
            'devotee_email' => 'priest.cash.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $this->assertSame('Paid', Booking::first()->payment_status);
    }
}
