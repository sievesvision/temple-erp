<?php

namespace App\Services;

use App\Mail\DonationReceiptMail;
use App\Models\Event;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;

class DonationReceiptService
{
    /**
     * Send a donation receipt/notification email.
     *
     * The donor (if an email address is known) is the primary recipient; coordinators
     * are CC'd alongside them. If the donation is tied to an event that has its own
     * coordinator_emails configured, those are used; otherwise the general
     * donation_coordinator_emails setting is used. If there's no donor email at all,
     * the coordinator list (if any) becomes the primary recipient instead, so a
     * donation without a donor email still generates a notification.
     *
     * Respects the same system_mode/testing_email_handling gate used elsewhere in the
     * app, so donations made while testing don't spam real donors/coordinators.
     */
    public static function send(array $donation): void
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

        $mailable = new DonationReceiptMail(
            donorName: $donation['donor_name'] ?? 'Devotee',
            amount: (float) ($donation['amount'] ?? 0),
            currency: $donation['currency'] ?? Setting::get('currency_code', 'AUD'),
            paymentMethod: $donation['payment_method'] ?? 'N/A',
            purpose: $donation['purpose'] ?? null,
            eventName: $eventName,
            donationDate: $donation['donation_date'] ?? now()->toDateString(),
            transactionId: $donation['transaction_id'] ?? null,
            isDonorCopy: (bool) $donorEmail,
        );

        try {
            if ($donorEmail) {
                Mail::to($donorEmail)->cc($coordinatorEmails)->send($mailable);
            } else {
                Mail::to($coordinatorEmails)->send($mailable);
            }
        } catch (\Exception $e) {
            // Ignore mail errors — a failed receipt email must never block a donation.
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
