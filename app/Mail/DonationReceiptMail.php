<?php

namespace App\Mail;

use App\Models\Setting;
use App\Services\DonationReceiptService;
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
    public $donorMobile;
    public $amount;
    public $currency;
    public $paymentMethod;
    public $purpose;
    public $eventName;
    public $donationDate;
    public $transactionId;
    public $receiptNumber;
    public $isDonorCopy;

    /**
     * Create a new message instance.
     */
    public function __construct($donorName, $amount, $currency, $paymentMethod, $purpose, $eventName, $donationDate, $transactionId, $isDonorCopy = true, $donorMobile = null, $receiptNumber = null)
    {
        $this->donorName = $donorName;
        $this->donorMobile = $donorMobile;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->paymentMethod = $paymentMethod;
        $this->purpose = $purpose;
        $this->eventName = $eventName;
        $this->donationDate = $donationDate;
        $this->transactionId = $transactionId;
        $this->receiptNumber = $receiptNumber ?? DonationReceiptService::receiptNumber('R', now()->timestamp);
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
            'bgImagePath' => public_path('images/donation_receipt_bg.png'),
            'donorName' => $this->donorName,
            'donorMobile' => $this->donorMobile,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'paymentMethod' => $this->paymentMethod,
            'purpose' => $this->purpose,
            'eventName' => $this->eventName,
            'donationDate' => $this->donationDate,
            'transactionId' => $this->transactionId,
            'receiptNumber' => $this->receiptNumber,
        ];

        // Matches the background letterhead image's own pixel dimensions exactly (points,
        // not the usual A4/Letter preset) so the overlaid text lines up with it precisely.
        $pdf = Pdf::loadView('emails.donation_receipt_pdf', $pdfData)
            ->setPaper([0, 0, 1102, 1427], 'portrait');
        $filename = 'Donation-Receipt-' . date('Ymd', strtotime($this->donationDate)) . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
