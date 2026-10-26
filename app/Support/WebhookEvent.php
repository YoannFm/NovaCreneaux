<?php

namespace App\Support;

/** Evenements auxquels un webhook peut s'abonner. `ping` n'existe que pour le bouton de test. */
final class WebhookEvent
{
    public const RESERVATION_CREATED = 'reservation.created';
    public const RESERVATION_CANCELLED = 'reservation.cancelled';
    public const USER_CREATED = 'user.created';
    public const USER_DELETED = 'user.deleted';
    public const PING = 'ping';

    /** @return array<string, string> cle => libelle */
    public static function all(): array
    {
        return [
            self::RESERVATION_CREATED => 'Réservation créée',
            self::RESERVATION_CANCELLED => 'Réservation annulée (par l\'élève, un administrateur ou l\'API)',
            self::USER_CREATED => 'Compte créé',
            self::USER_DELETED => 'Compte supprimé',
        ];
    }

    public static function only(array $events): array
    {
        return array_values(array_intersect(array_keys(self::all()), $events));
    }
}
