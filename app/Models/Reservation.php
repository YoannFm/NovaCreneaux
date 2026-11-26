<?php
namespace App\Models;
use App\Support\ReservationLimits;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Reservation extends Model {
    use HasFactory;
    protected $fillable = ['user_id','slot_id','reservation_date'];
    protected function casts(): array { return ['reservation_date'=>'date:Y-m-d']; }
    public function user() { return $this->belongsTo(User::class); }
    /** Le creneau meme supprime : l'historique garde son nom et ses horaires. */
    public function slot() { return $this->belongsTo(Slot::class)->withTrashed(); }

    /**
     * Jour au format AAAA-MM-JJ. Les regles comparent les jours comme du texte : "2026-11-25 00:00:00" n'etait pas
     * "aujourd'hui" et sautait l'heure de fermeture et le creneau deja commence.
     */
    public static function day(string $date): string
    {
        return Carbon::parse($date)->toDateString();
    }

    /** Fin du creneau ce jour-la. */
    public function endsAt(): Carbon
    {
        return Carbon::parse($this->reservation_date->toDateString() . ' ' . $this->slot->end_time);
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->reservation_date->toDateString() . ' ' . $this->slot->start_time);
    }

    /** Dernier moment ou le titulaire peut annuler lui-meme : debut du creneau moins le delai d'annulation de son role. */
    public function cancellableUntil(): Carbon
    {
        $deadline = $this->user?->role?->cancel_deadline_minutes ?? ReservationLimits::UNLIMITED;

        return $deadline === ReservationLimits::UNLIMITED ? $this->startsAt() : $this->startsAt()->subMinutes($deadline);
    }

    public function isCancellableByOwner(): bool
    {
        return now()->lt($this->cancellableUntil());
    }
}
