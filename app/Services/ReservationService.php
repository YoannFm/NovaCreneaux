<?php
namespace App\Services;
use App\Mail\ReservationCancelled;
use App\Mail\ReservationConfirmed;
use App\Mail\ReservationRescheduled;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use App\Services\Webhook\WebhookDispatcher;
use App\Services\Webhook\WebhookPayload;
use App\Support\Customization;
use App\Support\MailPreferences;
use App\Support\WebhookEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;


/**
 * Regles de reservation, partagees par le site et l'API.
 *
 * Un echec est un tableau ['ok'=>false, 'code'=>..., 'error'=>...] : 'code' est stable (utilise par l'API),
 * 'error' est le message affiche a l'utilisateur. Codes : banned, slot_unavailable, date_past,
 * resource_closed_day, resource_closed_today, slot_started, daily_limit, weekly_limit, advance_limit,
 * slot_full, already_booked, reservation_past, cancel_deadline, account_disabled (ajout par le personnel).
 */
class ReservationService {
    /** Motif des annulations faites quand un compte est desactive. */
    public const DEACTIVATION_REASON = 'Votre compte a été désactivé.';

    public function __construct(private SiteNotifier $notifier, private WebhookDispatcher $webhooks) {}

    public function create(User $user, int $slotId, string $date): array {
        $date = Reservation::day($date);
        return $this->createChecked($user, $slotId, $date, fn (Slot $slot) => $this->checkBookable($user, $slot, $date));
    }

    /**
     * Reservation faite par le personnel pour un compte (eleve sans acces a son compte...) : ni limites du role,
     * ni fenetre d'ouverture, ni suspension. Restent : creneau actif, pas dans le passe, pas termine, place libre, pas de doublon.
     */
    public function createByStaff(User $user, int $slotId, string $date): array {
        $date = Reservation::day($date);
        return $this->createChecked($user, $slotId, $date, fn (Slot $slot) => $this->checkStaffBookable($user, $slot, $date));
    }

    /** @param callable(Slot): ?array $check */
    private function createChecked(User $user, int $slotId, string $date, callable $check): array {
        $result = DB::transaction(fn () => $this->book($user, $slotId, $date, $check));
        if ($result['ok']) {
            $this->notify($user, new ReservationConfirmed($result['reservation']), MailPreferences::RESERVATION_CONFIRMED);
            $this->webhooks->dispatch(WebhookEvent::RESERVATION_CREATED, WebhookPayload::reservation($result['reservation']));
        }
        return $result;
    }

    /**
     * Deux verrous jusqu'a la fin de la transaction, toujours dans cet ordre (compte puis creneau, jamais l'inverse) :
     * - le compte : deux demandes du meme compte sur des creneaux differents ne comptent pas ses reservations
     *   du jour ou de la semaine en meme temps (sinon elles depasseraient ensemble la limite du role) ;
     * - le creneau : deux demandes de comptes differents ne depassent pas sa capacite.
     */
    private function book(User $user, int $slotId, string $date, callable $check): array {
        User::whereKey($user->id)->lockForUpdate()->first();
        $slot = Slot::with('resource')->lockForUpdate()->findOrFail($slotId);

        if ($failure = $check($slot)) return $failure;

        $reservation = Reservation::create(['user_id'=>$user->id,'slot_id'=>$slot->id,'reservation_date'=>$date]);
        $reservation->load('slot.resource');
        return ['ok'=>true,'reservation'=>$reservation];
    }

    /** " Pour toute question, contactez le CDI." (Parametres > General, "Qui contacter") */
    private static function contactHint(): string {
        return ' Pour toute question, contactez ' . Customization::get('contact_label') . '.';
    }

    /** Verifie toutes les regles sans rien ecrire. Renvoie l'echec, ou null si la reservation est possible. */
    public function checkBookable(User $user, Slot $slot, string $date): ?array {
        $date = Reservation::day($date);
        return $this->checkWith(BookingContext::load($user, [$date]), $slot, $date, $slot->availableCountFor($date));
    }

