<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Partial Pledge Payments: sent for one installment that does NOT, by itself, finish
 * paying off a pledge — distinct from DonationReceiptMail (the "fully paid" receipt, sent
 * instead once an installment brings the outstanding balance to zero — see
 * DonationReceiptService::sendForInstallment()). No PDF attachment, same reasoning as
 * DonationPendingMail: a PDF receipt is for a completed donation's own records, not an
 * interim acknowledgement of one installment toward a pledge that's still open.
 */
class DonationPaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public $donorName;
    public $amount;
    public $currency;
    public $paymentMethod;
    public $purpose;
    public $eventName;
    public $paymentDate;
    public $transactionId;
    public $pledgedAmount;
    public $totalPaid;
    public $outstandingBalance;
    public $receiptNumber;
    public $isDonorCopy;

    public function __construct($donorName, $amount, $currency, $paymentMethod, $purpose, $eventName, $paymentDate, $transactionId, $pledgedAmount, $totalPaid, $outstandingBalance, $isDonorCopy = true, $receiptNumber = null)
    {
        $this->donorName = $donorName;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->paymentMethod = $paymentMethod;
        $this->purpose = $purpose;
        $this->eventName = $eventName;
        $this->paymentDate = $paymentDate;
        $this->transactionId = $transactionId;
        $this->pledgedAmount = $pledgedAmount;
        $this->totalPaid = $totalPaid;
        $this->outstandingBalance = $outstandingBalance;
        $this->receiptNumber = $receiptNumber;
        $this->isDonorCopy = $isDonorCopy;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Received Toward Your Pledge - ' . Setting::get('temple_name', 'SRI SELVA VINAYAKAR KOYIL (GANESHA TEMPLE)'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.donation_payment_receipt',
            with: [
                'temple' => Setting::templeBranding(),
            ],
        );
    }
}
