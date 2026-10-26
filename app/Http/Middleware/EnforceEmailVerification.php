<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Parametres > Securite : les utilisateurs doivent confirmer leur adresse e-mail (les administrateurs en sont dispenses). */
class EnforceEmailVerification
{
    private const ALWAYS_OPEN = ['up', 'logout', 'verify-email', 'verify-email/*', 'email/verification-notification'];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (setting('force_email_verification', '0') === '1'
            && $user
            && !$user->isAdmin()
            && !$user->hasVerifiedEmail()
            && !$request->is(...self::ALWAYS_OPEN)) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
