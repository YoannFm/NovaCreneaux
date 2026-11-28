<?php

namespace App\Console\Commands;

use App\Services\ApiTokenService;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenRevoke extends Command
{
    protected $signature = 'api:token:revoke {id : Identifiant du jeton (voir api:token:list)}';

    protected $description = 'Revoque un jeton d\'API : il cesse de fonctionner immediatement';

    public function handle(ApiTokenService $tokens): int
    {
        $token = PersonalAccessToken::find($this->argument('id'));
        if (!$token) {
            $this->error('Jeton introuvable.');
            return self::FAILURE;
        }

        $tokens->revoke($token);
        $this->info("Jeton #{$token->id} revoque.");

        return self::SUCCESS;
    }
}