    /**
     * Les regles, avec les donnees deja chargees : la page de reservation les applique a chaque bouton sans une
     * requete par creneau, et le bouton dit ce que le serveur repondrait. $free : places libres de ce creneau ce jour.
     */
    public function checkWith(BookingContext $context, Slot $slot, string $date, int $free): ?array {
        $user = $context->user;
        if ($ban = $context->ban) {
            if ($ban->isPermanent())
                return $this->failure('banned', 'Votre accès aux réservations est suspendu définitivement. Motif : ' . $ban->reason . self::contactHint());
            return $this->failure('banned', 'Votre accès aux réservations est suspendu jusqu\'au ' . $ban->banned_until->format('d/m/Y') . '. Motif : ' . $ban->reason . self::contactHint());
        }

        if (!$slot->isOpenForBooking() || !$slot->resource->is_active)
            return $this->failure('slot_unavailable', "Ce créneau n'est pas disponible.");

        // Avant les limites : une reservation deja faite les remplit, mais c'est elle qu'il faut signaler
        if ($context->hasBooked($slot->id, $date))
            return $this->failure('already_booked', 'Vous avez déjà réservé ce créneau.');

        // Fenetre de la ressource et limite "a l'avance" du role (Resource::canReserveFor)
        $check = $slot->resource->canReserveFor($date, $user->advanceDays());
        if (!$check['allowed']) return $this->failure($check['code'], $check['reason']);

        // Un creneau deja commence (ou termine) aujourd'hui n'est plus reservable
        if ($date === Carbon::today()->toDateString() && Carbon::now()->format('H:i:s') >= $slot->start_time)
            return $this->failure('slot_started', 'Ce créneau a déjà commencé.');

        $role = $user->role;

        $maxDay = $role ? $role->max_reservations_day : -1;
        if ($maxDay !== -1 && $context->countOn($date) >= $maxDay)
            return $this->failure('daily_limit', "Limite de {$maxDay} réservation(s) par jour atteinte.");

        $maxWeek = $role ? $role->max_reservations_week : -1;
        if ($maxWeek !== -1 && $context->countInWeekOf($date) >= $maxWeek)
            return $this->failure('weekly_limit', "Limite de {$maxWeek} réservation(s) par semaine atteinte.");

        if ($free <= 0)
            return $this->failure('slot_full', 'Ce créneau est complet.');

        return null;
    }

    private function checkStaffBookable(User $user, Slot $slot, string $date): ?array {
        // Un compte desactive ne prend plus de place (ses reservations a venir sont annulees a la desactivation)
        if (!$user->is_active)
            return $this->failure('account_disabled', 'Ce compte est désactivé.');
        if (!$slot->isOpenForBooking() || !$slot->resource->is_active)
            return $this->failure('slot_unavailable', "Ce créneau n'est pas disponible.");
        if ($date < Carbon::today()->toDateString())
            return $this->failure('date_past', 'Cette date est passée.');
        if ($date === Carbon::today()->toDateString() && Carbon::now()->format('H:i:s') >= $slot->end_time)
            return $this->failure('slot_started', 'Ce créneau est terminé.');
        if (Reservation::where('user_id',$user->id)->where('slot_id',$slot->id)->where('reservation_date',$date)->exists())
            return $this->failure('already_booked', "{$user->name} a déjà réservé ce créneau.");
        if ($slot->availableCountFor($date) <= 0)
            return $this->failure('slot_full', 'Ce créneau est complet.');
        return null;
    }

    public function cancel(User $user, int $reservationId): array {
        $reservation = Reservation::where('user_id',$user->id)->findOrFail($reservationId);
        if ($reservation->reservation_date->isPast() && !$reservation->reservation_date->isToday())
            return $this->failure('reservation_past', "Impossible d'annuler une réservation passée.");

        // Bloquer si le créneau a déjà commencé
        if (Carbon::now()->gte($reservation->startsAt()))
            return $this->failure('slot_started', "Impossible d'annuler un créneau déjà commencé.");

        // Délai d'annulation du rôle (Reservation::cancellableUntil, aussi affiché dans "Mes réservations")
        if (!$reservation->isCancellableByOwner()) {
            $deadline = $user->role->cancel_deadline_minutes;
            return $this->failure('cancel_deadline', "Annulation impossible : le délai de {$deadline} minute(s) avant le créneau est dépassé.");
        }

        $this->remove($reservation);
        return ['ok'=>true];
    }

    /**
     * Ce que le serveur repondrait pour chaque creneau actif de ces ressources et chaque date, en une requete pour le
     * compte (BookingContext) : [id du creneau][date] => echec ou null. $counts : places libres (AvailabilityService).
     * La page de reservation en tire ses boutons, l'accueil le nombre de creneaux encore reservables.
     */
    public function statuses(User $user, iterable $resources, array $dates, array $counts): array {
        $context = BookingContext::load($user, $dates);
        $statuses = [];
        foreach ($resources as $resource) {
            foreach ($resource->activeSlots as $slot) {
                $slot->setRelation('resource', $resource);
                foreach ($dates as $date) {
                    $statuses[$slot->id][$date] = $this->checkWith($context, $slot, $date, $counts[$slot->id][$date] ?? $slot->max_capacity);
                }
            }
        }
        return $statuses;
    }

