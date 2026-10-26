<?php
namespace App\Services;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/** Creation et revocation des jetons d'API : commandes artisan et administration passent par ici. */
class ApiTokenService {
    /** Droits qu'un jeton peut recevoir, avec leur description (affichee dans l'administration). */
    public const ABILITIES = [
        'resources:read' => 'Consulter les ressources, créneaux, disponibilités et règles',
        'reservations:read' => 'Consulter ses réservations',
        'reservations:write' => 'Créer et annuler ses réservations',
    ];

    /** @param list<string> $abilities */
    public function create(User $user, string $name, array $abilities, ?int $days = null): NewAccessToken {
        $unknown = array_diff($abilities, array_keys(self::ABILITIES));
        if ($abilities === [] || $unknown !== []) {
            throw new \InvalidArgumentException('Droit inconnu : ' . implode(', ', $unknown ?: ['aucun']) . '. Droits possibles : ' . implode(', ', array_keys(self::ABILITIES)));
        }
        if (!$user->is_active) {
            throw new \InvalidArgumentException('Ce compte est désactivé.');
        }

        $token = $user->createToken($name, array_values($abilities), $days ? now()->addDays($days) : null);
        ActivityLogger::log('Jeton API créé', "#{$token->accessToken->id} {$name} pour {$user->email} (" . implode(', ', $abilities) . ')');

        return $token;
    }

    public function revoke(PersonalAccessToken $token): void {
        $token->delete();
        ActivityLogger::log('Jeton API révoqué', "#{$token->id} {$token->name}");
    }
}
