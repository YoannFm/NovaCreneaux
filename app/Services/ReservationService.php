<?php
namespace App\Services;
use App\Mail\ReservationCancelled;
use App\Mail\ReservationConfirmed;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Regles de reservation, partagees par le site et l'API.
 *
 * Un echec est un tableau ['ok'=>false, 'code'=>..., 'error'=>...] : 'code' est stable (utilise par l'API),
 * 'error' est le message affiche a l'utilisateur. Codes : banned, slot_unavailable, date_past,
 * resource_closed_day, resource_closed_today, slot_started, daily_limit, weekly_limit, advance_limit,
 * slot_full, already_booked, reservation_past, cancel_deadline.
 */
class ReservationService {
    public function create(User $user, int $slotId, string $date): array {
        $result = DB::transaction(fn () => $this->book($user, $slotId, $date));
        if ($result['ok']) {
            $this->notify($user, new ReservationConfirmed($result['reservation']));
        }
        return $result;
    }

    /** Verrouille le creneau pour que deux demandes simultanees ne depassent pas sa capacite. */
    private function book(User $user, int $slotId, string $date): array {
        $slot = Slot::with('resource')->lockForUpdate()->findOrFail($slotId);

        if ($failure = $this->checkBookable($user, $slot, $date)) return $failure;

        $reservation = Reservation::create(['user_id'=>$user->id,'slot_id'=>$slot->id,'reservation_date'=>$date]);
        $reservation->load('slot.resource');
        return ['ok'=>true,'reservation'=>$reservation];
    }

    /** Verifie toutes les regles sans rien ecrire. Renvoie l'echec, ou null si la reservation est possible. */
    public function checkBookable(User $user, Slot $slot, string $date): ?array {
        $ban = $user->activeBan();
        if ($ban) {
            if ($ban->isPermanent())
                return $this->failure('banned', 'Votre accès au CDI est banni définitivement. Motif : ' . $ban->reason);
            return $this->failure('banned', 'Votre accès au CDI est suspendu jusqu\'au ' . $ban->banned_until->format('d/m/Y') . '. Motif : ' . $ban->reason);
        }

        if (!$slot->is_active || !$slot->resource->is_active)
            return $this->failure('slot_unavailable', "Ce créneau n'est pas disponible.");

        $check = $slot->resource->canReserveFor($date);
        if (!$check['allowed']) return $this->failure($check['code'], $check['reason']);

        // Un creneau deja commence (ou termine) aujourd'hui n'est plus reservable
        if ($date === Carbon::today()->toDateString() && Carbon::now()->format('H:i:s') >= $slot->start_time)
            return $this->failure('slot_started', 'Ce créneau a déjà commencé.');

        $role = $user->role;

        // Max reservations per day
        $maxDay = $role ? $role->max_reservations_day : -1;
        if ($maxDay !== -1) {
            $dayCount = Reservation::where('user_id', $user->id)->where('reservation_date', $date)->count();
            if ($dayCount >= $maxDay)
                return $this->failure('daily_limit', "Limite de {$maxDay} réservation(s) par jour atteinte.");
        }

        // Max reservations per week
        $maxWeek = $role ? $role->max_reservations_week : -1;
        if ($maxWeek !== -1) {
            $d = Carbon::parse($date);
            $weekCount = Reservation::where('user_id', $user->id)
                ->whereBetween('reservation_date', [$d->startOfWeek()->toDateString(), $d->endOfWeek()->toDateString()])
                ->count();
            if ($weekCount >= $maxWeek)
                return $this->failure('weekly_limit', "Limite de {$maxWeek} réservation(s) par semaine atteinte.");
        }

        // Advance days limit
        $advanceDays = $role ? $role->advance_days : 30;
        $maxDate = Carbon::today()->addDays($advanceDays)->toDateString();
        if ($date > $maxDate)
            return $this->failure('advance_limit', "Vous ne pouvez pas réserver plus de {$advanceDays} jours à l'avance.");

        if ($slot->availableCountFor($date) <= 0)
            return $this->failure('slot_full', 'Ce créneau est complet.');

        if (Reservation::where('user_id',$user->id)->where('slot_id',$slot->id)->where('reservation_date',$date)->exists())
            return $this->failure('already_booked', 'Vous avez déjà réservé ce créneau.');

        return null;
    }

    public function cancel(User $user, int $reservationId): array {
        $reservation = Reservation::where('user_id',$user->id)->findOrFail($reservationId);
        if ($reservation->reservation_date->isPast() && !$reservation->reservation_date->isToday())
            return $this->failure('reservation_past', "Impossible d'annuler une réservation passée.");

        // Bloquer si le créneau a déjà commencé
        $slotStart = Carbon::parse($reservation->reservation_date->toDateString() . ' ' . $reservation->slot->start_time);
        if (Carbon::now()->gte($slotStart))
            return $this->failure('slot_started', "Impossible d'annuler un créneau déjà commencé.");

        // Cancel deadline
        $role = $user->role;
        $deadline = $role ? $role->cancel_deadline_minutes : -1;
        if ($deadline !== -1) {
            if (Carbon::now()->diffInMinutes($slotStart, false) < $deadline)
                return $this->failure('cancel_deadline', "Annulation impossible : le délai de {$deadline} minute(s) avant le créneau est dépassé.");
        }

        $this->remove($reservation);
        return ['ok'=>true];
    }

    /** Annulation par un administrateur : aucune regle de delai, l'eleve est prevenu comme pour une annulation normale. */
    public function cancelByAdmin(Reservation $reservation): void {
        $this->remove($reservation);
    }

    private function remove(Reservation $reservation): void {
        $reservation->load('slot.resource','user');
        $reservation->delete();
        $this->notify($reservation->user, new ReservationCancelled($reservation));
    }

    /** Un e-mail qui echoue ne doit pas annuler l'operation, mais l'echec est journalise. */
    private function notify(User $user, $mailable): void {
        try {
            Mail::to($user->email)->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function failure(string $code, string $message): array {
        return ['ok'=>false,'code'=>$code,'error'=>$message];
    }
}
