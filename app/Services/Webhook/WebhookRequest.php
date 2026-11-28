<?php

namespace App\Services\Webhook;

/** Requete HTTP prete a partir : ce que le destinataire d'un webhook va recevoir. */
final class WebhookRequest
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly string $body,
        public readonly string $contentType,
        public readonly array $headers = [],
    ) {}
}
