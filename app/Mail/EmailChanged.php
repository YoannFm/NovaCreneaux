<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Envoye a l'ancienne adresse quand l'adresse du compte change : son titulaire peut reagir si ce n'est pas lui. */
class EmailChanged extends Mailable
{
    public function __construct(public string $name, public string $newEmail) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Adresse e-mail modifiée - ' . setting('app_name', 'NovaCreneaux'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.email-changed');
    }
}
