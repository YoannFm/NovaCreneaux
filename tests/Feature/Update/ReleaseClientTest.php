<?php

namespace Tests\Feature\Update;

use App\Services\Update\ReleaseClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReleaseClientTest extends TestCase
{
    private const BASE = 'https://github.com/Owner/Repo/releases/download/v1.2.0/';

    protected function setUp(): void
    {
        parent::setUp();
        config(['nova.repo' => 'Owner/Repo']);
        Cache::forget('update_check');
    }

    private function api(array $overrides = []): array
    {
        return array_replace_recursive([
            'tag_name' => 'v1.2.0',
            'body' => 'Corrections et nouveautes',
            'published_at' => '2026-10-26T10:00:00Z',
            'assets' => [
                ['name' => 'novacreneaux-1.2.0.zip', 'size' => 1234, 'browser_download_url' => self::BASE . 'novacreneaux-1.2.0.zip'],
                ['name' => 'novacreneaux-1.2.0.zip.sig', 'size' => 88, 'browser_download_url' => self::BASE . 'novacreneaux-1.2.0.zip.sig'],
            ],
        ], $overrides);
    }

    public function test_the_latest_release_is_parsed(): void
    {
        Http::fake(['api.github.com/*' => Http::response($this->api())]);

        $result = app(ReleaseClient::class)->check();

        $this->assertNull($result['error']);
        $this->assertSame('1.2.0', $result['release']['version']);
        $this->assertSame('v1.2.0', $result['release']['tag']);
        $this->assertSame('Corrections et nouveautes', $result['release']['changelog']);
        $this->assertSame(1234, $result['release']['zip']['size']);
        $this->assertSame(self::BASE . 'novacreneaux-1.2.0.zip.sig', $result['release']['signature']['url']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/Owner/Repo/releases/latest'
            && $request->header('User-Agent')[0] === 'NovaCreneaux-updater');
    }

    public function test_nothing_but_the_request_is_sent_to_github(): void
    {
        Http::fake(['api.github.com/*' => Http::response($this->api())]);

        app(ReleaseClient::class)->check();

        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->body() === '' && !str_contains($request->url(), '?'));
    }

    public function test_no_release_yet_is_not_an_error(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

        $result = app(ReleaseClient::class)->check();

        $this->assertNull($result['release']);
        $this->assertNull($result['error']);
    }

    public function test_server_errors_and_network_failures_become_messages(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 503)]);
        $this->assertStringContainsString('503', app(ReleaseClient::class)->check(true)['error']);

        Http::fake(['api.github.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $this->assertStringContainsString('Impossible de joindre GitHub', app(ReleaseClient::class)->check(true)['error']);
    }

    public function test_a_tag_that_is_not_a_version_is_ignored(): void
    {
        Http::fake(['api.github.com/*' => Http::response($this->api(['tag_name' => 'nightly']))]);

        $this->assertNull(app(ReleaseClient::class)->check()['release']);
    }

    public function test_a_release_without_the_signed_archive_is_an_error(): void
    {
        Http::fake(['api.github.com/*' => Http::response($this->api(['assets' => [1 => ['name' => 'autre.txt']]]))]);

        $result = app(ReleaseClient::class)->check();

        $this->assertNull($result['release']);
        $this->assertStringContainsString("ne contient pas l'archive signée", $result['error']);
    }

    public function test_assets_hosted_outside_the_release_are_ignored(): void
    {
        $evil = $this->api();
        $evil['assets'][0]['browser_download_url'] = 'https://evil.example/novacreneaux-1.2.0.zip';
        Http::fake(['api.github.com/*' => Http::response($evil)]);

        $result = app(ReleaseClient::class)->check();

        $this->assertNull($result['release']);
        $this->assertNotNull($result['error']);
    }

    public function test_results_are_cached_and_can_be_forced(): void
    {
        Http::fake(['api.github.com/*' => Http::response($this->api())]);
        $client = app(ReleaseClient::class);

        $client->check();
        $client->check();
        Http::assertSentCount(1);
        $this->assertSame('1.2.0', $client->cached()['release']['version']);

        $client->check(true);
        Http::assertSentCount(2);
    }

    public function test_the_changelog_is_truncated(): void
    {
        Http::fake(['api.github.com/*' => Http::response($this->api(['body' => str_repeat('a', 30000)]))]);

        $this->assertSame(20000, mb_strlen(app(ReleaseClient::class)->check()['release']['changelog']));
    }
}
