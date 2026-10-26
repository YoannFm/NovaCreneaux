<?php

namespace App\Services\Webhook;

use App\Support\WebhookEvent;
use Carbon\Carbon;

/**
 * Message Discord pour un evenement : Discord n'accepte que son propre format (embeds), pas le JSON de NovaCreneaux.
 * Par defaut le nom des eleves n'y figure pas : Discord est un service tiers, l'administrateur doit choisir de les y envoyer.
 */
class DiscordMessage
{
    private const GREEN = 0x2ECC71;
    private const RED = 0xE74C3C;
    private const BLUE = 0x3498DB;
    private const ORANGE = 0xE67E22;

    /** @param array{id: string, event: string, created_at: string, data: array} $payload charge utile standard */
    public static function build(array $payload, bool $showNames, string $appName): array
    {
        [$title, $lines, $color] = self::describe($payload['event'], $payload['data'], $showNames);

        return [
            'username' => mb_substr($appName, 0, 80),
            'embeds' => [[
                'title' => $title,
                'description' => mb_substr(implode("\n", array_filter($lines)), 0, 4000),
                'color' => $color,
                'timestamp' => $payload['created_at'],
            ]],
            // Aucune mention (@everyone, @role) ne peut etre declenchee par un nom ou un motif
            'allowed_mentions' => ['parse' => []],
        ];
    }

    private static function describe(string $event, array $data, bool $showNames): array
    {
        $who = fn (array $user) => $showNames ? $user['name'] . ($user['classe'] ? " ({$user['classe']})" : '') : 'un élève';

        return match ($event) {
            WebhookEvent::RESERVATION_CREATED => [
                '📅 Nouvelle réservation', [self::reservation($data['reservation']), 'Par ' . $who($data['user'])], self::GREEN,
            ],
            WebhookEvent::RESERVATION_CANCELLED => [
                '❌ Réservation annulée', [
                    self::reservation($data['reservation']),
                    ($data['cancelled_by'] === 'admin' ? 'Annulée par un administrateur' : 'Annulée par l\'élève') . ' : ' . $who($data['user']),
                ], self::RED,
            ],
            WebhookEvent::USER_CREATED => [
                '👤 Nouveau compte', [$showNames ? $data['user']['name'] . ($data['user']['classe'] ? " ({$data['user']['classe']})" : '') : 'Un compte a été créé.'], self::BLUE,
            ],
            WebhookEvent::USER_DELETED => [
                '🗑️ Compte supprimé', [$showNames ? $data['user']['name'] . ($data['user']['classe'] ? " ({$data['user']['classe']})" : '') : 'Un compte a été supprimé.'], self::ORANGE,
            ],
            default => ['✅ Test', [$data['message'] ?? 'Test'], self::GREEN],
        };
    }

    private static function reservation(array $reservation): string
    {
        $date = Carbon::parse($reservation['date'])->locale('fr')->isoFormat('dddd D MMMM YYYY');

        return "**{$reservation['resource']['name']}** - {$reservation['slot']['name']}\n{$date}";
    }
}
