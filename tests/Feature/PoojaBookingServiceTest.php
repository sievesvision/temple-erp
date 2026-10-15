<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Devotee;
use App\Models\Pooja;
use App\Models\Priest;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase A of the Pooja Bookings plan: the shared BookingService::create()/cancel() path
 * every entry point (devotee wizard, public, Pooja Portal, Ticket POS, Priest Portal)
 * now goes through, plus the devotee/priest self-service cancel endpoints. See the plan's
 * own Tests section.
 */
class PoojaBookingServiceTest extends TestCase
{
    private function activePooja(array $overrides = []): Pooja
    {
        return Pooja::create(array_merge([
            'pooja_name' => 'Test Archana',
            'pooja_fee' => 100,
            'duration' => '30',
            'status' => 'Active',
            'online_allowed' => false,
        ], $overrides));
    }

    private function activePriest(array $overrides = []): Priest
    {
        $user = User::factory()->create(array_merge(['role' => 'Priest'], $overrides['user'] ?? []));
        unset($overrides['user']);

        return Priest::create(array_merge([
            'priest_id' => 'PRIEST' . $user->id,
            'user_id' => $user->id,
            'employment_status' => 'Active',
        ], $overrides));
    }

    public function test_create_matches_an_existing_account_by_email_instead_of_duplicating(): void
    {
        $this->activePriest();
        $pooja = $this->activePooja();
        $svc = new BookingService();

        $first = $svc->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Repeat Devotee',
            'devotee_mobile' => '0411111111',
            'devotee_email' => 'repeat.devotee@example.com',
        ], 'Public', null);

        $second = $svc->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'booking_time' => '11:00:00',
            'devotee_name' => 'Repeat Devotee',
            'devotee_mobile' => '0411111111',
            'devotee_email' => 'repeat.devotee@example.com',
        ], 'Public', null);

        $this->assertSame($first->devotee_id, $second->devotee_id);
        $this->assertSame(1, User::where('email', 'repeat.devotee@example.com')->count());
    }

    public function test_create_matches_an_existing_account_by_mobile_even_with_a_different_email(): void
    {
        $this->activePriest();
        $pooja = $this->activePooja();
        $existingUser = User::factory()->create(['mobile' => '0422222222', 'role' => 'Devotee']);
        Devotee::create(['user_id' => $existingUser->id, 'verified' => 1]);

        $booking = (new BookingService())->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Different Name',
            'devotee_mobile' => '0422222222',
            'devotee_email' => 'brand.new.email@example.com',
        ], 'Public', null);

        $this->assertSame(1, User::where('mobile', '0422222222')->count());
        $this->assertSame($existingUser->id, Devotee::find($booking->devotee_id)->user_id);
    }

    public function test_devotee_and_public_created_bookings_start_pending(): void
    {
        $this->activePriest();
        $pooja = $this->activePooja();
        $svc = new BookingService();

        $devoteeBooking = $svc->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Devotee Booker',
            'devotee_mobile' => '0433333333',
            'devotee_email' => 'devotee.booker@example.com',
        ], 'Devotee', null);

        $this->assertSame('Pending', $devoteeBooking->booking_status);
    }

    public function test_staff_priest_and_admin_created_bookings_are_auto_confirmed(): void
    {
        foreach (['Staff', 'Priest', 'Admin', 'Committee', 'Ticket Controller'] as $i => $role) {
            $this->activePriest();
            $pooja = $this->activePooja();

            $booking = (new BookingService())->create([
                'pooja_id' => $pooja->pooja_id,
                'booking_date' => now()->addDay()->toDateString(),
                'booking_time' => '10:00:00',
                'devotee_name' => "Role Booker {$i}",
                'devotee_mobile' => '04' . (44444400 + $i),
                'devotee_email' => "role.booker.{$i}@example.com",
            ], $role, null);

            $this->assertSame('Confirmed', $booking->booking_status, "Role {$role} should auto-confirm.");
        }
    }

    public function test_create_auto_assigns_the_least_busy_available_priest(): void
    {
        $busyPriest = $this->activePriest();
        $freePriest = $this->activePriest();
        $pooja = $this->activePooja();
        $date = now()->addDay()->toDateString();

        // Give $busyPriest an existing booking on that date so the workload-balancing
        // picks the other, idle priest for the new one.
        Booking::create([
            'devotee_id' => Devotee::create(['user_id' => User::factory()->create()->id, 'verified' => 1])->devotee_id,
            'pooja_id' => $pooja->pooja_id,
            'priest_id' => $busyPriest->priest_id,
            'booking_date' => $date,
            'booking_time' => '09:00:00',
            'amount' => 100,
            'total_amount' => 100,
            'payment_status' => 'Paid',
            'booking_status' => 'Confirmed',
        ]);

        $booking = (new BookingService())->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => $date,
            'booking_time' => '15:00:00',
            'devotee_name' => 'Workload Devotee',
            'devotee_mobile' => '0455555555',
            'devotee_email' => 'workload.devotee@example.com',
        ], 'Public', null);

        $this->assertSame($freePriest->priest_id, $booking->priest_id);
    }

    public function test_cancel_rejects_a_booking_already_completed_or_cancelled(): void
    {
        $priest = $this->activePriest();
        $pooja = $this->activePooja();
        $devotee = Devotee::create(['user_id' => User::factory()->create()->id, 'verified' => 1]);

        $booking = Booking::create([
            'devotee_id' => $devotee->devotee_id,
            'pooja_id' => $pooja->pooja_id,
            'priest_id' => $priest->priest_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'amount' => 100,
            'total_amount' => 100,
            'payment_status' => 'Paid',
            'booking_status' => 'Completed',
        ]);

        $this->expectException(\RuntimeException::class);
        (new BookingService())->cancel($booking, 'Admin', null);
    }

    public function test_cancel_notifies_devotee_and_priest_and_marks_cancelled(): void
    {
        $priest = $this->activePriest();
        $pooja = $this->activePooja();
        $devoteeUser = User::factory()->create();
        $devotee = Devotee::create(['user_id' => $devoteeUser->id, 'verified' => 1]);

        $booking = Booking::create([
            'devotee_id' => $devotee->devotee_id,
            'pooja_id' => $pooja->pooja_id,
            'priest_id' => $priest->priest_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'amount' => 100,
            'total_amount' => 100,
            'payment_status' => 'Pending',
            'booking_status' => 'Pending',
        ]);

        $cancelled = (new BookingService())->cancel($booking, 'Devotee', $devoteeUser->id);

        $this->assertSame('Cancelled', $cancelled->booking_status);
        $this->assertSame(1, \DB::table('notifications')->where('user_id', $devoteeUser->id)->count());
    }

    public function test_devotee_self_service_cancel_rejects_a_booking_that_is_not_theirs(): void
    {
        $priest = $this->activePriest();
        $pooja = $this->activePooja();
        $owner = Devotee::create(['user_id' => User::factory()->create()->id, 'verified' => 1]);
        $otherUser = User::factory()->create(['role' => 'Devotee']);
        Devotee::create(['user_id' => $otherUser->id, 'verified' => 1]);

        $booking = Booking::create([
            'devotee_id' => $owner->devotee_id,
            'pooja_id' => $pooja->pooja_id,
            'priest_id' => $priest->priest_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'amount' => 100,
            'total_amount' => 100,
            'payment_status' => 'Pending',
            'booking_status' => 'Pending',
        ]);

        $response = $this->actingAs($otherUser)->post(route('devotee.bookings.cancel', $booking->booking_id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('Pending', $booking->fresh()->booking_status);
    }

    public function test_priest_self_service_cancel_rejects_a_booking_not_assigned_to_them(): void
    {
        $assignedPriest = $this->activePriest();
        $otherPriest = $this->activePriest();
        $pooja = $this->activePooja();
        $devotee = Devotee::create(['user_id' => User::factory()->create()->id, 'verified' => 1]);

        $booking = Booking::create([
            'devotee_id' => $devotee->devotee_id,
            'pooja_id' => $pooja->pooja_id,
            'priest_id' => $assignedPriest->priest_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'amount' => 100,
            'total_amount' => 100,
            'payment_status' => 'Pending',
            'booking_status' => 'Pending',
        ]);

        $otherPriestUser = User::find($otherPriest->user_id);
        $response = $this->actingAs($otherPriestUser)->post(route('priest.bookings.cancel', $booking->booking_id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('Pending', $booking->fresh()->booking_status);
    }
}
