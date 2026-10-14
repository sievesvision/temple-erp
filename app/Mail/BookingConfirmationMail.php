<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
}
