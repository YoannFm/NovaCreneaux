<?php

namespace App\Services\Webhook;

use App\Models\Reservation;
use App\Models\User;

/** Donnees envoyees aux webhooks. Pas d'adresse e-mail : un service externe n'en a pas besoin pour agir sur une reservation. */
class WebhookPayload
{
    public static function reservation(Reservation $reservation): array
    {
        $reservation->loadMissing('slot.resource', 'user.role');

        return [
            'reservation' => [
                'id' => $reservation->id,
                'date' => $reservation->reservation_date->toDateString(),
                'slot' => [
                    'id' => $reservation->slot->id,
                    'name' => $reservation->slot->name,
                    'start_time' => $reservation->slot->start_time,
                    'end_time' => $reservation->slot->end_time,
                ],
                'resource' => ['id' => $reservation->slot->resource->id, 'name' => $reservation->slot->resource->name],
            ],
            'user' => self::user($reservation->user),
        ];
    }

    public static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'classe' => $user->classe,
            'role' => $user->role?->name,
        ];
    }
}
