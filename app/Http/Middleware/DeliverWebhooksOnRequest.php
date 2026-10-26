<?php

namespace App\Http\Middleware;

use App\Services\Webhook\WebhookDispatcher;
use App\Services\Webhook\WebhookRunner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filet de securite sans cron : apres l'envoi de la reponse, envoie les webhooks en attente (une verification par
 * 10 secondes au plus, quelques secondes de travail). Avec le cron, le planificateur s'en charge.
 */
class DeliverWebhooksOnRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! config('nova.webhooks.on_request') || ! Cache::get(WebhookDispatcher::PENDING_FLAG) || ! Cache::add('webhooks_check', 1, 10)) {
                return;
            }

            @set_time_limit(60);
            app(WebhookRunner::class)->run(8, 10);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
