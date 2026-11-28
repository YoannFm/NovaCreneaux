<?php
namespace App\Services;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/** Creation et revocation des jetons d'API : commandes artisan, administration et profil passent par ici. */
class ApiTokenService {
    /** Droits qu'un jeton peut recevoir, avec leur description (affichee dans l'administration). */
    public const ABILITIES = [
        'resources:read' => 'Consulter les ressources, créneaux, disponibilités et règles',
        'reservations:read' => 'Consulter ses réservations',
        'reservations:write' => 'Créer et annuler ses réservations',
    ];

    /** Jetons personnels : validites proposees (jours) et nombre de jetons non expires par compte. */
    public const PERSONAL_DURATIONS = [30, 90, 365];
    public const PERSONAL_LIMIT = 5;

    /** Interrupteur general de Admin > Integrations API : coupe l'API pour tout le monde sans toucher aux jetons. */
    public static function enabled(): bool {
        return setting('api_enabled', '1') === '1';
    }

    /** @param list<string> $abilities */
    public function create(User $user, string $name, array $abilities, ?int $days = null): NewAccessToken {
        $unknown = array_diff($abilities, array_keys(self::ABILITIES));
        if ($abilities === [] || $unknown !== []) {
            throw new \InvalidArgumentException('Droit inconnu : ' . implode(', ', $unknown ?: ['aucun']) . '. Droits possibles : ' . implode(', ', array_keys(self::ABILITIES)));
        }
        if (!$user->is_active) {
            throw new \InvalidArgumentException('Ce compte est désactivé.');
        }
        if (!self::enabled()) {
            throw new \InvalidArgumentException("L'API est désactivée par un administrateur.");
        }
        if (!$user->canUseApi()) {
            throw new \InvalidArgumentException("Le rôle de ce compte n'a pas accès à l'API.");
        }

        $token = $user->createToken($name, array_values($abilities), $days ? now()->addDays($days) : null);
        ActivityLogger::log('Jeton API créé', "#{$token->accessToken->id} {$name} pour {$user->email} (" . implode(', ', $abilities) . ')');

        return $token;
    }

    /** Jeton demande par l'utilisateur lui-meme : toujours a duree limitee, en nombre limite. */
    public function createPersonal(User $user, string $name, array $abilities, int $days): NewAccessToken {
        if (!in_array($days, self::PERSONAL_DURATIONS, true)) {
            throw new \InvalidArgumentException('Durée de validité non proposée.');
        }
        if ($this->activeTokens($user)->count() >= self::PERSONAL_LIMIT) {
            throw new \InvalidArgumentException('Vous avez déjà ' . self::PERSONAL_LIMIT . ' jetons actifs : révoquez-en un avant d\'en créer un autre.');
        }

        return $this->create($user, $name, $abilities, $days);
    }

    /** Jetons du compte qui n'ont pas expire. */
    public function activeTokens(User $user) {
        return $user->tokens()->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function revoke(PersonalAccessToken $token): void {
        $token->delete();
        ActivityLogger::log('Jeton API révoqué', "#{$token->id} {$token->name}");
    }
}
