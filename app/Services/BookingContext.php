<?php
namespace App\Services;

use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ce qui, chez un compte, decide d'une reservation : sa suspension et ses reservations des semaines concernees.
 * Charge en deux requetes pour toutes les dates d'une page (page de reservation), ou pour une seule date au moment
 * de reserver : ReservationService::checkWith() applique ensuite les memes regles dans les deux cas.
 */
final class BookingContext {
    /** @param Collection<int, Reservation> $reservations reservations du compte sur les semaines des dates demandees */
    private function __construct(public readonly User $user, public readonly ?Sanction $ban, private Collection $reservations) {}

    /** @param list<string> $dates jours AAAA-MM-JJ */
    public static function load(User $user, array $dates): self {
        $from = Carbon::parse(min($dates))->startOfWeek()->toDateString();
        $to = Carbon::parse(max($dates))->endOfWeek()->toDateString();
        $reservations = Reservation::where('user_id', $user->id)->whereBetween('reservation_date', [$from, $to])
            ->get(['slot_id', 'reservation_date']);

        return new self($user, $user->activeBan(), $reservations);
    }

    public function hasBooked(int $slotId, string $date): bool {
        return $this->reservations->contains(fn (Reservation $r) => $r->slot_id === $slotId && $r->reservation_date->toDateString() === $date);
    }

    public function countOn(string $date): int {
        return $this->reservations->filter(fn (Reservation $r) => $r->reservation_date->toDateString() === $date)->count();
    }

    public function countInWeekOf(string $date): int {
        $day = Carbon::parse($date);
        [$from, $to] = [$day->copy()->startOfWeek()->toDateString(), $day->copy()->endOfWeek()->toDateString()];

        return $this->reservations->filter(fn (Reservation $r) => $r->reservation_date->toDateString() >= $from && $r->reservation_date->toDateString() <= $to)->count();
    }
}
