<?php

namespace App\Mail;

use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
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
     * Get the attachments for the message — a PDF copy of the receipt, generated on
     * the fly from the same data as the email body (not stored anywhere on disk).
     */
    public function attachments(): array
    {
        $pdfData = [
            'temple' => Setting::templeBranding(),
            'donorName' => $this->donorName,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'paymentMethod' => $this->paymentMethod,
            'purpose' => $this->purpose,
            'eventName' => $this->eventName,
            'donationDate' => $this->donationDate,
            'transactionId' => $this->transactionId,
        ];

        $pdf = Pdf::loadView('emails.donation_receipt_pdf', $pdfData);
        $filename = 'Donation-Receipt-' . date('Ymd', strtotime($this->donationDate)) . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
