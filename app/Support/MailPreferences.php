<?php

namespace App\Support;

/**
 * E-mails que chacun peut choisir de ne plus recevoir (Profil > E-mails). La notification de la cloche reste toujours.
 * Les e-mails de securite (mot de passe oublie, confirmation de l'adresse, codes de double authentification,
 * changement d'adresse, suppression du compte) partent toujours : ils ne sont pas dans cette liste.
 */
final class MailPreferences
{
    public const RESERVATION_CONFIRMED = 'reservation_confirmed';
    public const CANCELLED_BY_ME = 'cancelled_by_me';
    public const CANCELLED_BY_ADMIN = 'cancelled_by_admin';
    public const SLOT_CHANGED = 'slot_changed';

    /** cle => [libelle, precision] */
    public const LABELS = [
        self::RESERVATION_CONFIRMED => ['Confirmation de mes réservations', 'À chaque réservation, faite par vous ou par le personnel.'],
        self::CANCELLED_BY_ME => ['Confirmation de mes annulations', 'Quand vous annulez vous-même une réservation.'],
        self::CANCELLED_BY_ADMIN => ["Annulation par l'établissement", 'Réservation annulée par le personnel, créneau supprimé ou compte désactivé.'],
        self::SLOT_CHANGED => ["Changement d'horaire", "L'horaire d'un créneau que vous avez réservé a changé."],
    ];

    /** Cles cochees dans le formulaire -> cles refusees, seules enregistrees (une nouvelle cle est donc envoyee par defaut). */
    public static function optOut(array $wanted): array
    {
        return array_values(array_diff(array_keys(self::LABELS), $wanted));
    }
}
