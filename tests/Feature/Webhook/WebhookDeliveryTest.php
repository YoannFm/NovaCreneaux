<?php

namespace Tests\Feature\Webhook;

use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\ReservationService;
use App\Services\Webhook\UrlGuard;
use App\Services\Webhook\WebhookDispatcher;
use App\Services\Webhook\WebhookRunner;
use App\Services\Webhook\WebhookSender;
use App\Support\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const SECRET = 'whsec_test';
    private const URL = 'https://hooks.example.com/nova';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(UrlGuard::class, new UrlGuard(fn (string $host) => ['hooks.example.com' => ['93.184.216.34'], 'interne.example.com' => ['10.0.0.5']][$host] ?? []));
    }

    private function hook(array $events = [WebhookEvent::RESERVATION_CREATED, WebhookEvent::RESERVATION_CANCELLED, WebhookEvent::USER_CREATED, WebhookEvent::USER_DELETED], array $attributes = []): Webhook
    {
        return Webhook::create($attributes + ['name' => 'Tableau', 'url' => self::URL, 'secret' => self::SECRET, 'events' => $events, 'is_active' => true]);
    }

    private function slot(): Slot
    {
        $resource = Resource::create(['name' => 'Salle B', 'is_active' => true, 'open_time' => '00:00', 'close_time' => '23:59']);

        return $resource->slots()->create(['name' => 'Matin', 'start_time' => '08:00', 'end_time' => '10:00', 'max_capacity' => 5, 'is_active' => true]);
    }

    private function runner(): WebhookRunner
    {
        return $this->app->make(WebhookRunner::class);
    }

    public function test_a_reservation_enqueues_one_delivery_per_subscribed_active_webhook_only(): void
    {
        $subscribed = $this->hook([WebhookEvent::RESERVATION_CREATED]);
        $this->hook([WebhookEvent::USER_DELETED], ['name' => 'Autre']);
        $this->hook([WebhookEvent::RESERVATION_CREATED], ['name' => 'Inactif', 'is_active' => false]);
        $pupil = $this->student();

        $result = app(ReservationService::class)->create($pupil, $this->slot()->id, now()->addDay()->toDateString());

        $this->assertTrue($result['ok']);
        $delivery = WebhookDelivery::where('event', 'reservation.created')->sole();
        $this->assertSame($subscribed->id, $delivery->webhook_id);
        $this->assertSame('pending', $delivery->status);

        $payload = json_decode($delivery->payload, true);
        $this->assertSame($delivery->uuid, $payload['id']);
        $this->assertSame('reservation.created', $payload['event']);
        $this->assertSame('Salle B', $payload['data']['reservation']['resource']['name']);
        $this->assertSame($pupil->id, $payload['data']['user']['id']);
        $this->assertStringNotContainsString($pupil->email, $delivery->payload, "pas d'adresse e-mail envoyee");
    }

    public function test_cancellations_say_who_cancelled(): void
    {
        $this->hook([WebhookEvent::RESERVATION_CANCELLED]);
        $pupil = $this->student();
        $slot = $this->slot();
        $service = app(ReservationService::class);
        $byUser = Reservation::create(['user_id' => $pupil->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDays(3)->toDateString()]);
        $byAdmin = Reservation::create(['user_id' => $pupil->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDays(4)->toDateString()]);

        $service->cancel($pupil, $byUser->id);
        $service->cancelByAdmin($byAdmin, 'fermé');

        $this->assertSame(['user', 'admin'], WebhookDelivery::orderBy('id')->get()->map(fn ($d) => json_decode($d->payload, true)['data']['cancelled_by'])->all());
    }

    public function test_accounts_created_and_deleted_are_announced(): void
    {
        $this->hook([WebhookEvent::USER_CREATED, WebhookEvent::USER_DELETED]);

        $pupil = $this->student();
        $this->actingAs($this->admin());
        $pupil->delete();

        $events = WebhookDelivery::orderBy('id')->get()->map(fn ($d) => [$d->event, json_decode($d->payload, true)['data']]);
        $this->assertSame('user.created', $events[0][0]);
        $this->assertSame($pupil->id, $events[0][1]['user']['id']);
        $deleted = $events->firstWhere(fn ($e) => $e[0] === 'user.deleted' && $e[1]['user']['id'] === $pupil->id);
        $this->assertSame('admin', $deleted[1]['deleted_by']);
    }

    public function test_a_delivery_is_signed_and_marks_the_webhook_as_working(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('ok', 200)]);
        $hook = $this->hook();
        $delivery = app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, ['message' => 'salut']);

        $this->assertSame(1, $this->runner()->run(5));

        Http::assertSent(function (Request $request) use ($delivery) {
            $timestamp = (int) $request->header('X-Nova-Timestamp')[0];
            $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $request->body(), self::SECRET);

            return $request->url() === self::URL
                && $request->method() === 'POST'
                && $request->header('X-Nova-Signature')[0] === $expected
                && $request->header('X-Nova-Event')[0] === 'ping'
                && $request->header('X-Nova-Delivery')[0] === $delivery->uuid
                && abs(time() - $timestamp) < 10
                && $request->isJson();
        });
        $delivery->refresh();
        $this->assertSame('success', $delivery->status);
        $this->assertSame(200, $delivery->response_code);
        $this->assertNotNull($hook->fresh()->last_delivery_at);
        $this->assertNull(WebhookDelivery::where('status', 'pending')->first());
        $this->assertNull(Cache::get(WebhookDispatcher::PENDING_FLAG));
    }

    public function test_the_secret_is_encrypted_in_the_database(): void
    {
        $hook = $this->hook();

        $raw = DB::table('webhooks')->where('id', $hook->id)->value('secret');
        $this->assertStringNotContainsString(self::SECRET, $raw);
        $this->assertSame(self::SECRET, $hook->fresh()->secret);
        $this->assertArrayNotHasKey('secret', $hook->toArray());
    }

    public function test_failures_are_retried_with_a_growing_delay_then_abandoned(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('boom', 500)]);
        $hook = $this->hook();
        $delivery = app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, []);

        $expected = [1, 5, 30, 120];
        foreach ($expected as $i => $minutes) {
            $this->runner()->run(5);
            $delivery->refresh();
            $this->assertSame('pending', $delivery->status);
            $this->assertSame($i + 1, $delivery->attempts);
            $this->assertEqualsWithDelta(now()->addMinutes($minutes)->timestamp, $delivery->next_attempt_at->timestamp, 5);
            $this->assertSame(0, $this->runner()->run(5), 'rien avant la prochaine echeance');
            $this->travel($minutes + 1)->minutes();
        }

        $this->runner()->run(5);
        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(5, $delivery->attempts);
        $this->assertSame(500, $delivery->response_code);
        $this->assertNull($delivery->next_attempt_at);
        $this->assertSame(1, $hook->fresh()->consecutive_failures);
    }

    public function test_network_errors_are_recorded_without_leaking_anything_else(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'));
        $delivery = app(WebhookDispatcher::class)->enqueue($this->hook(), WebhookEvent::PING, []);

        $this->runner()->run(5);

        $this->assertStringContainsString('timed out', $delivery->fresh()->error);
        $this->assertNull($delivery->fresh()->response_code);
    }

    public function test_redirects_are_not_followed_and_count_as_failures(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 302, ['Location' => 'https://10.0.0.1/secret'])]);
        $delivery = app(WebhookDispatcher::class)->enqueue($this->hook(), WebhookEvent::PING, []);

        $this->runner()->run(5);

        $this->assertSame(302, $delivery->fresh()->response_code);
        $this->assertSame('pending', $delivery->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_ten_definitive_failures_disable_the_webhook_and_a_success_resets_the_count(): void
    {
        Http::fake(['hooks.example.com/*' => Http::sequence()->push('', 500)->push('', 204)]);
        $hook = $this->hook();
        Webhook::whereKey($hook->id)->update(['consecutive_failures' => 9]);
        app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, [])->forceFill(['attempts' => 4])->save();

        $this->runner()->run(5);

        $this->assertFalse($hook->fresh()->is_active);
        $this->assertTrue(ActivityLog::where('action', 'Webhook désactivé après des échecs répétés')->exists());

        $other = $this->hook([WebhookEvent::PING], ['name' => 'B']);
        Webhook::whereKey($other->id)->update(['consecutive_failures' => 4]);
        app(WebhookDispatcher::class)->enqueue($other, WebhookEvent::PING, []);
        $this->runner()->run(5);
        $this->assertSame(0, $other->fresh()->consecutive_failures);
    }

    public function test_an_address_that_became_internal_is_never_contacted(): void
    {
        Http::fake();
        $hook = $this->hook();
        $delivery = app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, []);
        Webhook::whereKey($hook->id)->update(['url' => 'https://interne.example.com/x']);

        $this->runner()->run(5);

        Http::assertNothingSent();
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertStringContainsString('réseau interne', $delivery->fresh()->error);
        $this->assertSame(1, $delivery->fresh()->attempts, 'pas de nouvel essai');
    }

    public function test_a_deleted_or_disabled_webhook_drops_its_pending_deliveries(): void
    {
        Http::fake();
        $hook = $this->hook();
        $delivery = app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, []);
        Webhook::whereKey($hook->id)->update(['is_active' => false]);

        $this->runner()->run(5);

        Http::assertNothingSent();
        $this->assertSame('failed', $delivery->fresh()->status);
    }

    public function test_the_time_budget_and_the_batch_size_are_respected(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        $hook = $this->hook();
        foreach (range(1, 5) as $i) {
            app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, []);
        }

        $this->assertSame(3, $this->runner()->run(5, 3));
        $this->assertSame(2, WebhookDelivery::where('status', 'pending')->count());
        $this->assertNotNull(Cache::get(WebhookDispatcher::PENDING_FLAG));
        $this->assertSame(2, $this->runner()->run(5, 3));
        $this->assertNull(Cache::get(WebhookDispatcher::PENDING_FLAG));
    }

    public function test_two_runners_never_send_the_same_delivery_twice(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        app(WebhookDispatcher::class)->enqueue($this->hook(), WebhookEvent::PING, []);

        $lock = Cache::lock('webhooks-run', 60);
        $lock->get();
        $this->assertSame(0, $this->runner()->run(5), 'un autre passage est en cours');
        $lock->release();
        $this->assertSame(1, $this->runner()->run(5));
    }

    public function test_the_scheduled_command_delivers_pending_webhooks(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        app(WebhookDispatcher::class)->enqueue($this->hook(), WebhookEvent::PING, []);

        $this->artisan('webhooks:deliver')->expectsOutputToContain('1 envoi')->assertSuccessful();
    }

    public function test_without_cron_the_next_request_delivers_after_the_response(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        config(['nova.webhooks.on_request' => true]);
        $this->hook([WebhookEvent::USER_CREATED]);

        $this->student();
        $this->get(route('login'))->assertOk();

        $this->assertSame(1, WebhookDelivery::where('status', 'success')->count());
    }

    public function test_the_request_trigger_does_nothing_when_disabled_or_when_nothing_is_pending(): void
    {
        Http::fake();
        $this->hook([WebhookEvent::USER_CREATED]);
        $this->student();

        $this->get(route('login'))->assertOk();
        Http::assertNothingSent();

        config(['nova.webhooks.on_request' => true]);
        Cache::forget(WebhookDispatcher::PENDING_FLAG);
        $this->get(route('login'))->assertOk();
        Http::assertNothingSent();
    }

    public function test_old_history_is_pruned_but_pending_deliveries_are_kept(): void
    {
        Http::fake();
        $hook = $this->hook();
        $old = app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, []);
        $old->forceFill(['status' => 'success', 'created_at' => now()->subDays(40)])->save();
        $stuck = app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, []);
        $stuck->forceFill(['created_at' => now()->subDays(40), 'next_attempt_at' => now()->addDay()])->save();

        $this->runner()->run(5);

        $this->assertNull(WebhookDelivery::find($old->id));
        $this->assertNotNull(WebhookDelivery::find($stuck->id));
    }

    public function test_a_failing_dispatch_never_breaks_the_site_operation(): void
    {
        $this->hook();
        Schema::drop('webhook_deliveries');

        $pupil = $this->student();

        $this->assertDatabaseHas('users', ['id' => $pupil->id]);
    }
}
