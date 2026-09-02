<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent immediately when a Bank Transfer or Cash at Temple donation is first submitted,
 * while it still sits unconfirmed — a reminder of what to do next (transfer to the bank
 * details, or bring the offering to the temple counter). This is distinct from
 * DonationReceiptMail, the official receipt sent only once an admin confirms the money
 * was actually received.
 */
class DonationPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public $donorName;
    public $amount;
    public $currency;
    public $paymentMethod;
    public $purpose;
    public $eventName;
    public $donationDate;
    public $transactionId;
    public $isDonorCopy;

    public function __construct($donorName, $amount, $currency, $paymentMethod, $purpose, $eventName, $donationDate, $transactionId, $isDonorCopy = true)
    {
        $this->donorName = $donorName;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->paymentMethod = $paymentMethod;
        $this->purpose = $purpose;
        $this->eventName = $eventName;
        $this->donationDate = $donationDate;
        $this->transactionId = $transactionId;
        $this->isDonorCopy = $isDonorCopy;
    }

    public function envelope(): Envelope
    {
        $templeName = Setting::get('temple_name', 'SRI SELVA VINAYAKAR KOYIL (GANESHA TEMPLE)');
        $subject = $this->paymentMethod === 'Bank'
            ? 'Complete Your Bank Transfer - ' . $templeName
            : 'Donation Pledge Received - ' . $templeName;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.donation_pending',
            with: [
                'temple' => Setting::templeBranding(),
            ],
        );
    }
}
