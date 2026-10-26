@extends('layouts.admin')
@php
    $isNew = ! $webhook->exists;
    $pageTitle = $isNew ? 'Nouveau webhook' : 'Webhook : ' . $webhook->name;
@endphp
@section('content')

@if(session('new_secret'))
<div class="alert alert-success">
    <strong><i class="bi bi-key me-1"></i> Secret de signature.</strong> Copiez-le maintenant et donnez-le au service qui reçoit les envois : il ne sera plus jamais affiché.
    <div class="input-group mt-2">
        <input type="text" id="new-secret" class="form-control font-monospace" value="{{ session('new_secret') }}" readonly>
        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('new-secret').value)">
            <i class="bi bi-clipboard"></i> Copier
        </button>
    </div>
</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ $isNew ? route('admin.webhooks.store') : route('admin.webhooks.update', $webhook) }}">
            @csrf
            @unless($isNew) @method('PUT') @endunless
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="name">Nom</label>
                    <input type="text" id="name" name="name" maxlength="100" required value="{{ old('name', $webhook->name) }}"
                           class="form-control @error('name') is-invalid @enderror" placeholder="Tableau d'affichage du CDI">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="url">Adresse qui reçoit les envois</label>
                    <input type="url" id="url" name="url" maxlength="500" required value="{{ old('url', $webhook->url) }}"
                           class="form-control font-monospace @error('url') is-invalid @enderror" placeholder="https://exemple.fr/webhooks/nova">
                    @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">En https. Les adresses qui visent le réseau interne du serveur sont refusées.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Événements</label>
                    @php $selected = old('events', $webhook->events ?? []); @endphp
                    @foreach($events as $key => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="events[]" value="{{ $key }}" id="event-{{ $loop->index }}" @checked(in_array($key, $selected, true))>
                            <label class="form-check-label" for="event-{{ $loop->index }}"><code>{{ $key }}</code> - {{ $label }}</label>
                        </div>
                    @endforeach
                    @error('events')<div class="text-danger small">{{ $message }}</div>@enderror
                    @error('events.*')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $webhook->is_active))>
                        <label class="form-check-label" for="is_active">Actif</label>
                    </div>
                    @if(! $isNew && ! $webhook->is_active && $webhook->consecutive_failures >= 10)
                        <div class="form-text text-danger">Désactivé automatiquement après {{ $webhook->consecutive_failures }} échecs consécutifs. Corrigez le destinataire puis réactivez.</div>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Enregistrer</button>
                @unless($isNew)
                    <button type="submit" form="delete-webhook" class="btn btn-danger"
                            onclick="return confirm('Supprimer ce webhook et son historique ?')"><i class="bi bi-trash me-1"></i> Supprimer</button>
                @endunless
                <a href="{{ route('admin.webhooks.index') }}" class="btn btn-outline-secondary">Retour</a>
            </div>
        </form>
        @unless($isNew)
            <form id="delete-webhook" method="POST" action="{{ route('admin.webhooks.destroy', $webhook) }}" class="d-none">@csrf @method('DELETE')</form>
        @endunless
    </div>
</div>

@unless($isNew)
<div class="card mb-4">
    <div class="card-header">Test et secret</div>
    <div class="card-body d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.webhooks.test', $webhook) }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-send me-1"></i> Envoyer un test</button>
        </form>
        <form method="POST" action="{{ route('admin.webhooks.secret', $webhook) }}">
            @csrf
            <button type="submit" class="btn btn-outline-warning btn-sm"
                    onclick="return confirm('Générer un nouveau secret ? L\'ancien cessera de fonctionner.')"><i class="bi bi-arrow-repeat me-1"></i> Régénérer le secret</button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Derniers envois</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 small align-middle">
                <thead class="table-light"><tr><th>Date</th><th>Événement</th><th>État</th><th>Essais</th><th>Réponse</th><th>Détail</th></tr></thead>
                <tbody>
                @forelse($deliveries as $delivery)
                    <tr>
                        <td>{{ $delivery->created_at->format('d/m/Y H:i:s') }}</td>
                        <td><code>{{ $delivery->event }}</code></td>
                        <td>
                            @switch($delivery->status)
                                @case('success') <span class="badge bg-success">Réussi</span> @break
                                @case('failed') <span class="badge bg-danger">Échec</span> @break
                                @default <span class="badge bg-warning text-dark">En attente</span>
                            @endswitch
                        </td>
                        <td>{{ $delivery->attempts }}</td>
                        <td>{{ $delivery->response_code ?? '-' }}</td>
                        <td class="text-break">{{ $delivery->error ?? ($delivery->duration_ms !== null ? $delivery->duration_ms . ' ms' : '') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-3 text-muted">Aucun envoi pour l'instant.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Côté destinataire</div>
    <div class="card-body small">
        <p>Chaque envoi est une requête <code>POST</code> en JSON avec ces en-têtes :</p>
        <ul>
            <li><code>X-Nova-Event</code> : l'événement (<code>reservation.created</code>...).</li>
            <li><code>X-Nova-Delivery</code> : identifiant unique de l'envoi, identique à chaque nouvel essai (ignorez les doublons).</li>
            <li><code>X-Nova-Timestamp</code> : l'heure d'envoi (secondes). Refusez les envois de plus de 5 minutes.</li>
            <li><code>X-Nova-Signature</code> : <code>sha256=</code> suivi du HMAC-SHA256 de <code>horodatage.corps</code> avec votre secret.</li>
        </ul>
        <p class="mb-1">Vérification en PHP :</p>
        <pre class="border rounded p-2 mb-2"><code>$body = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $_SERVER['HTTP_X_NOVA_TIMESTAMP'] . '.' . $body, $secret);
$valid = hash_equals($expected, $_SERVER['HTTP_X_NOVA_SIGNATURE'])
    &amp;&amp; abs(time() - (int) $_SERVER['HTTP_X_NOVA_TIMESTAMP']) &lt; 300;</code></pre>
        <p class="mb-0">Répondez avec un code 2xx. Sinon l'envoi est retenté 4 fois (après 1 min, 5 min, 30 min et 2 h), puis abandonné.
            Après 10 échecs définitifs consécutifs, le webhook est désactivé. Les données envoyées ne contiennent ni adresse e-mail ni mot de passe.</p>
    </div>
</div>
@endunless

@endsection
