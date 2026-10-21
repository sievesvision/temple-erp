<?php

namespace Tests\Feature;

use App\Mail\DonationPaymentReceiptMail;
use App\Mail\DonationReceiptMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\DonationPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Partial Pledge Payments, Phase F — DonationReceiptService::sendForInstallment() picks
 * the right email for a just-confirmed payment: the new running-total "payment receipt"
 * for one that doesn't finish the pledge, or today's existing full "fully paid" receipt
 * for the one that does — never both, never neither for a Completed payment.
 *
 * Every send goes through dispatch()'s own ->afterResponse() deferral (see
 * DonationReceiptService::dispatch()), which a plain feature test's request/response
 * cycle never actually runs to completion — $this->app->terminate() fires those
 * terminating callbacks explicitly so Mail::fake() has something to see, same as a real
 * request's own kernel termination would.
 */
class DonationPaymentReceiptTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Payment Receipt Test Event', 'slug' => 'payment-receipt-test-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makePledge(int $eventId, float $amount): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => 'Pledging Donor', 'email' => 'pledging.donor@example.com',
            'amount' => $amount, 'purpose' => 'General', 'payment_method' => null, 'payment_status' => 'Pledge',
            'donation_status' => 'Pending', 'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_partial_payment_sends_the_payment_receipt_not_the_full_one(): void
    {
        Mail::fake();
        Setting::set('system_mode', 'Live Mode');
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);

        $payment = DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        \App\Services\DonationReceiptService::sendForInstallment('guest', $payment);
        $this->app->terminate();

        Mail::assertSent(DonationPaymentReceiptMail::class, function ($mail) {
            return $mail->amount === 200.0 && $mail->totalPaid === 200.0 && $mail->outstandingBalance === 300.0;
        });
        Mail::assertNotSent(DonationReceiptMail::class);
    }

    public function test_the_completing_payment_sends_the_full_receipt_not_the_partial_one(): void
    {
        Mail::fake();
        Setting::set('system_mode', 'Live Mode');
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        $final = DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 300, 'payment_method' => 'Bank', 'payment_status' => 'Completed']);
        \App\Services\DonationReceiptService::sendForInstallment('guest', $final);
        $this->app->terminate();

        Mail::assertSent(DonationReceiptMail::class, function ($mail) {
            return (float) $mail->amount === 500.0;
        });
        Mail::assertNotSent(DonationPaymentReceiptMail::class);
    }

    public function test_a_pending_payment_sends_no_receipt_at_all(): void
    {
        Mail::fake();
        Setting::set('system_mode', 'Live Mode');
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);

        $payment = DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Bank', 'payment_status' => 'Pending']);
        \App\Services\DonationReceiptService::sendForInstallment('guest', $payment);
        $this->app->terminate();

        Mail::assertNothingSent();
    }

    public function test_adding_a_payment_via_the_admin_portal_sends_the_payment_receipt(): void
    {
        Mail::fake();
        Setting::set('system_mode', 'Live Mode');
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);

        $this->actingAs($admin)->post(route('admin.donations.addPaymentGuest', $donationId), [
            'amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);
        $this->app->terminate();

        Mail::assertSent(DonationPaymentReceiptMail::class);
    }

    public function test_confirming_a_pending_payment_sends_the_payment_receipt(): void
    {
        Mail::fake();
        Setting::set('system_mode', 'Live Mode');
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        $payment = DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Bank', 'payment_status' => 'Pending']);

        $this->actingAs($admin)->post(route('admin.donations.confirmPayment', $payment->id));
        $this->app->terminate();

        Mail::assertSent(DonationPaymentReceiptMail::class);
    }

    public function test_a_devotee_partial_payment_sends_the_payment_receipt(): void
    {
        Mail::fake();
        Setting::set('system_mode', 'Live Mode');
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $devoteeUser = User::factory()->create(['role' => 'Devotee', 'email' => 'devotee.pledge@example.com', 'mobile' => fake()->unique()->numerify('04########')]);
        $devotee = \App\Models\Devotee::create(['user_id' => $devoteeUser->id, 'verified' => 1]);
        $donationId = DB::table('donations')->insertGetId([
            'devotee_id' => $devotee->devotee_id, 'event_id' => $eventId, 'amount' => 400,
            'payment_method' => null, 'payment_status' => 'Pledge', 'donation_status' => 'Pending',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.donations.addPaymentDevotee', $donationId), [
            'amount' => 150, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);
        $this->app->terminate();

        Mail::assertSent(DonationPaymentReceiptMail::class, function ($mail) {
            return $mail->amount === 150.0 && $mail->outstandingBalance === 250.0;
        });
    }
}
