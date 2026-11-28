<?php

namespace Tests\Feature\Webhook;

use App\Models\ActivityLog;
use App\Models\Webhook;
use App\Services\Webhook\UrlGuard;
use App\Services\Webhook\WebhookDispatcher;
use App\Services\Webhook\WebhookRunner;
use App\Support\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Formats personnalises et prereglages : formulaire, chiffrement, requetes envoyees. */
class WebhookFormatsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const HOSTS = [
        'hooks.example.com' => ['93.184.216.34'], 'hooks.slack.com' => ['34.200.0.1'], 'api.telegram.org' => ['149.154.167.220'],
        'matrix.example.com' => ['93.184.216.35'], 'ntfy.example.com' => ['93.184.216.36'], 'api.pushover.net' => ['3.3.3.3'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(UrlGuard::class, new UrlGuard(fn (string $host) => self::HOSTS[$host] ?? []));
        $this->actingAs($this->admin());
    }

    private function form(array $extra): array
    {
        return $extra + ['name' => 'Test', 'events' => [WebhookEvent::RESERVATION_CREATED], 'is_active' => '1'];
    }

    private function send(Webhook $hook, string $event = WebhookEvent::RESERVATION_CREATED, array $data = []): void
    {
        $data = $data ?: ['reservation' => ['id' => 1, 'date' => '2026-10-27', 'slot' => ['id' => 1, 'name' => 'Matin', 'start_time' => '08:00', 'end_time' => '09:00'], 'resource' => ['id' => 1, 'name' => 'CDI']], 'user' => ['id' => 2, 'name' => '@everyone <!channel> "Léa"', 'classe' => '2A', 'role' => 'user']];
        app(WebhookDispatcher::class)->enqueue($hook, $event, $data);
        app(WebhookRunner::class)->run(5);
    }

    public function test_a_custom_webhook_sends_its_own_template_with_its_headers(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        $this->post(route('admin.webhooks.store'), $this->form([
            'format' => 'custom', 'url' => 'https://hooks.example.com/in', 'custom_method' => 'PUT', 'custom_content_type' => 'json',
            'custom_body' => '{"msg": "{{ summary }}", "n": "{{ color_int }}", "static": 1}',
            'header_names' => ['Authorization', ''], 'header_values' => ['Bearer abc123', ''],
        ]))->assertSessionHasNoErrors();

        $this->send(Webhook::sole());

        Http::assertSent(function (Request $request) {
            return $request->method() === 'PUT' && $request->url() === 'https://hooks.example.com/in'
                && $request->header('Authorization')[0] === 'Bearer abc123'
                && str_contains($request->data()['msg'], 'Nouvelle réservation')
                && is_int($request->data()['n']) && $request->data()['static'] === 1
                && $request->hasHeader('X-Nova-Signature');
        });
    }

    public function test_a_value_can_never_break_the_json_or_add_a_field(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        $this->post(route('admin.webhooks.store'), $this->form([
            'format' => 'custom', 'show_names' => '1', 'url' => 'https://hooks.example.com/in', 'custom_method' => 'POST', 'custom_content_type' => 'json',
            'custom_body' => '{"text": "{{ who }}"}',
        ]))->assertSessionHasNoErrors();

        $this->send(Webhook::sole());

        Http::assertSent(fn (Request $request) => array_keys($request->data()) === ['text'] && str_contains($request->data()['text'], '"Léa"'));
    }

    public function test_form_and_text_contents_are_encoded_for_their_type(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'custom', 'url' => 'https://hooks.example.com/form', 'custom_method' => 'POST', 'custom_content_type' => 'form', 'custom_body' => '{"title": "{{ title_plain }}", "x": "a&b=c"}']))->assertSessionHasNoErrors();
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'custom', 'url' => 'https://hooks.example.com/text', 'custom_method' => 'POST', 'custom_content_type' => 'text', 'custom_body' => "Salut {{ resource }}\nfin"]))->assertSessionHasNoErrors();

        foreach (Webhook::all() as $hook) {
            $this->send($hook);
        }

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/form') && str_starts_with($request->header('Content-Type')[0], 'application/x-www-form-urlencoded')
            && $request->body() === 'title=Nouvelle%20r%C3%A9servation&x=a%26b%3Dc');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/text') && $request->body() === "Salut CDI\nfin" && str_starts_with($request->header('Content-Type')[0], 'text/plain'));
    }

    public function test_the_custom_form_refuses_bad_bodies_unknown_variables_and_forbidden_headers(): void
    {
        $base = $this->form(['format' => 'custom', 'url' => 'https://hooks.example.com/in', 'custom_method' => 'POST', 'custom_content_type' => 'json', 'custom_body' => '{"a": "{{ title }}"}']);

        $this->post(route('admin.webhooks.store'), ['custom_body' => 'pas du json'] + $base)->assertSessionHasErrors('custom_body');
        $this->post(route('admin.webhooks.store'), ['custom_body' => '"texte seul"'] + $base)->assertSessionHasErrors('custom_body');
        $this->post(route('admin.webhooks.store'), ['custom_body' => '{"a": "{{ secret }}"}'] + $base)->assertSessionHasErrors('custom_body');
        $this->post(route('admin.webhooks.store'), ['custom_content_type' => 'form', 'custom_body' => '{"a": {"b": "c"}}'] + $base)->assertSessionHasErrors('custom_body');
        $this->post(route('admin.webhooks.store'), ['custom_method' => 'DELETE'] + $base)->assertSessionHasErrors('custom_method');
        foreach (['Host', 'Content-Length', 'content-type', 'X-Nova-Signature', 'Mauvais Nom', 'Transfer-Encoding'] as $name) {
            $this->post(route('admin.webhooks.store'), ['header_names' => [$name], 'header_values' => ['x']] + $base)->assertSessionHasErrors('headers');
        }
        $this->post(route('admin.webhooks.store'), ['header_names' => ['X-Api-Key'], 'header_values' => ["a\r\nX-Evil: 1"]] + $base)->assertSessionHasErrors('headers');
        $this->post(route('admin.webhooks.store'), ['header_names' => ['X-Api-Key'], 'header_values' => ['']] + $base)->assertSessionHasErrors('headers');
        $this->post(route('admin.webhooks.store'), ['url' => 'https://interne.example.com/x'] + $base)->assertSessionHasErrors('url');
        $this->assertSame(0, Webhook::count());
    }

    public function test_header_values_are_kept_when_left_empty_and_stored_encrypted(): void
    {
        $this->post(route('admin.webhooks.store'), $this->form([
            'format' => 'custom', 'url' => 'https://hooks.example.com/in', 'custom_method' => 'POST', 'custom_content_type' => 'json',
            'custom_body' => '{"a": "{{ title }}"}', 'header_names' => ['X-Api-Key'], 'header_values' => ['cle-secrete-123'],
        ]))->assertSessionHasNoErrors();
        $hook = Webhook::sole();
        $this->assertStringNotContainsString('cle-secrete-123', DB::table('webhooks')->value('settings'));

        $this->put(route('admin.webhooks.update', $hook), $this->form([
            'format' => 'custom', 'url' => 'https://hooks.example.com/in', 'custom_method' => 'POST', 'custom_content_type' => 'json',
            'custom_body' => '{"a": "{{ text }}"}', 'header_names' => ['X-Api-Key'], 'header_values' => [''],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('cle-secrete-123', $hook->fresh()->settings['custom']['headers']['X-Api-Key']);
        $this->get(route('admin.webhooks.edit', $hook))->assertOk()->assertDontSee('cle-secrete-123')->assertSee('(inchangé)', false);
    }

    public function test_a_preset_with_a_token_in_its_address_sends_to_the_service_and_hides_the_token(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('{"ok":true}', 200)]);
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'telegram', 'fields' => ['bot_token' => '123:ABC-def', 'chat_id' => '-1001']]))->assertSessionHasNoErrors();
        $hook = Webhook::sole();

        $this->assertSame('https://api.telegram.org/', $hook->url, 'le jeton ne reste pas dans la colonne en clair');
        $this->assertStringNotContainsString('123:ABC', DB::table('webhooks')->value('settings'));
        $this->assertStringNotContainsString('123:ABC', ActivityLog::pluck('context')->implode(' '));
        $this->get(route('admin.webhooks.edit', $hook))->assertDontSee('123:ABC-def')->assertSee('(inchangé)', false);

        $this->send($hook);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.telegram.org/bot123%3AABC-def/sendMessage' && $request->data()['chat_id'] === '-1001' && str_contains($request->data()['text'], 'Nouvelle réservation'));
    }

    public function test_a_preset_secret_left_empty_is_kept_but_a_missing_one_is_refused(): void
    {
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'telegram', 'fields' => ['bot_token' => 'tok', 'chat_id' => '1']]))->assertSessionHasNoErrors();
        $hook = Webhook::sole();

        $this->put(route('admin.webhooks.update', $hook), $this->form(['format' => 'telegram', 'fields' => ['bot_token' => '', 'chat_id' => '2']]))->assertSessionHasNoErrors();
        $this->assertSame(['bot_token' => 'tok', 'chat_id' => '2'], $hook->fresh()->settings['fields']);

        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'telegram', 'fields' => ['bot_token' => '', 'chat_id' => '1']]))->assertSessionHasErrors('fields.bot_token');
        $this->put(route('admin.webhooks.update', $hook), $this->form(['format' => 'pushover', 'fields' => ['app_token' => '', 'user_key' => 'u']]))->assertSessionHasErrors('fields.app_token');
    }

    public function test_slack_text_cannot_trigger_mentions_and_the_address_must_be_slack(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'slack', 'show_names' => '1', 'url' => 'https://hooks.example.com/x']))->assertSessionHasErrors('url');
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'slack', 'show_names' => '1', 'url' => 'https://hooks.slack.com/services/T/B/x']))->assertSessionHasNoErrors();

        $this->send(Webhook::sole());

        Http::assertSent(fn (Request $request) => str_contains($request->data()['text'], '&lt;!channel&gt;') && ! str_contains($request->data()['text'], '<!channel>'));
    }

    public function test_matrix_puts_the_message_with_a_bearer_token_and_a_unique_transaction(): void
    {
        Http::fake(['matrix.example.com/*' => Http::response('{}', 200)]);
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'matrix', 'fields' => ['homeserver' => 'https://matrix.example.com/', 'room_id' => '!abc:example.com', 'access_token' => 'syt_x']]))->assertSessionHasNoErrors();

        $this->send(Webhook::sole());

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && str_starts_with($request->url(), 'https://matrix.example.com/_matrix/client/v3/rooms/%21abc%3Aexample.com/send/m.room.message/')
            && $request->header('Authorization')[0] === 'Bearer syt_x');
    }

    public function test_ntfy_gets_plain_text_and_pushover_a_form(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'ntfy', 'url' => 'https://ntfy.example.com/sujet']))->assertSessionHasNoErrors();
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'pushover', 'fields' => ['app_token' => 'a', 'user_key' => 'u']]))->assertSessionHasNoErrors();

        foreach (Webhook::all() as $hook) {
            $this->send($hook);
        }

        Http::assertSent(fn (Request $request) => $request->url() === 'https://ntfy.example.com/sujet' && $request->header('Title')[0] === 'Nouvelle réservation' && str_contains($request->body(), 'CDI - Matin'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.pushover.net/1/messages.json' && str_contains($request->body(), 'token=a&user=u&title='));
    }

    public function test_payload_presets_send_the_standard_payload(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'n8n', 'show_names' => '1', 'url' => 'https://hooks.example.com/webhook/x']))->assertSessionHasNoErrors();
        $hook = Webhook::sole();
        $this->assertFalse($hook->show_names, 'sans objet : la charge utile standard est envoyee telle quelle');

        $this->send($hook);

        Http::assertSent(fn (Request $request) => $request->data()['event'] === 'reservation.created' && isset($request->data()['data']['reservation']));
        $this->get(route('admin.webhooks.edit', $hook))->assertSee('X-Nova-Signature');
    }

    public function test_a_removed_preset_fails_cleanly_without_retries(): void
    {
        $hook = Webhook::create(['name' => 'Ancien', 'url' => 'https://hooks.example.com/x', 'secret' => 's', 'events' => ['ping'], 'is_active' => true, 'format' => 'preset', 'preset' => 'disparu']);
        $delivery = app(WebhookDispatcher::class)->enqueue($hook, WebhookEvent::PING, ['message' => 'x']);

        app(WebhookRunner::class)->run(5);

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertStringContainsString('disparu', $delivery->fresh()->error);
    }

    public function test_the_form_lists_the_catalog_and_the_index_names_the_format(): void
    {
        $this->get(route('admin.webhooks.create'))->assertOk()->assertSee('Messagerie d&#039;équipe', false)->assertSee('Zapier')->assertSee('n8n')
            ->assertSee('Variables disponibles')->assertSee('title_plain');
        $this->post(route('admin.webhooks.store'), $this->form(['format' => 'custom', 'url' => 'https://hooks.example.com/in', 'custom_method' => 'POST', 'custom_content_type' => 'text', 'custom_body' => '{{ text }}']))->assertSessionHasNoErrors();

        $this->get(route('admin.webhooks.index'))->assertSee('Personnalisé');
    }
}
