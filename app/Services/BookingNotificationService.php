<?php

namespace App\Services;

use App\Mail\BookingConfirmationMail;
use App\Mail\BookingCreatedMail;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Email counterpart to the in-app NotificationService calls BookingService already makes
 * — see the Pooja Bookings plan's Phase B. Modeled on DonationReceiptService's dispatch
 * pattern: respects the testing-mode email gate, sends after the response so a clerk/
 * devotee is never kept waiting on an SMTP round-trip, and swallows mail failures (a
 * booking must never fail because an email didn't send).
 */
class BookingNotificationService
{
    /**
     * Emails every Admin plus the assigned priest (if any) that a new booking needs
     * attention — the email twin of NotificationService::notifyAdmin() already called by
     * BookingService::create().
     */
    public static function notifyCreated(Booking $booking, string $createdByRole): void
    {
        self::dispatch(function () use ($booking, $createdByRole) {
            $booking->loadMissing(['devotee.user', 'pooja', 'priest.user']);

            $recipients = User::where('role', 'Admin')->pluck('email')->filter()->values()->all();
            if ($booking->priest && $booking->priest->user && $booking->priest->user->email) {
                $recipients[] = $booking->priest->user->email;
            }
            $recipients = array_values(array_unique(array_filter($recipients)));

            if (empty($recipients)) {
                return;
            }

            $mailable = new BookingCreatedMail(
                $booking->booking_id,
                $booking->custom_pooja_label ?: ($booking->pooja->pooja_name ?? 'Pooja'),
                $booking->devotee->user->name ?? 'Devotee',
                $booking->devotee->user->mobile ?? '',
                $booking->booking_date,
                $booking->booking_time,
                $booking->booking_status,
                $createdByRole
            );

            Mail::to($recipients)->send($mailable);
        });
    }

    /**
     * The devotee-facing confirmation email — pooja/date/time/priest/cost and both payment
     * options together. Sent once a booking is (or becomes) Confirmed; see
     * BookingController's "Confirm & Send Email" admin action and BookingService::create()
     * for the auto-Confirmed-on-creation case.
     */
    public static function sendConfirmation(Booking $booking): void
    {
        self::dispatch(function () use ($booking) {
            $booking->loadMissing(['devotee.user', 'pooja', 'priest.user']);

            $devoteeEmail = $booking->devotee->user->email ?? null;
            if (!$devoteeEmail || !filter_var($devoteeEmail, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $mailable = new BookingConfirmationMail(
                $booking->booking_id,
                $booking->devotee->user->name ?? 'Devotee',
                $booking->custom_pooja_label ?: ($booking->pooja->pooja_name ?? 'Pooja'),
                // No catalog description for a custom pooja — its own label already says
                // what it is, there's nothing further on file to show.
                $booking->custom_pooja_label ? null : ($booking->pooja->description ?? null),
                $booking->booking_date,
                $booking->booking_time,
                $booking->priest->user->name ?? null,
                $booking->participant_details,
                $booking->payment_method,
                $booking->total_amount,
                Setting::get('currency_code', 'AUD'),
                $booking->payment_status
            );

            Mail::to($devoteeEmail)->send($mailable);
        });
    }

    /**
     * Shared gate + deferred-send wrapper — same testing-mode check and afterResponse()
     * deferral as DonationReceiptService::dispatch(), just without its donor/coordinator
     * address resolution (callers above already resolve their own recipients).
     */
    private static function dispatch(\Closure $send): void
    {
        $systemMode = Setting::get('system_mode', 'Testing Mode');
        $emailHandling = Setting::get('testing_email_handling', 'Do Not Send Emails');

        if ($systemMode === 'Testing Mode' && $emailHandling !== 'Send Emails') {
            return;
        }

        dispatch(function () use ($send) {
            try {
                $send();
            } catch (\Exception $e) {
                // A failed booking email must never block or roll back the booking itself.
            }
        })->afterResponse();
    }
}
