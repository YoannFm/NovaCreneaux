<?php

namespace App\Services\Webhook;

use App\Support\WebhookEvent;
use Carbon\Carbon;

/**
 * Les variables d'un evenement, utilisables dans les modeles des webhooks (formats personnalises et prereglages).
 * Par defaut le nom des eleves n'y figure pas : un service tiers n'a pas a le recevoir, l'administrateur doit choisir
 * de l'y envoyer (`who` et `user_*` disent alors « un eleve » et restent vides).
 */
class EventMessage
{
    private const GREEN = 0x2ECC71;
    private const RED = 0xE74C3C;
    private const BLUE = 0x3498DB;
    private const ORANGE = 0xE67E22;

    /** Variables fournies par l'evenement, avec leur sens (affichees dans le formulaire du format personnalise). */
    public const VARIABLES = [
        'title' => "Titre avec pictogramme : « 📅 Nouvelle réservation »",
        'title_plain' => 'Titre sans pictogramme',
        'text' => 'Détail sur plusieurs lignes (ressource, créneau, date, élève)',
        'summary' => 'Titre et détail sur une seule ligne',
        'event' => 'Nom de l\'événement : reservation.created, user.deleted...',
        'created_at' => 'Date et heure de l\'événement (ISO 8601)',
        'delivery_id' => 'Identifiant unique de l\'envoi',
        'app_name' => 'Nom du site',
        'color_int' => 'Couleur de l\'événement en nombre (Discord)',
        'color_hex' => 'Couleur de l\'événement en hexadécimal (#2ECC71)',
        'who' => 'L\'élève (« un élève » si les noms sont masqués)',
        'user_name' => 'Nom de l\'élève (vide si les noms sont masqués)',
        'user_classe' => 'Classe de l\'élève (vide si les noms sont masqués)',
        'resource' => 'Nom de la ressource réservée',
        'slot' => 'Nom du créneau',
        'date' => 'Date de la réservation (2026-10-27)',
        'date_fr' => 'Date de la réservation en français (mardi 27 octobre 2026)',
        'start_time' => 'Début du créneau',
        'end_time' => 'Fin du créneau',
    ];

    /**
     * @param  array{id: string, event: string, created_at: string, data: array}  $payload  charge utile standard
     * @return array<string, string|int>
     */
    public static function variables(array $payload, bool $showNames, string $appName, string $deliveryId): array
    {
        $data = $payload['data'];
        $user = $data['user'] ?? null;
        $reservation = $data['reservation'] ?? null;
        $who = self::who($user, $showNames);
        [$emoji, $title, $lines, $color] = self::describe($payload['event'], $data, $who);
        $text = mb_substr(implode("\n", array_filter($lines)), 0, 4000);
        $date = $reservation ? Carbon::parse($reservation['date']) : null;

        return [
            'title' => "{$emoji} {$title}",
            'title_plain' => $title,
            'text' => $text,
            'summary' => trim($title . ' : ' . str_replace("\n", ' - ', $text), ' :'),
            'event' => $payload['event'],
            'created_at' => $payload['created_at'],
            'delivery_id' => $deliveryId,
            'app_name' => mb_substr($appName, 0, 80),
            'color_int' => $color,
            'color_hex' => sprintf('#%06X', $color),
            'who' => $who,
            'user_name' => $showNames ? (string) ($user['name'] ?? '') : '',
            'user_classe' => $showNames ? (string) ($user['classe'] ?? '') : '',
            'resource' => (string) ($reservation['resource']['name'] ?? ''),
            'slot' => (string) ($reservation['slot']['name'] ?? ''),
            'date' => (string) ($reservation['date'] ?? ''),
            'date_fr' => $date ? $date->locale('fr')->isoFormat('dddd D MMMM YYYY') : '',
            'start_time' => (string) ($reservation['slot']['start_time'] ?? ''),
            'end_time' => (string) ($reservation['slot']['end_time'] ?? ''),
        ];
    }

    private static function who(?array $user, bool $showNames): string
    {
        if (! $showNames || ! $user) {
            return 'un élève';
        }

        return $user['name'] . (($user['classe'] ?? null) ? " ({$user['classe']})" : '');
    }

    /** @return array{0: string, 1: string, 2: list<string>, 3: int} pictogramme, titre, lignes, couleur */
    private static function describe(string $event, array $data, string $who): array
    {
        $known = $who !== 'un élève';

        return match ($event) {
            WebhookEvent::RESERVATION_CREATED => ['📅', 'Nouvelle réservation', [...self::reservationLines($data['reservation']), "Par {$who}"], self::GREEN],
            WebhookEvent::RESERVATION_CANCELLED => ['❌', 'Réservation annulée', [
                ...self::reservationLines($data['reservation']),
                ($data['cancelled_by'] === 'admin' ? 'Annulée par un administrateur' : 'Annulée par l\'élève') . " : {$who}",
            ], self::RED],
            WebhookEvent::USER_CREATED => ['👤', 'Nouveau compte', [$known ? $who : 'Un compte a été créé.'], self::BLUE],
            WebhookEvent::USER_DELETED => ['🗑️', 'Compte supprimé', [$known ? $who : 'Un compte a été supprimé.'], self::ORANGE],
            default => ['✅', 'Test', [$data['message'] ?? 'Test'], self::GREEN],
        };
    }

    /** @return list<string> */
    private static function reservationLines(array $reservation): array
    {
        $date = Carbon::parse($reservation['date'])->locale('fr')->isoFormat('dddd D MMMM YYYY');

        return ["{$reservation['resource']['name']} - {$reservation['slot']['name']}", $date];
    }
}
