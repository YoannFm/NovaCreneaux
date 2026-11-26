<?php

namespace App\Http\Middleware;

use App\Http\ApiError;
use Closure;
use Illuminate\Http\Request;

/** Parametres > Maintenance : seuls les administrateurs accedent au site, la connexion reste possible. */
class EnforceMaintenanceMode
{
    private const ALWAYS_OPEN = ['up', 'login', 'logout', 'mfa/*', 'forgot-password', 'reset-password/*', 'install*'];

    public function handle(Request $request, Closure $next)
    {
        // Les pages toujours ouvertes passent avant toute lecture en base : l'assistant d'installation doit
        // s'afficher quand la base est vide ou injoignable
        if ($request->is(...self::ALWAYS_OPEN)
            || setting('maintenance_mode', '0') !== '1'
            || $request->user()?->hasAdminAccess()) {
            return $next($request);
        }

        $message = trim(html_entity_decode(strip_tags((string) setting('maintenance_message', ''))));

        if ($request->is('api/*')) {
            return ApiError::response('maintenance', $message !== '' ? $message : 'Le service est temporairement indisponible.', 503);
        }

        return response()->view('errors.maintenance', ['message' => $message], 503);
    }
}
