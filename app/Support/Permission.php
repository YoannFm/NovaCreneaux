<?php

namespace App\Support;

/**
 * Permissions que l'on peut donner a un role non administrateur.
 * Un administrateur les a toutes. Reglages, mises a jour, sauvegardes, jetons d'API et roles ne sont pas delegables :
 * celui qui les tient pourrait s'attribuer tous les droits.
 */
final class Permission
{
    public const NAVIGATION = 'navigation.manage';
    public const PAGES = 'pages.manage';
    public const RESOURCES = 'resources.manage';
    public const RESERVATIONS = 'reservations.manage';
    public const USERS = 'users.manage';
    public const SANCTIONS = 'sanctions.manage';
    public const NOTIFICATIONS = 'notifications.manage';
    public const APPEARANCE = 'appearance.manage';
    public const LOGS = 'logs.view';

    /** @return array<string, string> cle => libelle */
    public static function all(): array
    {
        return [
            self::RESOURCES => 'Ressources et créneaux',
            self::RESERVATIONS => 'Réservations de tous les comptes',
            self::USERS => 'Utilisateurs (comptes non administrateurs)',
            self::SANCTIONS => 'Sanctions',
            self::NOTIFICATIONS => 'Notifications aux utilisateurs',
            self::PAGES => 'Pages du site',
            self::NAVIGATION => 'Barre de navigation du site',
            self::APPEARANCE => 'Apparence (couleurs)',
            self::LOGS => "Journal d'activité (lecture)",
        ];
    }

    /** Ne garde que les permissions connues, sans doublon : ce qui vient d'un formulaire n'est jamais cru tel quel. */
    public static function only(array $keys): array
    {
        return array_values(array_intersect(array_keys(self::all()), $keys));
    }
}
