<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Requetes d'API refusees pour une raison de politique (API coupee, role sans acces, compte desactive, jeton invalide),
 * a lire dans Admin > Activite. Une meme cause pour un meme jeton (ou une meme adresse IP) n'est inscrite qu'une fois
 * toutes les 10 minutes : un client mal configure qui reessaie en boucle ne remplit pas le journal.
 * Le jeton lui-meme n'est jamais inscrit, seulement son numero.
 */
class ApiAccessLog
{
    private const WINDOW_SECONDS = 600;

    public static function denied(string $code, Request $request): void
    {
        $tokenId = data_get($request->user()?->currentAccessToken(), 'id');
        $subject = $tokenId !== null ? "jeton #{$tokenId}" : 'IP ' . $request->ip();

        if (! Cache::add("api-denied:{$code}:{$subject}", 1, self::WINDOW_SECONDS)) {
            return;
        }

        ActivityLogger::log('Requête API refusée', "{$code} : {$request->method()} /{$request->path()} ({$subject})");
    }
}
