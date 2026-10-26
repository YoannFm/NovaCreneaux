<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Console\Command;

class ApiTokenCreate extends Command
{
    protected $signature = 'api:token:create
        {email : E-mail du compte de service auquel appartient le jeton}
        {name : Nom de l\'integration (ex. borne CDI)}
        {--ability=* : Droit du jeton (resources:read, reservations:read, reservations:write), tous les droits de lecture par defaut}
        {--days= : Duree de validite en jours (1 an par defaut)}';

    protected $description = 'Cree un jeton d\'API pour un compte de service';

    public function handle(ApiTokenService $tokens): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('Aucun compte avec cet e-mail.');
            return self::FAILURE;
        }

        $days = $this->option('days');
        if ($days !== null && (!ctype_digit((string) $days) || (int) $days < 1)) {
            $this->error('--days doit etre un entier positif.');
            return self::FAILURE;
        }

        try {
            $token = $tokens->create(
                $user,
                $this->argument('name'),
                $this->option('ability') ?: ['resources:read', 'reservations:read'],
                $days ? (int) $days : null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->warn('Ce compte est administrateur : preferez un compte de service dedie, sans droits admin.');
        }
        $this->info("Jeton #{$token->accessToken->id} cree. Copiez-le maintenant, il ne sera plus affiche :");
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
