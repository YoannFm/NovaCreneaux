<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Http;

/**
 * Un essai d'envoi. Signature : `X-Nova-Signature: sha256=` + HMAC-SHA256 de "<horodatage>.<corps>" avec le secret,
 * l'horodatage est dans `X-Nova-Timestamp` (le destinataire refuse les envois trop anciens). `X-Nova-Delivery` est
 * identique d'un essai a l'autre : le destinataire peut ignorer un doublon.
 */
class WebhookSender
{
    public const MAX_ATTEMPTS = 5;
    /** Delai (minutes) avant l'essai suivant, apres le 1er, 2e, 3e et 4e echec. */
    private const BACKOFF_MINUTES = [1, 5, 30, 120];
    /** Echecs definitifs consecutifs apres lesquels le webhook est desactive. */
    private const DISABLE_AFTER = 10;

    public function __construct(private UrlGuard $guard, private WebhookRequestBuilder $builder) {}

    public static function signature(string $secret, int $timestamp, string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    /** $retry = false : un seul essai, sans nouveau passage plus tard (bouton de test). */
    public function send(WebhookDelivery $delivery, bool $retry = true): void
    {
        $hook = $delivery->webhook;
        if (! $hook || ! $hook->is_active) {
            $this->finish($delivery, WebhookDelivery::FAILED, null, 'Webhook supprimé ou désactivé.', 0);

            return;
        }

        $started = microtime(true);
        try {
            $request = $this->builder->build($hook, $delivery);
            $target = $this->guard->check($request->url);
        } catch (WebhookException $e) {
            // Adresse devenue interdite (le DNS a change) ou format disparu : inutile de reessayer
            $this->finish($delivery, WebhookDelivery::FAILED, null, $e->getMessage(), 0);
            $this->recordOutcome($hook, false);
            $this->logAbandoned($hook, $delivery, $retry);

            return;
        }

        try {
            $response = $this->post($hook, $delivery, $request, $target);
            $ms = (int) ((microtime(true) - $started) * 1000);
            $response->successful() ? $this->succeed($delivery, $hook, $response->status(), $ms) : $this->fail($delivery, $hook, $response->status(), 'Réponse HTTP ' . $response->status(), $ms, $retry);
        } catch (\Throwable $e) {
            $this->fail($delivery, $hook, null, mb_substr($e->getMessage(), 0, 250), (int) ((microtime(true) - $started) * 1000), $retry);
        }
    }

    private function post(Webhook $hook, WebhookDelivery $delivery, WebhookRequest $request, array $target)
    {
        $timestamp = time();
        $resolve = $target['host'] . ':' . $target['port'] . ':' . (str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip']);

        return Http::withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => [$resolve]]])
            ->connectTimeout(3)->timeout(7)
            ->withHeaders(['User-Agent' => 'NovaCreneaux-Webhook'] + $request->headers + [
                'X-Nova-Event' => $delivery->event,
                'X-Nova-Delivery' => $delivery->uuid,
                'X-Nova-Timestamp' => (string) $timestamp,
                'X-Nova-Signature' => self::signature($hook->secret, $timestamp, $request->body),
            ])
            ->withBody($request->body, $request->contentType)
            ->send($request->method, $request->url);
    }

    private function succeed(WebhookDelivery $delivery, Webhook $hook, int $status, int $ms): void
    {
        $this->finish($delivery, WebhookDelivery::SUCCESS, $status, null, $ms);
        $this->recordOutcome($hook, true);
    }

    private function fail(WebhookDelivery $delivery, Webhook $hook, ?int $status, string $error, int $ms, bool $retry): void
    {
        $attempts = $delivery->attempts + 1;
        if (! $retry || $attempts >= self::MAX_ATTEMPTS) {
            $this->finish($delivery, WebhookDelivery::FAILED, $status, $error, $ms);
            $this->recordOutcome($hook, false);
            $this->logAbandoned($hook, $delivery, $retry);

            return;
        }

        $delivery->forceFill([
            'attempts' => $attempts, 'response_code' => $status, 'error' => $error, 'duration_ms' => $ms,
            'next_attempt_at' => now()->addMinutes(self::BACKOFF_MINUTES[$attempts - 1]),
        ])->save();
    }

    /** Un envoi abandonne apres tous ses essais est visible dans Admin > Activite. Le bouton de test a son propre journal. */
    private function logAbandoned(Webhook $hook, WebhookDelivery $delivery, bool $retry): void
    {
        if ($retry) {
            ActivityLogger::log('Webhook : envoi abandonné', "{$hook->name} : {$delivery->event}, {$delivery->error}");
        }
    }

    private function finish(WebhookDelivery $delivery, string $status, ?int $code, ?string $error, int $ms): void
    {
        $delivery->forceFill([
            'status' => $status, 'attempts' => $delivery->attempts + 1, 'response_code' => $code, 'error' => $error,
            'duration_ms' => $ms, 'next_attempt_at' => null, 'delivered_at' => $status === WebhookDelivery::SUCCESS ? now() : null,
        ])->save();
    }

    /** Mises a jour sans evenement Eloquent : ce suivi ne doit pas remplir le journal d'activite. */
    private function recordOutcome(Webhook $hook, bool $success): void
    {
        $query = Webhook::whereKey($hook->id);
        if ($success) {
            $query->update(['consecutive_failures' => 0, 'last_delivery_at' => now()]);

            return;
        }

        $query->increment('consecutive_failures');
        if ($query->value('consecutive_failures') >= self::DISABLE_AFTER && $hook->is_active) {
            $query->update(['is_active' => false]);
            ActivityLogger::log('Webhook désactivé après des échecs répétés', $hook->name);
        }
    }
}
