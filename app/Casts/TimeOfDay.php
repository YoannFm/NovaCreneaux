<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Heure d'une colonne TIME, toujours au format HH:MM:SS. MySQL renvoie "12:00:00" alors que les formulaires envoient
 * "12:00" : sans ce format unique, chaque enregistrement croyait l'horaire modifie (faux e-mail "Horaire modifie",
 * journal et import de configuration faux). Affichage : substr(..., 0, 5).
 */
class TimeOfDay implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : self::normalize((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : self::normalize((string) $value);
    }

    public static function normalize(string $time): string
    {
        return preg_match('/^\d{2}:\d{2}$/', $time) ? "{$time}:00" : $time;
    }
}
