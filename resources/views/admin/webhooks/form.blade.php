@extends('layouts.admin')
@php
    $isNew = ! $webhook->exists;
    $pageTitle = $isNew ? 'Nouveau webhook' : 'Webhook : ' . $webhook->name;
    $choice = old('format', $webhook->formatChoice());
    $custom = $webhook->settings['custom'] ?? [];
    $savedFields = $webhook->settings['fields'] ?? [];
    $presetList = collect($presets)->flatMap(fn ($group) => $group)->all();
    $receivesPayload = $webhook->format === 'json' || ($presetList[$webhook->preset]['body'] ?? null) === '@payload';
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
                <div class="col-md-5">
                    <label class="form-label" for="format">Format des envois</label>
                    <select id="format" name="format" class="form-select @error('format') is-invalid @enderror">
                        <optgroup label="Général">
                            <option value="json" data-names="0" @selected($choice === 'json')>Standard (JSON signé)</option>
                            <option value="custom" data-names="1" data-url="1" @selected($choice === 'custom')>Personnalisé (modèle libre)</option>
                        </optgroup>
                        @foreach($presets as $category => $group)
                            <optgroup label="{{ $category }}">
                                @foreach($group as $id => $preset)
                                    <option value="{{ $id }}" data-names="{{ ($preset['body'] ?? null) === '@payload' ? 0 : 1 }}"
                                            data-url="{{ isset($preset['url_template']) ? 0 : 1 }}" data-example="{{ $preset['url_example'] ?? '' }}"
                                            @selected($choice === $id)>{{ $preset['name'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('format')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-7" id="names-option">
                    <div class="form-check form-switch mt-md-4">
                        <input type="checkbox" class="form-check-input" id="show_names" name="show_names" value="1" @checked(old('show_names', $webhook->show_names))>
                        <label class="form-check-label" for="show_names">Afficher le nom et la classe des élèves dans les messages</label>
                    </div>
                    <div class="form-text">Désactivé par défaut : le destinataire est un service tiers, les messages disent « un élève ».</div>
                </div>
                <div class="col-12" id="url-group">
                    <label class="form-label" for="url">Adresse qui reçoit les envois</label>
                    <input type="url" id="url" name="url" maxlength="500" value="{{ old('url', $webhook->url) }}"
                           class="form-control font-monospace @error('url') is-invalid @enderror" placeholder="https://exemple.fr/webhooks/nova">
                    @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">En https. Les adresses qui visent le réseau interne du serveur sont refusées.</div>
                </div>

                @foreach($presets as $group)
                    @foreach($group as $id => $preset)
                        <div class="col-12 format-section" data-format="{{ $id }}">
                            <div class="border rounded p-3">
                                <p class="mb-2"><strong>{{ $preset['name'] }}</strong> : {{ $preset['description'] }}</p>
                                <p class="small mb-2" style="color:var(--muted-text)">{{ $preset['help'] ?? '' }}</p>
                                @if(($preset['body'] ?? null) === '@payload')
                                    <p class="small mb-2">Ce service reçoit la charge utile standard (JSON signé) : les noms des élèves y figurent.</p>
                                @endif
                                <div class="row g-3">
                                    @foreach($preset['fields'] ?? [] as $field)
                                        @php $key = $field['key']; $secret = $field['secret'] ?? false; $has = $webhook->preset === $id && ($savedFields[$key] ?? '') !== ''; @endphp
                                        <div class="col-md-6">
                                            <label class="form-label" for="field-{{ $id }}-{{ $key }}">{{ $field['label'] }}</label>
                                            <input type="{{ $secret ? 'password' : 'text' }}" id="field-{{ $id }}-{{ $key }}" name="fields[{{ $key }}]" maxlength="500" disabled autocomplete="off"
                                                   value="{{ $secret ? '' : old("fields.{$key}", $webhook->preset === $id ? ($savedFields[$key] ?? '') : '') }}"
                                                   placeholder="{{ $secret && $has ? '(inchangé)' : ($field['placeholder'] ?? '') }}"
                                                   class="form-control font-monospace @error("fields.{$key}") is-invalid @enderror">
                                            @error("fields.{$key}")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
                @error('fields')<div class="col-12 text-danger small">{{ $message }}</div>@enderror

                <div class="col-12 format-section" data-format="custom">
                    <div class="border rounded p-3">
                        <p class="small mb-3" style="color:var(--muted-text)">Pour un service absent de la liste : choisissez la requête que le service attend. Le corps est un modèle où chaque <code>@{{ variable }}</code> est remplacée par la valeur de l'événement.</p>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label" for="custom_method">Méthode</label>
                                <select id="custom_method" name="custom_method" class="form-select" disabled>
                                    @foreach(['POST', 'PUT'] as $method)
                                        <option @selected(old('custom_method', $custom['method'] ?? 'POST') === $method)>{{ $method }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="custom_content_type">Type de contenu</label>
                                <select id="custom_content_type" name="custom_content_type" class="form-select" disabled>
                                    @foreach(['json' => 'JSON', 'form' => 'Formulaire (clé=valeur)', 'text' => 'Texte brut'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('custom_content_type', $custom['content_type'] ?? 'json') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="custom_body">Corps du message</label>
                                <textarea id="custom_body" name="custom_body" rows="6" maxlength="5000" disabled spellcheck="false"
                                          class="form-control font-monospace @error('custom_body') is-invalid @enderror"
                                          placeholder='{"text": "@{{ title }} : @{{ text }}"}'>{{ old('custom_body', $custom['body'] ?? '') }}</textarea>
                                @error('custom_body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">JSON : un objet, par exemple <code>{"text": "@{{ summary }}"}</code>. Une valeur ne peut pas casser le JSON : elle est toujours encodée.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">En-têtes (jeton d'accès, clé d'API...)</label>
                                @php $headerRows = old('header_names') ? array_values(array_filter(old('header_names'))) : array_keys($custom['headers'] ?? []); @endphp
                                @for($i = 0; $i < min(10, count($headerRows) + 2); $i++)
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4"><input type="text" name="header_names[]" class="form-control font-monospace" maxlength="64" disabled placeholder="Authorization" value="{{ $headerRows[$i] ?? '' }}"></div>
                                        <div class="col-md-8"><input type="password" name="header_values[]" class="form-control font-monospace" maxlength="500" disabled autocomplete="off"
                                                   placeholder="{{ isset($headerRows[$i]) && isset($custom['headers'][$headerRows[$i]]) ? '(inchangé)' : 'Bearer ...' }}"></div>
                                    </div>
                                @endfor
                                @error('headers')<div class="text-danger small">{{ $message }}</div>@enderror
                                <div class="form-text">Les valeurs sont chiffrées et ne sont plus réaffichées : laissez vide pour les garder.</div>
                            </div>
                            <div class="col-12">
                                <details>
                                    <summary class="small">Variables disponibles</summary>
                                    <table class="table table-sm small mt-2 mb-0">
                                        @foreach(\App\Services\Webhook\EventMessage::VARIABLES as $variable => $meaning)
                                            <tr><td><code>{{ '{' . '{ ' . $variable . ' }' . '}' }}</code></td><td>{{ $meaning }}</td></tr>
                                        @endforeach
                                    </table>
                                </details>
                            </div>
                        </div>
                    </div>
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

@if($receivesPayload)
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
@endif
@endunless

@push('footer-scripts')
<script>
(function () {
    var format = document.getElementById('format');
    var url = document.getElementById('url');
    var defaultPlaceholder = url.placeholder;
    function sync() {
        var option = format.options[format.selectedIndex];
        document.getElementById('names-option').style.display = option.dataset.names === '1' ? '' : 'none';
        var needsUrl = option.dataset.url !== '0';
        document.getElementById('url-group').style.display = needsUrl ? '' : 'none';
        url.required = needsUrl;
        url.placeholder = option.dataset.example || defaultPlaceholder;
        document.querySelectorAll('.format-section').forEach(function (section) {
            var active = section.dataset.format === format.value;
            section.style.display = active ? '' : 'none';
            section.querySelectorAll('input, select, textarea').forEach(function (field) { field.disabled = !active; });
        });
    }
    format.addEventListener('change', sync);
    sync();
})();
</script>
@endpush

@endsection
