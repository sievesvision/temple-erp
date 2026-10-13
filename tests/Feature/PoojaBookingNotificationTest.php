<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmationMail;
use App\Mail\BookingCreatedMail;
use App\Models\Booking;
use App\Models\Devotee;
use App\Models\Pooja;
use App\Models\Priest;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase B of the Pooja Bookings plan: email notifications on top of the existing in-app
 * ones. See the plan's own Tests section — email dispatch respects the testing-mode
 * gate, and the confirmation email shows both payment options.
 */
class PoojaBookingNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

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

    private function activePriest(): Priest
    {
        $user = User::factory()->create(['role' => 'Priest']);

        return Priest::create([
            'priest_id' => 'PRIEST' . $user->id,
            'user_id' => $user->id,
            'employment_status' => 'Active',
        ]);
    }

    public function test_no_emails_are_sent_in_testing_mode_by_default(): void
    {
        Setting::set('system_mode', 'Testing Mode');
        Setting::set('testing_email_handling', 'Do Not Send Emails');
        $this->activePriest();
        $pooja = $this->activePooja();

        (new BookingService())->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Gated Devotee',
            'devotee_mobile' => '0466666666',
            'devotee_email' => 'gated.devotee@example.com',
        ], 'Devotee', null);

        Mail::assertNothingSent();
    }

    /**
     * BookingNotificationService defers its sends via dispatch(...)->afterResponse(),
     * which only actually fires once the container's terminating() callbacks run — that
     * happens automatically for a real HTTP request/response cycle (see
     * test_admin_confirm_and_notify_* below, driven through actingAs()->post()), but a
     * direct service call in a test has no such cycle, so these tests trigger it
     * explicitly, the same way the real Kernel::terminate() would after sending a response.
     */
    private function flushAfterResponseJobs(): void
    {
        $this->app->terminate();
    }

    public function test_live_mode_sends_the_created_email_but_not_confirmation_for_a_pending_booking(): void
    {
        Setting::set('system_mode', 'Live Mode');
        $this->activePriest();
        $pooja = $this->activePooja();

        (new BookingService())->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Pending Devotee',
            'devotee_mobile' => '0477777777',
            'devotee_email' => 'pending.devotee@example.com',
        ], 'Devotee', null);
        $this->flushAfterResponseJobs();

        Mail::assertSent(BookingCreatedMail::class, 1);
        Mail::assertNotSent(BookingConfirmationMail::class);
    }

    public function test_auto_confirmed_booking_sends_both_the_created_and_confirmation_emails(): void
    {
        Setting::set('system_mode', 'Live Mode');
        $this->activePriest();
        $pooja = $this->activePooja();

        (new BookingService())->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Confirmed Devotee',
            'devotee_mobile' => '0488888888',
            'devotee_email' => 'confirmed.devotee@example.com',
        ], 'Staff', null);
        $this->flushAfterResponseJobs();

        Mail::assertSent(BookingCreatedMail::class, 1);
        Mail::assertSent(BookingConfirmationMail::class, 1);
    }

    public function test_confirmation_email_shows_both_payment_options_when_unpaid(): void
    {
        Setting::set('system_mode', 'Live Mode');
        $this->activePriest();
        $pooja = $this->activePooja();

        (new BookingService())->create([
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Dual Payment Devotee',
            'devotee_mobile' => '0499999999',
            'devotee_email' => 'dual.payment.devotee@example.com',
        ], 'Admin', null);
        $this->flushAfterResponseJobs();

        Mail::assertSent(BookingConfirmationMail::class, function (BookingConfirmationMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'Bank Transfer') && str_contains($html, 'Pay at the Temple');
        });
    }

    public function test_admin_confirm_and_notify_moves_a_pending_booking_to_confirmed_and_sends_the_email(): void
    {
        Setting::set('system_mode', 'Live Mode');
        $priest = $this->activePriest();
        $pooja = $this->activePooja();
        $admin = User::factory()->create(['role' => 'Admin']);
        $devotee = Devotee::create(['user_id' => User::factory()->create()->id, 'verified' => 1]);

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

        $response = $this->actingAs($admin)->post(route('admin.bookings.confirm-notify', $booking->booking_id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame('Confirmed', $booking->fresh()->booking_status);
        Mail::assertSent(BookingConfirmationMail::class, 1);
    }

    public function test_admin_confirm_and_notify_rejects_a_booking_that_is_not_pending(): void
    {
        Setting::set('system_mode', 'Live Mode');
        $priest = $this->activePriest();
        $pooja = $this->activePooja();
        $admin = User::factory()->create(['role' => 'Admin']);
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
            'booking_status' => 'Confirmed',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.bookings.confirm-notify', $booking->booking_id));

        $response->assertSessionHas('error');
        $this->assertSame('Confirmed', $booking->fresh()->booking_status);
        Mail::assertNotSent(BookingConfirmationMail::class);
    }
}
