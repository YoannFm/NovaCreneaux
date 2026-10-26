<?php

namespace Tests\Feature\Webhook;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Webhook;
use App\Services\ReservationService;
use App\Services\Webhook\DiscordMessage;
use App\Services\Webhook\UrlGuard;
use App\Services\Webhook\WebhookDispatcher;
use App\Services\Webhook\WebhookRunner;
use App\Support\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class DiscordWebhookTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const URL = 'https://discord.com/api/webhooks/123456/abcdef-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(UrlGuard::class, new UrlGuard(fn (string $host) => ['discord.com' => ['162.159.135.232'], 'hooks.example.com' => ['93.184.216.34']][$host] ?? []));
    }

    private function discordHook(bool $showNames = false): Webhook
    {
        return Webhook::create([
            'name' => 'Discord', 'url' => self::URL, 'secret' => 'whsec_x', 'format' => 'discord', 'show_names' => $showNames,
            'events' => array_keys(WebhookEvent::all()), 'is_active' => true,
        ]);
    }

    private function reservationPayload(string $event = WebhookEvent::RESERVATION_CREATED, array $extra = []): array
    {
        return ['id' => 'u1', 'event' => $event, 'created_at' => '2026-10-27T10:00:00+01:00', 'data' => [
            'reservation' => ['id' => 1, 'date' => '2026-10-27', 'slot' => ['id' => 1, 'name' => '11h55 - 12h50'], 'resource' => ['id' => 1, 'name' => 'CDI']],
            'user' => ['id' => 12, 'name' => 'Yoann', 'classe' => 'TEST', 'role' => 'user'],
        ] + $extra];
    }

    public function test_the_message_describes_the_reservation_without_naming_the_pupil_by_default(): void
    {
        $message = DiscordMessage::build($this->reservationPayload(), false, 'CDI Rascol');

        $embed = $message['embeds'][0];
        $this->assertSame('CDI Rascol', $message['username']);
        $this->assertStringContainsString('Nouvelle réservation', $embed['title']);
        $this->assertStringContainsString('**CDI** - 11h55 - 12h50', $embed['description']);
        $this->assertStringContainsString('mardi 27 octobre 2026', $embed['description']);
        $this->assertStringContainsString('un élève', $embed['description']);
        $this->assertStringNotContainsString('Yoann', json_encode($message));
        $this->assertStringNotContainsString('TEST', json_encode($message));
        $this->assertSame('2026-10-27T10:00:00+01:00', $embed['timestamp']);
    }

    public function test_names_appear_only_when_the_administrator_chooses_so(): void
    {
        $message = DiscordMessage::build($this->reservationPayload(), true, 'CDI');

        $this->assertStringContainsString('Yoann (TEST)', $message['embeds'][0]['description']);
    }

    public function test_every_event_has_a_readable_message(): void
    {
        $user = ['id' => 5, 'name' => 'Léa', 'classe' => null, 'role' => 'user'];
        $cases = [
            [WebhookEvent::RESERVATION_CANCELLED, ['cancelled_by' => 'admin'], 'Annulée par un administrateur'],
            [WebhookEvent::RESERVATION_CANCELLED, ['cancelled_by' => 'user'], "Annulée par l'élève"],
        ];
        foreach ($cases as [$event, $extra, $expected]) {
            $text = DiscordMessage::build($this->reservationPayload($event, $extra), false, 'CDI')['embeds'][0];
            $this->assertStringContainsString('Réservation annulée', $text['title']);
            $this->assertStringContainsString($expected, $text['description']);
        }

        $created = DiscordMessage::build(['id' => 'u', 'event' => 'user.created', 'created_at' => now()->toIso8601String(), 'data' => ['user' => $user]], false, 'CDI')['embeds'][0];
        $this->assertSame('Un compte a été créé.', $created['description']);
        $deleted = DiscordMessage::build(['id' => 'u', 'event' => 'user.deleted', 'created_at' => now()->toIso8601String(), 'data' => ['user' => $user, 'deleted_by' => 'admin']], true, 'CDI')['embeds'][0];
        $this->assertSame('Léa', $deleted['description']);
        $ping = DiscordMessage::build(['id' => 'u', 'event' => 'ping', 'created_at' => now()->toIso8601String(), 'data' => ['message' => 'Test']], false, 'CDI')['embeds'][0];
        $this->assertStringContainsString('Test', $ping['title']);
    }

    public function test_no_mention_can_be_triggered_and_long_text_is_cut(): void
    {
        $payload = $this->reservationPayload();
        $payload['data']['user']['name'] = '@everyone ' . str_repeat('x', 6000);

        $message = DiscordMessage::build($payload, true, str_repeat('N', 200));

        $this->assertSame(['parse' => []], $message['allowed_mentions']);
        $this->assertLessThanOrEqual(4000, mb_strlen($message['embeds'][0]['description']));
        $this->assertLessThanOrEqual(80, mb_strlen($message['username']));
    }

    public function test_a_discord_webhook_posts_the_discord_message_not_the_standard_payload(): void
    {
        Http::fake([self::URL => Http::response('', 204)]);
        $hook = $this->discordHook();
        app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::RESERVATION_CREATED, $this->reservationPayload()['data']);

        app(WebhookRunner::class)->run(5);

        Http::assertSent(function (Request $request) use ($hook) {
            $body = $request->data();
            $timestamp = (int) $request->header('X-Nova-Timestamp')[0];

            return $request->url() === self::URL
                && isset($body['embeds'][0]['title'], $body['username'])
                && ! isset($body['event'])
                && $request->header('X-Nova-Signature')[0] === 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $request->body(), $hook->secret);
        });
        $this->assertSame('success', $hook->deliveries()->first()->status);
        $this->assertSame(204, $hook->deliveries()->first()->response_code);
    }

    public function test_a_standard_webhook_still_gets_the_standard_payload(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        $hook = Webhook::create(['name' => 'Std', 'url' => 'https://hooks.example.com/x', 'secret' => 's', 'events' => ['ping'], 'is_active' => true]);
        app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, ['message' => 'salut']);

        app(WebhookRunner::class)->run(5);

        Http::assertSent(fn (Request $request) => $request->data()['event'] === 'ping' && ! isset($request->data()['embeds']));
        $this->assertSame('json', $hook->fresh()->format);
    }

    public function test_a_discord_rate_limit_is_retried_later(): void
    {
        Http::fake([self::URL => Http::response(['retry_after' => 1], 429)]);
        $hook = $this->discordHook();
        $delivery = app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, ['message' => 'x']);

        app(WebhookRunner::class)->run(5);

        $this->assertSame('pending', $delivery->fresh()->status);
        $this->assertSame(429, $delivery->fresh()->response_code);
    }

    public function test_a_real_reservation_reaches_discord_end_to_end(): void
    {
        Http::fake([self::URL => Http::response('', 204)]);
        $this->discordHook();
        $resource = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '00:00', 'close_time' => '23:59']);
        $slot = $resource->slots()->create(['name' => '11h55 - 12h50', 'start_time' => '11:55', 'end_time' => '12:50', 'max_capacity' => 5, 'is_active' => true]);

        app(ReservationService::class)->create($this->student(), $slot->id, now()->addDay()->toDateString());
        app(WebhookRunner::class)->run(5);

        Http::assertSent(fn (Request $request) => str_contains($request->data()['embeds'][0]['description'], '**CDI** - 11h55 - 12h50'));
    }

    public function test_the_form_saves_the_format_and_the_names_option_and_requires_a_discord_address(): void
    {
        $this->actingAs($this->admin());
        $payload = ['name' => 'Discord', 'url' => self::URL, 'events' => [WebhookEvent::RESERVATION_CREATED], 'is_active' => '1', 'format' => 'discord'];

        $this->post(route('admin.webhooks.store'), $payload)->assertSessionHasNoErrors();
        $hook = Webhook::sole();
        $this->assertTrue($hook->isDiscord());
        $this->assertFalse($hook->show_names, 'les noms ne sont pas envoyes par defaut');

        $this->put(route('admin.webhooks.update', $hook), $payload + ['show_names' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($hook->fresh()->show_names);

        $this->put(route('admin.webhooks.update', $hook), array_merge($payload, ['format' => 'json', 'show_names' => '1', 'url' => 'https://hooks.example.com/x']))->assertSessionHasNoErrors();
        $this->assertFalse($hook->fresh()->show_names, "l'option n'a de sens qu'avec Discord");

        $this->post(route('admin.webhooks.store'), array_merge($payload, ['url' => 'https://hooks.example.com/x']))->assertSessionHasErrors('url');
        $this->post(route('admin.webhooks.store'), array_merge($payload, ['url' => 'https://evil-discord.com.example.net/x']))->assertSessionHasErrors('url');
        $this->post(route('admin.webhooks.store'), array_merge($payload, ['format' => 'slack']))->assertSessionHasErrors('format');
        $this->assertSame(1, Webhook::count());
    }

    public function test_the_pages_adapt_to_the_format(): void
    {
        $this->actingAs($this->admin());
        $discord = $this->discordHook();

        $this->get(route('admin.webhooks.edit', $discord))->assertOk()->assertSee('Format des envois')->assertDontSee('Côté destinataire');
        $this->get(route('admin.webhooks.index'))->assertSee('Discord');
        $this->get(route('admin.webhooks.create'))->assertOk()->assertSee('Afficher le nom et la classe des élèves dans Discord');
    }

    public function test_the_test_button_posts_a_discord_test_message(): void
    {
        Http::fake([self::URL => Http::response('', 204)]);
        $hook = $this->discordHook();

        $this->actingAs($this->admin())->post(route('admin.webhooks.test', $hook))->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => str_contains($request->data()['embeds'][0]['title'], 'Test'));
    }
}
