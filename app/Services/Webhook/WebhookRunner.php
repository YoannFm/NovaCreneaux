<?php

namespace App\Services\Webhook;

use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Cache;

/** Envoie les webhooks dus, dans la limite d'un budget de temps (planificateur : 50 s, apres une requete : quelques secondes). */
class WebhookRunner
{
    private const KEEP_DAYS = 30;

    public function __construct(private WebhookSender $sender) {}

    /** @return int nombre d'envois tentes */
    public function run(int $budgetSeconds, int $max = 100): int
    {
        $lock = Cache::lock('webhooks-run', $budgetSeconds + 60);
        if (! $lock->get()) {
            return 0;
        }

        try {
            $deadline = microtime(true) + $budgetSeconds;
            $attempted = 0;
            $due = WebhookDelivery::with('webhook')->where('status', WebhookDelivery::PENDING)
                ->where('next_attempt_at', '<=', now())->orderBy('id')->limit($max)->get();
            foreach ($due as $delivery) {
                if (microtime(true) >= $deadline) {
                    break;
                }
                $this->sender->send($delivery);
                $attempted++;
            }

            if (! WebhookDelivery::where('status', WebhookDelivery::PENDING)->exists()) {
                Cache::forget(WebhookDispatcher::PENDING_FLAG);
            }
            $this->prune();

            return $attempted;
        } finally {
            $lock->release();
        }
    }

    /** Une fois par jour : l'historique des envois n'est garde que quelques semaines. */
    private function prune(): void
    {
        if (Cache::add('webhooks_pruned', 1, 86400)) {
            WebhookDelivery::where('created_at', '<', now()->subDays(self::KEEP_DAYS))->where('status', '!=', WebhookDelivery::PENDING)->delete();
        }
    }
}
