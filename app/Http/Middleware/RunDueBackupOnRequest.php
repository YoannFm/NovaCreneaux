<?php

namespace App\Http\Middleware;

use App\Services\Backup\AutomaticBackup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filet de securite sans cron : apres l'envoi de la reponse, lance la sauvegarde si elle est due
 * (au plus une verification par minute). Avec le cron, le planificateur passe avant et ce filet ne fait rien.
 */
class RunDueBackupOnRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if ($request->is('install*') || !config('nova.backups.on_request') || !Cache::add('backup_due_check', 1, 60)) {
                return;
            }

            @set_time_limit(300);
            app(AutomaticBackup::class)->runIfDue();
        } catch (\Throwable $e) {
            // Site pas encore installe, base ou cache indisponible : ne jamais gener la requete, et ne pas remplir
            // les logs d'une meme panne a chaque minute (une fois par heure au plus)
            if (rescue(fn () => Cache::add('backup_on_request_error', 1, 3600), true, false)) {
                report($e);
            }
        }
    }
}
