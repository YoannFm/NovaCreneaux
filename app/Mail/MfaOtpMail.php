<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MfaOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $type = 'email',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Code de vérification — ' . setting('app_name', 'NovaCreneaux'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.mfa-otp');
    }
}
