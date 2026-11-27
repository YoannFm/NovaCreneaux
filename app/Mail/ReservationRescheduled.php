<?php
namespace App\Mail;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
/** L'horaire du creneau d'une reservation a venir a change ; $previous : ancien horaire ("08:00 - 09:00"). */
class ReservationRescheduled extends Mailable {
    use Queueable, SerializesModels;
    public function __construct(public Reservation $reservation, public string $previous) {}
    public function envelope(): Envelope {
        return new Envelope(subject: 'Horaire modifié - '.setting('app_name','NovaCreneaux'));
    }
    public function content(): Content {
        return new Content(view: 'emails.reservation-rescheduled');
    }
}
