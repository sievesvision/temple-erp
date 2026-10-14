<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Devotee;
use App\Models\Pooja;
use App\Models\RolePermission;
use App\Models\User;
use Tests\TestCase;

/**
 * The "Receipt" link on the Pooja Portal's Manage Bookings page points at
 * devotee.bookings.receipt — a route that used to sit behind role.devotee middleware, so
 * any staff member (Admin, Ticket Controller, Committee) clicking it got a 403 before the
 * request ever reached BookingController::downloadReceipt()'s own, already-correct
 * "Admin/authorised staff or the owning devotee" check. The route is now auth-only, and
 * the controller's own check uses the same 'bookings'->'view' permission Manage Bookings
 * itself is gated on, not a hardcoded 'Admin' role.
 */
class BookingReceiptAccessTest extends TestCase
{
    private function makeBooking(): Booking
    {
        $pooja = Pooja::create(['pooja_name' => 'Receipt Test Pooja', 'pooja_fee' => 40, 'status' => 'Active']);
        $devotee = Devotee::create(['user_id' => User::factory()->create()->id, 'verified' => 1]);

        return Booking::create([
            'devotee_id' => $devotee->devotee_id, 'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(), 'booking_time' => '09:00:00',
            'amount' => 40, 'total_amount' => 40, 'payment_status' => 'Paid', 'booking_status' => 'Confirmed',
        ]);
    }

    public function test_a_committee_member_with_bookings_view_permission_can_open_the_receipt(): void
    {
        $booking = $this->makeBooking();
        RolePermission::updateOrCreate(
            ['role' => 'Committee', 'resource' => 'bookings'],
            ['can_view' => true]
        );
        $committee = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($committee)->get(route('devotee.bookings.receipt', $booking->booking_id));

        $response->assertOk();
    }

    public function test_a_staff_member_without_bookings_view_permission_is_forbidden(): void
    {
        $booking = $this->makeBooking();
        RolePermission::updateOrCreate(
            ['role' => 'Committee', 'resource' => 'bookings'],
            ['can_view' => false]
        );
        $committee = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($committee)->get(route('devotee.bookings.receipt', $booking->booking_id));

        $response->assertForbidden();
    }

    public function test_the_owning_devotee_can_still_open_their_own_receipt(): void
    {
        $pooja = Pooja::create(['pooja_name' => 'Own Receipt Pooja', 'pooja_fee' => 25, 'status' => 'Active']);
        $devoteeUser = User::factory()->create(['role' => 'Devotee']);
        $devotee = Devotee::create(['user_id' => $devoteeUser->id, 'verified' => 1]);
        $booking = Booking::create([
            'devotee_id' => $devotee->devotee_id, 'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(), 'booking_time' => '09:00:00',
            'amount' => 25, 'total_amount' => 25, 'payment_status' => 'Paid', 'booking_status' => 'Confirmed',
        ]);

        $response = $this->actingAs($devoteeUser)->get(route('devotee.bookings.receipt', $booking->booking_id));

        $response->assertOk();
    }

    public function test_a_different_devotee_cannot_open_someone_elses_receipt(): void
    {
        $booking = $this->makeBooking();
        $otherDevoteeUser = User::factory()->create(['role' => 'Devotee']);
        Devotee::create(['user_id' => $otherDevoteeUser->id, 'verified' => 1]);

        $response = $this->actingAs($otherDevoteeUser)->get(route('devotee.bookings.receipt', $booking->booking_id));

        $response->assertForbidden();
    }
}
