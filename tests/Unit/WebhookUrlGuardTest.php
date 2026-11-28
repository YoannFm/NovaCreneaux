<?php

namespace Tests\Unit;

use App\Services\Webhook\UrlGuard;
use App\Services\Webhook\WebhookException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebhookUrlGuardTest extends TestCase
{
    private function guard(array $dns = []): UrlGuard
    {
        $records = $dns + ['hooks.example.com' => ['93.184.216.34'], 'interne.example.com' => ['10.0.0.5'], 'mixte.example.com' => ['93.184.216.34', '192.168.1.10'], 'vide.example.com' => []];

        return new UrlGuard(fn (string $host) => $records[$host] ?? []);
    }

    public function test_a_public_https_address_is_accepted_and_returns_the_ip_to_connect_to(): void
    {
        $target = $this->guard()->check('https://hooks.example.com/webhooks/nova?x=1');

        $this->assertSame(['scheme' => 'https', 'host' => 'hooks.example.com', 'port' => 443, 'ip' => '93.184.216.34'], $target);
        $this->assertSame(8443, $this->guard()->check('https://hooks.example.com:8443/x')['port']);
        $this->assertSame('93.184.216.34', $this->guard()->check('https://93.184.216.34/x')['ip']);
    }

    #[DataProvider('forbiddenUrls')]
    public function test_forbidden_addresses_are_refused(string $url, string $message): void
    {
        $this->expectException(WebhookException::class);
        $this->expectExceptionMessage($message);

        $this->guard()->check($url);
    }

    public static function forbiddenUrls(): array
    {
        $internal = 'réseau interne';

        return [
            'http' => ['http://hooks.example.com/x', 'https://'],
            'ftp' => ['ftp://hooks.example.com/x', 'https://'],
            'sans schema' => ['hooks.example.com/x', 'invalide'],
            'identifiants' => ['https://user:pass@hooks.example.com/x', 'identifiants'],
            'localhost ip' => ['https://127.0.0.1/x', $internal],
            'loopback 127.x' => ['https://127.1.2.3/x', $internal],
            'prive 10.x' => ['https://10.1.2.3/x', $internal],
            'prive 192.168' => ['https://192.168.0.1/x', $internal],
            'prive 172.16' => ['https://172.16.5.5/x', $internal],
            'metadonnees cloud' => ['https://169.254.169.254/latest/meta-data', $internal],
            'cgnat' => ['https://100.64.0.1/x', $internal],
            'ipv6 loopback' => ['https://[::1]/x', $internal],
            'ipv6 prive' => ['https://[fd00::1]/x', $internal],
            'ipv4 mappee' => ['https://[::ffff:127.0.0.1]/x', $internal],
            'ipv4 mappee prive' => ['https://[::ffff:10.0.0.1]/x', $internal],
            'zero' => ['https://0.0.0.0/x', $internal],
            'nom vers prive' => ['https://interne.example.com/x', $internal],
            'nom mixte' => ['https://mixte.example.com/x', $internal],
            'nom inconnu' => ['https://vide.example.com/x', 'ne se résout pas'],
        ];
    }

    public function test_the_configuration_can_allow_private_targets_for_a_trusted_internal_service(): void
    {
        config(['nova.webhooks.allow_private_targets' => true]);

        $this->assertSame('10.0.0.5', $this->guard()->check('http://interne.example.com:8080/x')['ip']);
        $this->assertSame('127.0.0.1', $this->guard()->check('https://127.0.0.1/x')['ip']);
    }
}
