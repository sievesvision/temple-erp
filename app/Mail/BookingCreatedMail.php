<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the admin list and the assigned priest (if any) when a new pooja booking is
 * created — the email counterpart to the existing in-app NotificationService::notify()/
 * notifyAdmin() calls BookingService::create() already makes. See the Pooja Bookings
 * plan's Phase B.
 */
class BookingCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $bookingId;
    public $poojaName;
    public $devoteeName;
    public $devoteeMobile;
    public $bookingDate;
    public $bookingTime;
    public $bookingStatus;
    public $createdByRole;

    public function __construct($bookingId, $poojaName, $devoteeName, $devoteeMobile, $bookingDate, $bookingTime, $bookingStatus, $createdByRole)
    {
        $this->bookingId = $bookingId;
        $this->poojaName = $poojaName;
        $this->devoteeName = $devoteeName;
        $this->devoteeMobile = $devoteeMobile;
        $this->bookingDate = $bookingDate;
        $this->bookingTime = $bookingTime;
        $this->bookingStatus = $bookingStatus;
        $this->createdByRole = $createdByRole;
    }

    public function envelope(): Envelope
    {
        $templeName = Setting::get('temple_name', 'SRI SELVA VINAYAKAR KOYIL (GANESHA TEMPLE)');

        return new Envelope(subject: "New Pooja Booking #{$this->bookingId} - {$templeName}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking_created',
            with: ['temple' => Setting::templeBranding()],
        );
    }
}
