<?php

namespace App\Services;

use App\Mail\DonationPendingMail;
use App\Mail\DonationReceiptMail;
use App\Models\Event;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;

class DonationReceiptService
{
    /**
     * Send the official donation receipt/notification email — used once a donation is
     * confirmed 'Paid' (immediately for Stripe, or once an admin approves a Bank/Cash
     * donation). See sendPendingNotice() for the earlier, unconfirmed-donation email.
     */
    public static function send(array $donation): void
    {
        self::dispatch($donation, function (bool $isDonorCopy, ?string $eventName) use ($donation) {
            return new DonationReceiptMail(
                donorName: $donation['donor_name'] ?? 'Devotee',
                amount: (float) ($donation['amount'] ?? 0),
                currency: $donation['currency'] ?? Setting::get('currency_code', 'AUD'),
                paymentMethod: $donation['payment_method'] ?? 'N/A',
                purpose: $donation['purpose'] ?? null,
                eventName: $eventName,
                donationDate: $donation['donation_date'] ?? now()->toDateString(),
                transactionId: $donation['transaction_id'] ?? null,
                isDonorCopy: $isDonorCopy,
            );
        });
    }

    /**
     * Sent immediately when a Bank Transfer or Cash at Temple donation is first submitted,
     * while it still sits 'Pending' — reminds the donor what to do next (bank details, or
     * visit the temple counter). The official receipt still only goes out via send() once
     * an admin confirms the money was actually received; this is a separate, earlier email.
     */
    public static function sendPendingNotice(array $donation): void
    {
        self::dispatch($donation, function (bool $isDonorCopy, ?string $eventName) use ($donation) {
            return new DonationPendingMail(
                donorName: $donation['donor_name'] ?? 'Devotee',
                amount: (float) ($donation['amount'] ?? 0),
                currency: $donation['currency'] ?? Setting::get('currency_code', 'AUD'),
                paymentMethod: $donation['payment_method'] ?? 'N/A',
                purpose: $donation['purpose'] ?? null,
                eventName: $eventName,
                donationDate: $donation['donation_date'] ?? now()->toDateString(),
                transactionId: $donation['transaction_id'] ?? null,
                isDonorCopy: $isDonorCopy,
            );
        });
    }

    /**
     * Shared pipeline for both donation emails: resolves the donor address and the
     * event/coordinator CC list, respects the testing-mode email gate (so donations made
     * while testing don't spam real donors/coordinators), then builds and sends whichever
     * mailable the caller's factory returns. If there's no donor email at all, the
     * coordinator list (if any) becomes the primary recipient instead, so a donation
     * without a donor email still generates a notification.
     */
    private static function dispatch(array $donation, \Closure $mailableFactory): void
    {
        $systemMode = Setting::get('system_mode', 'Testing Mode');
        $emailHandling = Setting::get('testing_email_handling', 'Do Not Send Emails');

        if ($systemMode === 'Testing Mode' && $emailHandling !== 'Send Emails') {
            return;
        }

        $donorEmail = trim((string) ($donation['donor_email'] ?? ''));
        $donorEmail = filter_var($donorEmail, FILTER_VALIDATE_EMAIL) ? $donorEmail : null;

        $eventName = null;
        $coordinatorEmails = [];

        if (!empty($donation['event_id'])) {
            $event = Event::find($donation['event_id']);
            if ($event) {
                $eventName = $event->event_name;
                $coordinatorEmails = $event->coordinatorEmailList();
            }
        }

        if (empty($coordinatorEmails)) {
            $coordinatorEmails = self::parseEmailList(Setting::get('donation_coordinator_emails', ''));
        }

        if (!$donorEmail && empty($coordinatorEmails)) {
            return;
        }

        $mailable = $mailableFactory((bool) $donorEmail, $eventName);

        try {
            if ($donorEmail) {
                Mail::to($donorEmail)->cc($coordinatorEmails)->send($mailable);
            } else {
                Mail::to($coordinatorEmails)->send($mailable);
            }
        } catch (\Exception $e) {
            // Ignore mail errors — a failed donation email must never block a donation.
        }
    }

    private static function parseEmailList(?string $value): array
    {
        if (!$value) {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }
}
