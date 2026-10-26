<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Parametres > Securite : les administrateurs doivent avoir une double authentification.
 * Ils sont redirigés vers leur profil pour la configurer plutot que bloques, afin de ne jamais s'enfermer dehors.
 */
class EnforceAdminTwoFactor
{
    private const ALWAYS_OPEN = ['up', 'logout', 'profile', 'profile/*', 'mfa/*'];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (setting('require_2fa_admin', '0') === '1'
            && $user?->isAdmin()
            && !$user->hasAnyMfa()
            && !$request->is(...self::ALWAYS_OPEN)) {
            return redirect()->route('profile.edit')
                ->with('error', 'La double authentification est obligatoire pour les administrateurs. Activez-la pour continuer.');
        }

        return $next($request);
    }
}
