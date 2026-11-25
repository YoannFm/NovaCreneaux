<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Detail d'une entree du journal d'activite : champ => ['old' => avant, 'new' => apres].
 * Creation : seulement 'new' ; suppression : seulement 'old'. Un secret n'est jamais ecrit : il apparait
 * comme "(masqué)" des deux cotes, ce qui dit qu'il a change sans rien reveler.
 */
final class ActivityChanges
{
    public const MASKED = '(masqué)';

    /** Champs jamais journalises (techniques ou secrets), en plus du $hidden de chaque modele. */
    private const IGNORED = ['id', 'created_at', 'updated_at', 'remember_token'];
    private const SECRET = ['password', 'secret', 'settings', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Libelles des champs courants ; un champ absent s'affiche sous son nom technique. */
    private const LABELS = [
        'name' => 'Nom', 'label' => 'Libellé', 'title' => 'Titre', 'description' => 'Description', 'email' => 'Adresse e-mail',
        'classe' => 'Classe', 'role' => 'Rôle', 'is_active' => 'Actif', 'is_enabled' => 'Activée', 'is_admin' => 'Administrateur',
        'is_restricted' => 'Réservée aux comptes connectés', 'open_time' => "Ouverture d'un nouveau jour", 'close_time' => 'Fermeture (jour même)',
        'closed_days' => 'Jours fermés', 'start_time' => 'Début', 'end_time' => 'Fin', 'max_capacity' => 'Places',
        'resource_id' => 'Ressource', 'color' => 'Couleur', 'icon' => 'Icône', 'permissions' => 'Permissions', 'api_access' => "Accès à l'API",
        'max_reservations_day' => 'Réservations par jour', 'max_reservations_week' => 'Réservations par semaine',
        'cancel_deadline_minutes' => "Délai d'annulation (min)", 'advance_days' => "Réservation à l'avance (jours)",
        'slug' => 'Adresse', 'content' => 'Contenu', 'url' => 'Adresse', 'events' => 'Événements', 'format' => 'Format',
        'preset' => 'Service', 'show_names' => 'Afficher les noms', 'password' => 'Mot de passe',
    ];

    private const MAX_LENGTH = 2000;

    /** Changements d'un modele a l'evenement Eloquent donne (created, updated, deleted). */
    public static function fromModel(Model $model, string $event): array
    {
        $hidden = array_merge(self::IGNORED, $model->getHidden());
        $keys = $event === 'updated' ? array_keys($model->getChanges()) : array_keys($model->getAttributes());

        $changes = [];
        foreach (array_diff($keys, $hidden) as $key) {
            $changes[$key] = match ($event) {
                'created' => ['new' => self::normalize($model->getAttribute($key))],
                'deleted' => ['old' => self::normalize($model->getAttribute($key))],
                default => ['old' => self::normalize($model->getOriginal($key)), 'new' => self::normalize($model->getAttribute($key))],
            };
        }
        foreach (array_intersect($keys, $model->getHidden()) as $key) {
            if ($event === 'updated') {
                $changes[$key] = ['old' => self::MASKED, 'new' => self::MASKED];
            }
        }

        return $changes;
    }

    /**
     * Difference entre deux etats (reglages, fiche d'un compte) : seulement les valeurs qui ont change.
     * @param list<string> $secretKeys champs dont la valeur n'est jamais ecrite
     */
    public static function diff(array $before, array $after, array $secretKeys = []): array
    {
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $old = self::normalize($before[$key] ?? null);
            $new = self::normalize($after[$key] ?? null);
            if ($old === $new) {
                continue;
            }
            $secret = in_array($key, $secretKeys, true) || in_array($key, self::SECRET, true);
            $changes[$key] = $secret ? ['old' => self::MASKED, 'new' => self::MASKED] : ['old' => $old, 'new' => $new];
        }

        return $changes;
    }

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? $key;
    }

    /** Valeur lisible dans le detail du journal. */
    public static function display(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '(vide)',
            $value === true => 'oui',
            $value === false => 'non',
            is_array($value) => $value === [] ? '(aucun)' : implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $value)),
            default => (string) $value,
        };
    }

    /** Valeur stockable en JSON, de taille bornee (un contenu de page peut etre long). */
    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/Y H:i');
        }
        if (is_string($value) && mb_strlen($value) > self::MAX_LENGTH) {
            return mb_substr($value, 0, self::MAX_LENGTH) . '…';
        }

        return is_scalar($value) || is_array($value) || $value === null ? $value : (string) $value;
    }
}
