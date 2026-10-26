<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Prevenu l'ancien titulaire : le compte n'existe plus, donc le message ne porte que des textes, pas le modele. */
class AccountDeleted extends Mailable
{
    public function __construct(public string $name, public bool $byAdmin) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte a été supprimé - ' . setting('app_name', 'NovaCreneaux'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-deleted');
    }
}
