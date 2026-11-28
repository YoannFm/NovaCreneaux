<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Lien pour choisir un nouveau mot de passe (page "Mot de passe oublie" et action groupee de l'administration). */
class PasswordResetLink extends Mailable
{
    public function __construct(public string $name, public string $url, public int $expiresInMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Réinitialiser votre mot de passe - ' . setting('app_name', 'NovaCreneaux'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset');
    }
}