    /** Annulation par un administrateur : aucune regle de delai. L'eleve est prevenu (cloche et e-mail), avec le motif s'il y en a un. */
    public function cancelByAdmin(Reservation $reservation, ?string $reason = null): void {
        $this->remove($reservation, true, $reason);
    }

    /**
     * Annule et previent les titulaires des reservations a venir de ces creneaux, quand l'administrateur supprime un
     * creneau ou une ressource. Une reservation dont le creneau est termine (y compris plus tot aujourd'hui) n'est
     * pas annulee : personne n'est prevenu d'une seance deja passee. Renvoie le nombre de reservations annulees.
     */
    public function cancelUpcomingForSlots(iterable $slotIds, string $reason): int {
        return $this->cancelUpcoming(Reservation::whereIn('slot_id', collect($slotIds)), $reason);
    }

    /**
     * Desactivation d'un compte (eleve parti, par exemple) : ses reservations a venir sont annulees, ce qui libere
     * les places et le retire des feuilles d'appel. Ses reservations passees restent dans l'historique.
     */
    public function cancelUpcomingForUsers(iterable $userIds, string $reason): int {
        return $this->cancelUpcoming(Reservation::whereIn('user_id', collect($userIds)), $reason);
    }

    /**
     * L'horaire d'un creneau a change : chaque titulaire d'une reservation pas encore terminee est prevenu (cloche et
     * e-mail). La reservation est gardee. Renvoie le nombre de personnes prevenues.
     */
    public function notifyRescheduled(Slot $slot, string $previousStart, string $previousEnd): int {
        $previous = substr($previousStart, 0, 5) . ' - ' . substr($previousEnd, 0, 5);
        $reservations = $slot->reservations()->with('slot.resource', 'user')->whereDate('reservation_date', '>=', Carbon::today())->get()
            ->filter(fn (Reservation $reservation) => $reservation->endsAt()->isFuture());
        foreach ($reservations as $reservation) {
            $this->notify($reservation->user, new ReservationRescheduled($reservation, $previous), MailPreferences::SLOT_CHANGED);
            $this->notifier->send($reservation->user, 'Horaire modifié', $this->describe($reservation, null) . "\nNouvel horaire : "
                . substr($slot->start_time, 0, 5) . ' - ' . substr($slot->end_time, 0, 5) . " (avant : {$previous})", 'warning');
        }
        return $reservations->count();
    }

    /** Fin du message de confirmation apres une annulation groupee (rien si aucune reservation n'etait a venir). */
    public static function cancelledNote(int $cancelled): string {
        return $cancelled > 0 ? " {$cancelled} réservation(s) à venir annulée(s), les personnes concernées ont été prévenues." : '';
    }

    /** Annule (et previent) les reservations de la requete dont le creneau n'est pas termine. */
    private function cancelUpcoming(Builder $query, string $reason): int {
        $reservations = $query->with('slot')->whereDate('reservation_date', '>=', Carbon::today())->get()
            ->filter(fn (Reservation $reservation) => $reservation->endsAt()->isFuture());
        foreach ($reservations as $reservation) {
            $this->remove($reservation, true, $reason);
        }
        return $reservations->count();
    }

    private function remove(Reservation $reservation, bool $byAdmin = false, ?string $reason = null): void {
        $reservation->load('slot.resource','user.role');
        $reservation->delete();
        $this->notify($reservation->user, new ReservationCancelled($reservation, $byAdmin, $reason), $byAdmin ? MailPreferences::CANCELLED_BY_ADMIN : MailPreferences::CANCELLED_BY_ME);
        $this->webhooks->dispatch(WebhookEvent::RESERVATION_CANCELLED, WebhookPayload::reservation($reservation) + ['cancelled_by' => $byAdmin ? 'admin' : 'user']);

        if ($byAdmin) {
            $this->notifier->send($reservation->user, 'Réservation annulée par un administrateur', $this->describe($reservation, $reason), 'warning');
        }
    }

    private function describe(Reservation $reservation, ?string $reason): string {
        $lines = [
            "{$reservation->slot->resource->name}, {$reservation->slot->name}",
            $reservation->reservation_date->isoFormat('dddd D MMMM YYYY'),
        ];
        if ($reason !== null && $reason !== '') {
            $lines[] = "Motif : {$reason}";
        }
        return implode("\n", $lines);
    }

    /** Un e-mail qui echoue ne doit pas annuler l'operation, mais l'echec est journalise. */
    /** E-mail facultatif : seulement si la personne le veut (Profil > E-mails, $kind de Support\MailPreferences). */
    private function notify(User $user, $mailable, string $kind): void {
        if (!$user->wantsMail($kind)) return;
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
