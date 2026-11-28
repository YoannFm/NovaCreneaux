<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\Middleware\AuthenticateSession;

/**
 * Change le mot de passe d'un compte : ses autres sessions (appareil perdu, session volee) sont fermees a leur prochaine requete.
 * Les requetes web utilisent toujours la garde "web", meme si une requete d'API a change la garde par defaut du processus.
 */
class EndSessionsOnPasswordChange extends AuthenticateSession
{
    public function handle($request, Closure $next)
    {
        $this->auth->shouldUse('web');

        return parent::handle($request, $next);
    }
}
