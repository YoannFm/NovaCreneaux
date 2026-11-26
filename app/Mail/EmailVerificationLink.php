<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Lien pour confirmer son adresse e-mail (Parametres > Securite : confirmation exigee). */
class EmailVerificationLink extends Mailable
{
    public function __construct(public string $name, public string $url, public int $expiresInMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirmer votre adresse e-mail - ' . setting('app_name', 'NovaCreneaux'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.email-verification');
    }
}
