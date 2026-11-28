<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use App\Models\WebhookDelivery;

/**
 * Construit la requete d'un envoi selon le format du webhook : charge utile standard, prereglage du catalogue ou
 * format personnalise. Les trois passent par le meme moteur de modeles (TemplateRenderer).
 */
class WebhookRequestBuilder
{
    public const PAYLOAD_BODY = '@payload';

    private const CONTENT_TYPES = [
        'json' => 'application/json',
        'form' => 'application/x-www-form-urlencoded',
        'text' => 'text/plain; charset=utf-8',
    ];

    public function __construct(private PresetCatalog $catalog) {}

    public function build(Webhook $hook, WebhookDelivery $delivery): WebhookRequest
    {
        if ($hook->format === Webhook::FORMAT_JSON) {
            return new WebhookRequest('POST', $hook->url, $delivery->payload, self::CONTENT_TYPES['json']);
        }

        $spec = $this->spec($hook);
        $fields = $hook->settings['fields'] ?? [];
        $variables = $this->escaped(
            EventMessage::variables(json_decode($delivery->payload, true), $hook->show_names, setting('app_name', 'NovaCreneaux'), $delivery->uuid),
            $spec['escape'] ?? null,
        ) + $fields;

        $body = $spec['body'];
        $contentType = $spec['content_type'];
        if ($body === self::PAYLOAD_BODY) {
            $encoded = $delivery->payload;
            $contentType = 'json';
        } else {
            $encoded = $this->encode(TemplateRenderer::render($body, $variables), $contentType);
        }

        return new WebhookRequest(
            $spec['method'],
            $spec['url_template'] === null ? $hook->url : $this->presetUrl($spec, $fields, $delivery->uuid),
            $encoded,
            self::CONTENT_TYPES[$contentType],
            $this->headers($spec['headers'] ?? [], $variables),
        );
    }

    /** @return array{method: string, content_type: string, body: mixed, headers: array<string, string>, url_template: ?string, escape: ?string} */
    private function spec(Webhook $hook): array
    {
        if ($hook->format === Webhook::FORMAT_PRESET) {
            $preset = $this->catalog->find($hook->preset) ?? throw new WebhookException("Le préréglage « {$hook->preset} » n'existe plus.");

            return $preset + ['headers' => [], 'url_template' => null, 'escape' => null];
        }

        $custom = $hook->settings['custom'] ?? [];
        $contentType = $custom['content_type'] ?? 'json';

        return [
            'method' => $custom['method'] ?? 'POST',
            'content_type' => $contentType,
            'body' => $contentType === 'text' ? (string) ($custom['body'] ?? '') : (json_decode((string) ($custom['body'] ?? ''), true) ?? []),
            'headers' => $custom['headers'] ?? [],
            'url_template' => null,
            'escape' => null,
        ];
    }

    /** Adresse d'un prereglage a modele : champs encodes pour une adresse, sauf ceux de type adresse (deja une adresse). */
    public function presetUrl(array $preset, array $fields, string $deliveryId): string
    {
        $types = array_column($preset['fields'] ?? [], 'type', 'key');
        $parts = ['delivery_id' => rawurlencode($deliveryId)];
        foreach ($fields as $key => $value) {
            $parts[$key] = ($types[$key] ?? 'text') === 'url' ? rtrim((string) $value, '/') : rawurlencode((string) $value);
        }

        return TemplateRenderer::render($preset['url_template'], $parts);
    }

    private function encode(mixed $rendered, string $contentType): string
    {
        return match ($contentType) {
            'form' => http_build_query($rendered, '', '&', PHP_QUERY_RFC3986),
            'text' => (string) $rendered,
            default => json_encode($rendered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }

    /** @return array<string, string> */
    private function headers(array $templates, array $variables): array
    {
        $headers = [];
        foreach ($templates as $name => $template) {
            // Un retour a la ligne dans une valeur permettrait d'ajouter un en-tete
            $headers[$name] = str_replace(["\r", "\n"], ' ', (string) TemplateRenderer::render($template, $variables));
        }

        return $headers;
    }

    /** Slack lit &, < et > comme du balisage (<!channel> notifie tout le monde) : le texte venu du site les perd. */
    private function escaped(array $variables, ?string $mode): array
    {
        if ($mode !== 'slack') {
            return $variables;
        }

        return array_map(fn ($value) => is_string($value) ? strtr($value, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']) : $value, $variables);
    }
}
