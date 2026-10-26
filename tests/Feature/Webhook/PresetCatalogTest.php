<?php

namespace Tests\Feature\Webhook;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Webhook\PresetCatalog;
use App\Services\Webhook\WebhookRequestBuilder;
use App\Support\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Garde-fou du catalogue : un fichier de prereglage casse ne doit jamais partir en production. */
class PresetCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_offers_many_services_and_each_file_matches_its_id(): void
    {
        $catalog = new PresetCatalog();

        $this->assertGreaterThanOrEqual(25, count($catalog->all()));
        $files = array_map(fn ($path) => basename($path, '.json'), glob(resource_path('webhook-presets/*.json')));
        $this->assertEqualsCanonicalizing($files, array_keys($catalog->all()), 'un fichier JSON invalide ou mal nomme est ignore');
        foreach (['discord', 'slack', 'teams', 'n8n', 'zapier', 'telegram'] as $id) {
            $this->assertNotNull($catalog->find($id), $id);
        }
    }

    public function test_every_preset_file_is_valid(): void
    {
        $catalog = new PresetCatalog();

        foreach ($catalog->all() as $id => $preset) {
            $this->assertSame([], $catalog->problems($preset, $id), "prereglage {$id}");
        }
    }

    public function test_every_preset_builds_a_valid_request_for_every_event(): void
    {
        $catalog = new PresetCatalog();
        $builder = app(WebhookRequestBuilder::class);
        $payloads = [
            WebhookEvent::RESERVATION_CREATED => ['reservation' => ['id' => 1, 'date' => '2026-10-27', 'slot' => ['id' => 1, 'name' => '11h55 - 12h50', 'start_time' => '11:55:00', 'end_time' => '12:50:00'], 'resource' => ['id' => 1, 'name' => 'CDI']], 'user' => ['id' => 2, 'name' => 'Léa', 'classe' => '2A', 'role' => 'user']],
            WebhookEvent::USER_DELETED => ['user' => ['id' => 2, 'name' => 'Léa', 'classe' => null, 'role' => 'user'], 'deleted_by' => 'admin'],
            WebhookEvent::PING => ['message' => 'Test'],
        ];

        foreach ($catalog->all() as $id => $preset) {
            $fields = [];
            foreach ($preset['fields'] ?? [] as $field) {
                $fields[$field['key']] = ($field['type'] ?? 'text') === 'url' ? 'https://serveur.example.com' : 'valeur-' . $field['key'];
            }
            $hook = new Webhook(['url' => 'https://hooks.example.com/x', 'format' => 'preset', 'preset' => $id, 'settings' => ['fields' => $fields], 'show_names' => true]);

            foreach ($payloads as $event => $data) {
                $delivery = new WebhookDelivery(['uuid' => 'uuid-1', 'event' => $event, 'payload' => json_encode(['id' => 'uuid-1', 'event' => $event, 'created_at' => '2026-10-27T10:00:00+01:00', 'data' => $data])]);
                $request = $builder->build($hook, $delivery);

                $this->assertContains($request->method, PresetCatalog::METHODS, $id);
                $this->assertStringStartsWith('https://', $request->url, $id);
                $this->assertStringNotContainsString('{{', $request->body . $request->url . implode('', $request->headers), "variable non remplacee dans {$id}");
                if (str_starts_with($request->contentType, 'application/json')) {
                    $this->assertIsArray(json_decode($request->body, true), "JSON invalide pour {$id}");
                }
                $this->assertNotSame('', trim($request->body), $id);
            }
        }
    }

    public function test_host_allowlists_accept_subdomains_only_where_declared(): void
    {
        $preset = ['hosts' => ['hooks.slack.com', '*.make.com']];

        $this->assertTrue(PresetCatalog::allowsHost($preset, 'https://hooks.slack.com/services/x'));
        $this->assertTrue(PresetCatalog::allowsHost($preset, 'https://hook.eu1.make.com/abc'));
        $this->assertFalse(PresetCatalog::allowsHost($preset, 'https://evil-hooks.slack.com.example.net/x'));
        $this->assertFalse(PresetCatalog::allowsHost($preset, 'https://make.com.example.net/x'));
        $this->assertTrue(PresetCatalog::allowsHost([], 'https://nimporte.example.com'));
    }

    public function test_the_validator_catches_broken_files(): void
    {
        $catalog = new PresetCatalog();
        $broken = ['id' => 'casse', 'name' => 'Cassé', 'category' => 'X', 'description' => 'y', 'method' => 'DELETE', 'content_type' => 'json', 'body' => ['a' => '{{ inconnue }}'], 'fields' => [['key' => 'title', 'label' => 'x']]];

        $problems = implode(' | ', $catalog->problems($broken, 'casse'));

        $this->assertStringContainsString('Méthode invalide', $problems);
        $this->assertStringContainsString('Variable inconnue : {{ inconnue }}', $problems);
        $this->assertStringContainsString('déjà pris par une variable', $problems);
        $this->assertNotSame([], $catalog->problems(['id' => 'json'] + $broken, 'json'), 'json et custom sont reserves');
    }
}
