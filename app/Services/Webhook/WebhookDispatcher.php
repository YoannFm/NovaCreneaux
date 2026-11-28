<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Met en file les envois (table webhook_deliveries). L'envoi lui-meme se fait hors de la requete : WebhookRunner,
 * lance par le planificateur ou, a defaut, apres la reponse de la requete suivante. Ne leve jamais d'exception.
 */
class WebhookDispatcher
{
    public const PENDING_FLAG = 'webhooks_pending';

    public function dispatch(string $event, array $data): void
    {
        try {
            $hooks = Webhook::where('is_active', true)->get()->filter(fn (Webhook $hook) => $hook->subscribesTo($event));
            foreach ($hooks as $hook) {
                $this->enqueue($hook, $event, $data);
            }
        } catch (\Throwable $e) {
            // Table absente (mise a jour en cours) ou base indisponible : l'operation du site ne doit pas echouer pour autant
            report($e);
        }
    }

    public function enqueue(Webhook $hook, string $event, array $data): WebhookDelivery
    {
        $uuid = (string) Str::uuid();
        $payload = json_encode(['id' => $uuid, 'event' => $event, 'created_at' => now()->toIso8601String(), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $hook->id, 'uuid' => $uuid, 'event' => $event, 'payload' => $payload,
            'status' => WebhookDelivery::PENDING, 'next_attempt_at' => now(), 'created_at' => now(),
        ]);
        Cache::forever(self::PENDING_FLAG, true);

        return $delivery;
    }
}
