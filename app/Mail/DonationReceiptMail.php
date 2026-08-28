<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DonationReceiptMail extends Mailable
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

    /**
     * Create a new message instance.
     */
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

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Donation Receipt - ' . Setting::get('temple_name', 'SRI SELVA VINAYAKAR KOYIL (GANESHA TEMPLE)'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.donation_receipt',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
