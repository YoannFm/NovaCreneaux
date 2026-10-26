<?php

namespace Tests\Feature\Webhook;

use App\Models\ActivityLog;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Webhook\UrlGuard;
use App\Support\Permission;
use App\Support\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class WebhookAdminTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(UrlGuard::class, new UrlGuard(fn (string $host) => ['hooks.example.com' => ['93.184.216.34'], 'interne.example.com' => ['10.0.0.5']][$host] ?? []));
    }

    private function payload(array $override = []): array
    {
        return $override + ['name' => 'Tableau', 'url' => 'https://hooks.example.com/nova', 'events' => [WebhookEvent::RESERVATION_CREATED], 'is_active' => '1'];
    }

    private function hook(): Webhook
    {
        return Webhook::create(['name' => 'Tableau', 'url' => 'https://hooks.example.com/nova', 'secret' => 'whsec_old', 'events' => [WebhookEvent::RESERVATION_CREATED], 'is_active' => true]);
    }

    public function test_only_administrators_manage_webhooks_even_with_every_delegable_permission(): void
    {
        $hook = $this->hook();
        $staff = $this->staff(array_keys(Permission::all()));

        $this->get(route('admin.webhooks.index'))->assertRedirect(route('login'));
        foreach ([$this->student(), $staff] as $user) {
            $this->actingAs($user);
            $expected = $user->hasAdminAccess() ? route('admin.dashboard') : '/';
            $this->get(route('admin.webhooks.index'))->assertRedirect($expected);
            $this->post(route('admin.webhooks.store'), $this->payload())->assertRedirect($expected);
            $this->post(route('admin.webhooks.test', $hook))->assertRedirect($expected);
            $this->delete(route('admin.webhooks.destroy', $hook))->assertRedirect($expected);
        }
        $this->assertSame(1, Webhook::count());
    }

    public function test_creating_a_webhook_shows_the_secret_once_and_logs_it_without_the_secret(): void
    {
        $this->actingAs($this->admin())->post(route('admin.webhooks.store'), $this->payload())->assertSessionHasNoErrors();
        $hook = Webhook::sole();
        $secret = session('new_secret');

        $this->assertMatchesRegularExpression('/^whsec_[a-f0-9]{48}$/', $secret);
        $this->assertSame($secret, $hook->secret);
        $this->get(route('admin.webhooks.edit', $hook))->assertSee($secret, false);
        $this->get(route('admin.webhooks.edit', $hook))->assertDontSee($secret, false);
        $log = ActivityLog::where('action', 'Webhook créé')->sole();
        $this->assertStringNotContainsString($secret, (string) $log->context);
    }

    public function test_the_form_refuses_unsafe_or_invalid_input(): void
    {
        $this->actingAs($this->admin());

        foreach ([
            ['url' => 'http://hooks.example.com/x'],
            ['url' => 'https://interne.example.com/x'],
            ['url' => 'https://127.0.0.1/x'],
            ['url' => 'https://169.254.169.254/latest'],
            ['url' => 'pas une adresse'],
            ['url' => ''],
            ['name' => ''],
            ['events' => []],
            ['events' => ['admin.everything']],
        ] as $invalid) {
            $this->post(route('admin.webhooks.store'), $this->payload($invalid))->assertSessionHasErrors();
        }
        $this->assertSame(0, Webhook::count());
    }

    public function test_updating_changes_the_fields_and_reactivating_clears_the_failure_count(): void
    {
        $hook = $this->hook();
        Webhook::whereKey($hook->id)->update(['is_active' => false, 'consecutive_failures' => 10]);

        $this->actingAs($this->admin())->put(route('admin.webhooks.update', $hook), $this->payload(['name' => 'Nouveau nom', 'events' => [WebhookEvent::USER_DELETED], 'is_active' => '1']))->assertSessionHasNoErrors();

        $fresh = $hook->fresh();
        $this->assertSame('Nouveau nom', $fresh->name);
        $this->assertSame([WebhookEvent::USER_DELETED], $fresh->events);
        $this->assertTrue($fresh->is_active);
        $this->assertSame(0, $fresh->consecutive_failures);
        $this->assertSame('whsec_old', $fresh->secret, 'le secret ne change pas en modifiant le webhook');
    }

    public function test_regenerating_the_secret_replaces_it_and_shows_the_new_one_once(): void
    {
        $hook = $this->hook();

        $this->actingAs($this->admin())->post(route('admin.webhooks.secret', $hook))->assertSessionHas('new_secret');

        $this->assertNotSame('whsec_old', $hook->fresh()->secret);
        $this->assertSame(session('new_secret'), $hook->fresh()->secret);
    }

    public function test_the_test_button_reports_success_and_failure_with_a_single_attempt(): void
    {
        $hook = $this->hook();
        $this->actingAs($this->admin());

        Http::fake(['hooks.example.com/*' => Http::sequence()->push('', 200)->push('', 503)]);
        $this->post(route('admin.webhooks.test', $hook))->assertSessionHas('success', fn ($m) => str_contains($m, '200'));

        $this->post(route('admin.webhooks.test', $hook))->assertSessionHas('error', fn ($m) => str_contains($m, '503'));

        $this->assertSame(['success', 'failed'], WebhookDelivery::orderBy('id')->pluck('status')->all());
        $this->assertSame(0, WebhookDelivery::where('status', 'pending')->count(), 'un test ne se retente pas');
    }

    public function test_the_edit_page_lists_recent_deliveries_and_the_receiver_documentation(): void
    {
        $hook = $this->hook();
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        $this->actingAs($this->admin())->post(route('admin.webhooks.test', $hook));

        $this->get(route('admin.webhooks.edit', $hook))->assertOk()->assertSee('Derniers envois')->assertSee('Réussi')->assertSee('X-Nova-Signature')->assertSee('hash_hmac');
        $this->get(route('admin.webhooks.index'))->assertOk()->assertSee('Tableau')->assertSee('Actif');
    }

    public function test_deleting_a_webhook_removes_its_history_and_is_logged(): void
    {
        $hook = $this->hook();
        WebhookDelivery::create(['webhook_id' => $hook->id, 'uuid' => (string) Str::uuid(), 'event' => 'ping', 'payload' => '{}', 'status' => 'success', 'created_at' => now()]);

        $this->actingAs($this->admin())->delete(route('admin.webhooks.destroy', $hook))->assertRedirect(route('admin.webhooks.index'));

        $this->assertSame(0, WebhookDelivery::count());
        $this->assertTrue(ActivityLog::where('action', 'Webhook supprimé')->exists());
    }

    public function test_delivery_bookkeeping_does_not_fill_the_activity_log(): void
    {
        $hook = $this->hook();
        Http::fake(['hooks.example.com/*' => Http::response('', 500)]);
        $this->actingAs($this->admin());
        ActivityLog::query()->delete();

        $this->post(route('admin.webhooks.test', $hook));

        $this->assertSame(0, ActivityLog::where('action', 'Webhook modifié')->count());
    }
}
