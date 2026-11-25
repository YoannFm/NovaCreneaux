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
    public function slot() { return $this->belongsTo(Slot::class); }

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
