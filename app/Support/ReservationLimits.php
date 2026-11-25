<?php

namespace App\Support;

/**
 * Limites de reservation d'un role (Admin > Parametres > Reservations) : une seule description
 * pour le formulaire, la validation et l'enregistrement. -1 en base signifie "sans limite".
 */
final class ReservationLimits
{
    public const UNLIMITED = -1;

    /** Cle de la colonne de roles => [libelle, icone Bootstrap, aide, unite, min, max, libelle "sans limite" ou null si toujours borne]. */
    private const FIELDS = [
        'max_reservations_day' => ['Réservations par jour', 'bi-calendar-check', 'Créneaux réservables sur une même journée.', 'par jour', 0, 99, 'Illimité'],
        'max_reservations_week' => ['Réservations par semaine', 'bi-calendar-week', 'Créneaux réservables du lundi au dimanche.', 'par semaine', 0, 99, 'Illimité'],
        'cancel_deadline_minutes' => ["Délai d'annulation", 'bi-hourglass-split', 'Temps minimum avant le début du créneau pour annuler.', 'min', 0, 10080, 'Toujours possible'],
        'advance_days' => ["Réservation à l'avance", 'bi-calendar-range', "Jours réservables à l'avance ; le plus lointain s'ouvre à l'heure d'ouverture de la ressource.", 'jours', 1, 365, null],
    ];

    /** @return array<string, array{label: string, icon: string, help: string, unit: string, min: int, max: int, unlimited: ?string}> */
    public static function fields(): array
    {
        $fields = [];
        foreach (self::FIELDS as $key => [$label, $icon, $help, $unit, $min, $max, $unlimited]) {
            $fields[$key] = compact('label', 'icon', 'help', 'unit', 'min', 'max', 'unlimited');
        }

        return $fields;
    }

    /** Regles pour le tableau roles[id][cle] et roles[id][unlimited][cle] du formulaire. */
    public static function rules(): array
    {
        $rules = ['roles' => ['required', 'array']];
        foreach (self::fields() as $key => $field) {
            $range = ['integer', "min:{$field['min']}", "max:{$field['max']}"];
            $rules["roles.*.{$key}"] = $field['unlimited'] === null
                ? ['required', ...$range]
                : ["required_without:roles.*.unlimited.{$key}", 'nullable', ...$range];
            if ($field['unlimited'] !== null) {
                $rules["roles.*.unlimited.{$key}"] = ['nullable', 'boolean'];
            }
        }

        return $rules;
    }

    /** Valeur acceptable telle qu'enregistree en base (UNLIMITED compris) : pour l'import de configuration. */
    public static function accepts(string $key, mixed $value): bool
    {
        $field = self::fields()[$key] ?? null;
        if ($field === null || !is_int($value)) {
            return false;
        }

        return ($value === self::UNLIMITED && $field['unlimited'] !== null) || ($value >= $field['min'] && $value <= $field['max']);
    }

    /** Noms des champs dans les messages d'erreur. */
    public static function attributes(): array
    {
        $attributes = [];
        foreach (self::fields() as $key => $field) {
            $attributes["roles.*.{$key}"] = mb_strtolower($field['label']);
        }

        return $attributes;
    }

    /** Valeurs a enregistrer pour un role a partir de sa partie (validee) du formulaire. */
    public static function fromInput(array $input): array
    {
        $values = [];
        foreach (self::fields() as $key => $field) {
            $unlimited = $field['unlimited'] !== null && !empty($input['unlimited'][$key]);
            $values[$key] = $unlimited ? self::UNLIMITED : (int) $input[$key];
        }

        return $values;
    }
}
