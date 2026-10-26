<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\ApiTokenService;
use App\Services\Webhook\UrlGuard;
use App\Services\Webhook\WebhookDispatcher;
use App\Services\Webhook\WebhookRunner;
use App\Support\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Tout ce qui touche l'API et les webhooks doit apparaitre dans Admin > Activite. */
class ApiAndWebhookActivityLogTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(UrlGuard::class, new UrlGuard(fn (string $host) => ['hooks.example.com' => ['93.184.216.34']][$host] ?? []));
    }

    private function bearer(string $token): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $token];
    }

    private function entry(string $action): ?ActivityLog
    {
        return ActivityLog::where('action', $action)->latest('id')->first();
    }

    public function test_api_reservations_are_logged_under_the_name_of_the_token_owner(): void
    {
        $pupil = $this->student();
        $token = app(ApiTokenService::class)->create($pupil, 'appli', ['reservations:write'], 30)->plainTextToken;
        $resource = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '00:00', 'close_time' => '23:59']);
        $slot = $resource->slots()->create(['name' => 'Matin', 'start_time' => '08:00', 'end_time' => '10:00', 'max_capacity' => 5, 'is_active' => true]);

        $created = $this->postJson('/api/v1/reservations', ['slot_id' => $slot->id, 'date' => now()->addDay()->toDateString()], $this->bearer($token))->assertCreated();
        $this->deleteJson('/api/v1/reservations/' . $created->json('data.id'), [], $this->bearer($token))->assertNoContent();

        foreach (['Réservation créée (API)', 'Réservation annulée (API)'] as $action) {
            $entry = $this->entry($action);
            $this->assertNotNull($entry, $action);
            $this->assertSame($pupil->id, $entry->user_id);
            $this->assertSame($pupil->name, $entry->user_name);
        }
    }

    public function test_requests_refused_by_policy_are_logged_without_the_token_and_not_repeated(): void
    {
        $pupil = $this->student();
        $secret = app(ApiTokenService::class)->create($pupil, 'appli', ['resources:read'], 30)->plainTextToken;
        $pupil->role->update(['api_access' => false]);

        $this->getJson('/api/v1/me', $this->bearer($secret))->assertForbidden();
        $this->getJson('/api/v1/me', $this->bearer($secret))->assertForbidden();

        $logs = ActivityLog::where('action', 'Requête API refusée')->get();
        $this->assertCount(1, $logs, 'la meme cause pour le meme jeton ne se repete pas');
        $this->assertStringContainsString('api_access_denied : GET /api/v1/me (jeton #', $logs[0]->context);
        $this->assertStringNotContainsString(explode('|', $secret)[1], $logs[0]->context);
        $this->assertSame($pupil->id, $logs[0]->user_id);
    }

    public function test_a_disabled_api_a_disabled_account_and_an_unknown_token_are_logged(): void
    {
        $pupil = $this->student();
        $secret = app(ApiTokenService::class)->create($pupil, 'appli', ['resources:read'], 30)->plainTextToken;

        $this->getJson('/api/v1/me', $this->bearer('1|inconnu'))->assertUnauthorized();
        $pupil->update(['is_active' => false]);
        $this->getJson('/api/v1/me', $this->bearer($secret))->assertForbidden();
        $pupil->update(['is_active' => true]);
        setting('api_enabled'); // lecture avant ecriture : le cache des reglages est deja chaud
        app(\App\Services\SettingService::class)->set('api_enabled', '0');
        $this->getJson('/api/v1/meta')->assertStatus(503);

        $codes = ActivityLog::where('action', 'Requête API refusée')->pluck('context')->map(fn ($c) => explode(' :', $c)[0])->all();
        $this->assertEqualsCanonicalizing(['unauthenticated', 'account_disabled', 'api_disabled'], $codes);
    }

    public function test_changing_a_roles_api_access_is_logged_with_the_new_state(): void
    {
        $role = $this->student()->role;
        $payload = ['label' => $role->label, 'color' => '#3b7ddd', 'max_reservations_day' => 2, 'max_reservations_week' => 5, 'cancel_deadline_minutes' => 30, 'advance_days' => 14];

        $this->actingAs($this->admin())->put(route('admin.roles.update', $role), $payload + ['api_access' => '0']);
        $this->put(route('admin.roles.update', $role), $payload + ['api_access' => '0']);
        $this->put(route('admin.roles.update', $role), $payload + ['api_access' => '1']);

        $this->assertSame(
            ["Rôle {$role->label} : refusé", "Rôle {$role->label} : autorisé"],
            ActivityLog::where('action', "Accès à l'API modifié")->orderBy('id')->pluck('context')->all(),
        );
    }

    public function test_webhook_test_secret_change_and_abandoned_deliveries_are_logged(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 500)]);
        $hook = Webhook::create(['name' => 'Tableau', 'url' => 'https://hooks.example.com/x', 'secret' => 's', 'events' => ['ping'], 'is_active' => true]);
        $this->actingAs($this->admin());
        ActivityLog::query()->delete();

        $this->post(route('admin.webhooks.test', $hook));
        $this->post(route('admin.webhooks.secret', $hook));

        $this->assertStringContainsString('Tableau : échec', $this->entry('Webhook testé')->context);
        $this->assertSame('Tableau', $this->entry('Secret de webhook régénéré')->context);
        $this->assertNull($this->entry('Webhook modifié'), 'pas de doublon "modifié" pour un changement de secret');
        $this->assertNull($this->entry('Webhook : envoi abandonné'), 'un test echoue sans etre "abandonne"');

        $delivery = app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, []);
        $delivery->forceFill(['attempts' => 4])->save();
        app(WebhookRunner::class)->run(5);

        $abandoned = $this->entry('Webhook : envoi abandonné');
        $this->assertSame('Tableau : ping, Réponse HTTP 500', $abandoned->context);
        $this->assertSame('failed', $delivery->fresh()->status);
    }

    public function test_the_activity_page_lists_and_filters_these_entries(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        \App\Services\ActivityLogger::log('Requête API refusée', 'api_disabled : GET /api/v1/meta (IP 1.2.3.4)');
        \App\Services\ActivityLogger::log('Webhook testé', 'Tableau : réponse 200');

        $this->get(route('admin.logs.index'))->assertOk()->assertSee('Requête API refusée')->assertSee('Webhook testé')->assertSee('api_disabled');
        $this->get(route('admin.logs.index', ['action' => 'Webhook testé']))->assertOk()->assertSee('Tableau : réponse 200')->assertDontSee('api_disabled : GET');
    }
}
