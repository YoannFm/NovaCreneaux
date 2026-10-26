<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\Webhook\UrlGuard;
use App\Services\Webhook\WebhookDispatcher;
use App\Services\Webhook\WebhookException;
use App\Services\Webhook\WebhookSender;
use App\Support\WebhookEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Administration des webhooks (reservee aux administrateurs : une adresse choisie ici recoit des donnees du site). */
class WebhookController extends Controller
{
    public function __construct(private UrlGuard $guard) {}

    public function index()
    {
        return view('admin.webhooks.index', [
            'webhooks' => Webhook::withCount(['deliveries as failed_count' => fn ($q) => $q->where('status', 'failed')])->orderBy('name')->get(),
            'events' => WebhookEvent::all(),
        ]);
    }

    public function create()
    {
        return view('admin.webhooks.form', ['webhook' => new Webhook(['is_active' => true, 'events' => []]), 'events' => WebhookEvent::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $secret = $this->newSecret();
        $webhook = Webhook::create($data + ['secret' => $secret]);

        // Le secret n'est affiche qu'une fois : le destinataire en a besoin pour verifier les signatures
        return redirect()->route('admin.webhooks.edit', $webhook)->with('new_secret', $secret)->with('success', 'Webhook créé.');
    }

    public function edit(Webhook $webhook)
    {
        return view('admin.webhooks.form', [
            'webhook' => $webhook,
            'events' => WebhookEvent::all(),
            'deliveries' => $webhook->deliveries()->limit(30)->get(),
        ]);
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data['is_active'] && ! $webhook->is_active) {
            Webhook::whereKey($webhook->id)->update(['consecutive_failures' => 0]);
        }
        $webhook->update($data);

        return redirect()->route('admin.webhooks.edit', $webhook)->with('success', 'Webhook mis à jour.');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $webhook->delete();

        return redirect()->route('admin.webhooks.index')->with('success', 'Webhook supprimé.');
    }

    /** Envoie un evenement `ping` tout de suite, un seul essai, et dit ce qui s'est passe. */
    public function test(Webhook $webhook, WebhookDispatcher $dispatcher, WebhookSender $sender): RedirectResponse
    {
        $delivery = $dispatcher->enqueue($webhook, WebhookEvent::PING, ['message' => 'Test envoyé depuis ' . setting('app_name', 'NovaCreneaux')]);
        $sender->send($delivery, retry: false);
        $delivery->refresh();

        return $delivery->status === 'success'
            ? back()->with('success', "Test réussi : le destinataire a répondu {$delivery->response_code}.")
            : back()->with('error', 'Test échoué : ' . ($delivery->error ?? 'pas de réponse') . '.');
    }

    public function regenerateSecret(Webhook $webhook): RedirectResponse
    {
        $secret = $this->newSecret();
        $webhook->update(['secret' => $secret]);

        return back()->with('new_secret', $secret)->with('success', "Nouveau secret généré : l'ancien ne signe plus rien, mettez à jour le destinataire.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
                try {
                    $this->guard->check($value);
                } catch (WebhookException $e) {
                    $fail($e->getMessage());
                }
            }],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:' . implode(',', array_keys(WebhookEvent::all()))],
        ]);
        $data['events'] = WebhookEvent::only($data['events']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function newSecret(): string
    {
        return 'whsec_' . bin2hex(random_bytes(24));
    }
}
