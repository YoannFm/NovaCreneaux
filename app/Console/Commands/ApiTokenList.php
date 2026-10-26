<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenList extends Command
{
    protected $signature = 'api:token:list';

    protected $description = 'Liste les jetons d\'API (le secret n\'est jamais affiche)';

    public function handle(): int
    {
        $rows = PersonalAccessToken::with('tokenable')->orderBy('id')->get()->map(fn ($token) => [
            $token->id,
            $token->tokenable?->email ?? '(compte supprime)',
            $token->name,
            implode(', ', $token->abilities ?? []),
            $token->last_used_at?->format('d/m/Y H:i') ?? 'jamais',
            $token->expires_at?->format('d/m/Y') ?? 'defaut',
        ]);

        $this->table(['ID', 'Compte', 'Nom', 'Droits', 'Derniere utilisation', 'Expire le'], $rows);

        return self::SUCCESS;
    }
}
