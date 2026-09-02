<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an admin clicks "Send Reset Link" for a user on the System Users page — a real
 * clickable link (via Laravel's built-in password broker, see User::sendPasswordResetNotification())
 * rather than the OTP code used by the self-service ForgotPasswordMail flow.
 */
class AdminPasswordResetLinkMail extends Mailable
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
            subject: Setting::get('temple_name', 'SRI SELVA VINAYAKAR KOYIL (GANESHA TEMPLE)') . ' - Password Reset Requested',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_reset_link',
        );
    }
}
