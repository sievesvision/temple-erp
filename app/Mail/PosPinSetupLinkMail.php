<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an Organisation Admin or an event-admin-level coordinator triggers a POS PIN
 * setup/reset for a staff member (see AccountSetupService::sendPosPinSetupLink()) — never
 * anything else, since nothing in this app sets users.pos_pin directly from an
 * admin-submitted value; this link is the only path to it besides the account holder's
 * own self-service change-PIN form.
 */
class PosPinSetupLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $url;

    public function __construct($name, $url)
    {
        $this->name = $name;
        $this->url = $url;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Setting::get('temple_name', 'SRI SELVA VINAYAKAR KOYIL (GANESHA TEMPLE)') . ' - Set Your POS PIN',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pos_pin_setup_link',
        );
    }
}
