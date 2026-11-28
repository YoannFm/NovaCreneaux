<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Met le champ "email" des formulaires en minuscules avant la validation : la regle "adresse deja utilisee",
 * la connexion et le mot de passe oublie comparent ainsi la meme forme que celle enregistree (User::email).
 */
class NormalizeEmailInput
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => User::normalizeEmail($request->input('email'))]);
        }

        return $next($request);
    }
}
