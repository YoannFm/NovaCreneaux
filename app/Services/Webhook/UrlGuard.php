<?php

namespace App\Services\Webhook;

/**
 * Controle l'adresse d'un webhook : https, sans identifiants, et qui ne vise jamais le reseau interne du serveur
 * (SSRF : metadonnees du cloud, base de donnees locale, services prives). L'adresse IP controlee est celle qui sert
 * a l'envoi : le nom n'est pas resolu une seconde fois entre le controle et la connexion.
 * `nova.webhooks.allow_private_targets` leve ces interdits pour un service interne de confiance (http accepte alors).
 */
class UrlGuard
{
    /** Plages non couvertes par les filtres de PHP. */
    private const EXTRA_BLOCKED_V4 = ['100.64.0.0/10', '192.0.0.0/24', '198.18.0.0/15'];

    /** @var (callable(string): list<string>)|null */
    private $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /** @return array{scheme: string, host: string, port: int, ip: string} */
    public function check(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            throw new WebhookException('Adresse invalide.');
        }

        $allowPrivate = (bool) config('nova.webhooks.allow_private_targets');
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, $allowPrivate ? ['https', 'http'] : ['https'], true)) {
            throw new WebhookException("L'adresse doit commencer par https://.");
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new WebhookException("L'adresse ne doit pas contenir d'identifiants.");
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($ips === []) {
            throw new WebhookException("Le nom de domaine « {$host} » ne se résout pas.");
        }
        if (! $allowPrivate) {
            foreach ($ips as $ip) {
                if (! $this->isPublic($ip)) {
                    throw new WebhookException("L'adresse vise le réseau interne du serveur : refusée.");
                }
            }
        }

        return ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if ($this->resolver !== null) {
            return array_values(($this->resolver)($host));
        }

        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }

    private function isPublic(string $ip): bool
    {
        // ::ffff:127.0.0.1 est l'adresse IPv4 127.0.0.1 : on controle celle-ci
        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            $ip = inet_ntop(substr($packed, 12));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        foreach (self::EXTRA_BLOCKED_V4 as $cidr) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $this->inCidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $mask = -1 << (32 - (int) $bits);

        return (ip2long($ip) & $mask) === (ip2long($network) & $mask);
    }
}
