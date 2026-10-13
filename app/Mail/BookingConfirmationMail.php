<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The devotee-facing confirmation email once a booking is Confirmed — pooja, date/time,
 * assigned priest, total cost, and BOTH payment options together (Bank Transfer details
 * and pay-at-temple cash/card instructions) so the devotee can pick either, unlike
 * DonationPendingMail which only ever shows the one method already chosen. See the
 * Pooja Bookings plan's Phase B.
 */
class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $bookingId;
    public $devoteeName;
    public $poojaName;
    public $bookingDate;
    public $bookingTime;
    public $priestName;
    public $totalAmount;
    public $currency;
    public $paymentStatus;

    public function __construct($bookingId, $devoteeName, $poojaName, $bookingDate, $bookingTime, $priestName, $totalAmount, $currency, $paymentStatus)
    {
        $this->bookingId = $bookingId;
        $this->devoteeName = $devoteeName;
        $this->poojaName = $poojaName;
        $this->bookingDate = $bookingDate;
        $this->bookingTime = $bookingTime;
        $this->priestName = $priestName;
        $this->totalAmount = $totalAmount;
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
