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

/**
 * The devotee-facing confirmation email once a booking is Confirmed. When the booking is
 * already Paid (the common case — Cash/Card/Counter at creation), this doubles as the
 * paid invoice: pooja (with its own catalog description), date/time, assigned priest,
 * participant details/special requirements, payment method, and a Paid/Due breakdown.
 * When still Pending (Bank Transfer only), it instead shows both ways to actually pay —
 * Bank Transfer details and pay-at-temple cash/card instructions — so the devotee can
 * pick either, unlike DonationPendingMail which only ever shows the one method already
 * chosen. See the Pooja Bookings plan's Phase B.
 */
class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $bookingId;
    public $devoteeName;
    public $poojaName;
    public $poojaDescription;
    public $bookingDate;
    public $bookingTime;
    public $priestName;
    public $participantDetails;
    public $paymentMethod;
    public $totalAmount;
    public $paidAmount;
    public $dueAmount;
    public $currency;
    public $paymentStatus;

    public function __construct($bookingId, $devoteeName, $poojaName, $poojaDescription, $bookingDate, $bookingTime, $priestName, $participantDetails, $paymentMethod, $totalAmount, $currency, $paymentStatus)
    {
        $this->bookingId = $bookingId;
        $this->devoteeName = $devoteeName;
        $this->poojaName = $poojaName;
        $this->poojaDescription = $poojaDescription;
        $this->bookingDate = $bookingDate;
        $this->bookingTime = $bookingTime;
        $this->priestName = $priestName;
        $this->participantDetails = $participantDetails;
        $this->paymentMethod = $paymentMethod;
        $this->totalAmount = $totalAmount;
        $this->paidAmount = $paymentStatus === 'Paid' ? $totalAmount : 0;
        $this->dueAmount = $paymentStatus === 'Paid' ? 0 : $totalAmount;
        $this->currency = $currency;
        $this->paymentStatus = $paymentStatus;
    }

    public function envelope(): Envelope
    {
        $templeName = Setting::get('temple_name', 'SRI SELVA VINAYAKAR KOYIL (GANESHA TEMPLE)');

        return new Envelope(subject: "Your Pooja Booking #{$this->bookingId} is Confirmed - {$templeName}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking_confirmation',
            with: [
                'temple' => Setting::templeBranding(),
                'bankAccount' => Setting::effectivePoojaBookingBankAccount(),
            ],
        );
    }

    /**
     * A PDF receipt/confirmation, same pattern as DonationReceiptMail's own attachment —
     * same letterhead background image and layout language, generated fresh from this
     * mailable's own data rather than stored anywhere on disk.
     */
    public function attachments(): array
    {
        $temple = Setting::templeBranding();
        $isPaid = $this->paymentStatus === 'Paid';

        $pdfData = [
            'templeLegalName' => $temple['legal_name'] ?: $temple['name'],
            'bgImagePath' => public_path('images/donation_receipt_bg.png'),
            'receiptNumber' => DonationReceiptService::receiptNumber('B', $this->bookingId),
            'bookingId' => $this->bookingId,
            'isPaid' => $isPaid,
            'devoteeName' => $this->devoteeName,
            'poojaName' => $this->poojaName,
            'poojaDescription' => $this->poojaDescription,
            'bookingDate' => $this->bookingDate,
            'bookingTime' => $this->bookingTime,
            'priestName' => $this->priestName,
            'participantDetails' => $this->participantDetails,
            'paymentMethod' => $this->paymentMethod,
            'totalAmount' => $this->totalAmount,
            'paidAmount' => $this->paidAmount,
            'dueAmount' => $this->dueAmount,
            'currency' => $this->currency,
        ];

        $pdf = Pdf::loadView('emails.booking_receipt_pdf', $pdfData)
            ->setPaper([0, 0, 1102, 1427], 'portrait');
        $filename = ($isPaid ? 'Pooja-Booking-Receipt-' : 'Pooja-Booking-Confirmation-') . date('Ymd', strtotime($this->bookingDate)) . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
