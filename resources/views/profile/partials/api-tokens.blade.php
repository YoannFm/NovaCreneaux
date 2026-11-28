{{-- Jetons d'API personnels (voir ProfileApiTokenController). Affiche seulement si le role du compte autorise l'API. --}}
@php
    $apiTokens = $user->tokens()->latest('id')->get();
    $abilities = \App\Services\ApiTokenService::ABILITIES;
    $durations = \App\Services\ApiTokenService::PERSONAL_DURATIONS;
@endphp
<div class="card mb-4" id="api">
    <div class="card-header"><i class="bi bi-plug me-1"></i> Jetons d'API</div>
    <div class="card-body">
        <p class="small text-body-secondary">
            Un jeton permet à un autre logiciel d'agir sur vos réservations, avec les droits que vous choisissez.
            Il ne donne jamais accès aux réservations des autres. Ne le partagez avec personne.
            <a href="{{ route('api.docs') }}" target="_blank" rel="noopener">Documentation de l'API</a>
        </p>

        @if(session('new_token'))
            <div class="alert alert-success">
                <strong><i class="bi bi-key me-1"></i> Jeton créé.</strong> Copiez-le maintenant : il ne sera plus jamais affiché.
                <div class="input-group mt-2">
                    <input type="text" id="new-token" class="form-control font-monospace" value="{{ session('new_token') }}" readonly>
                    <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('new-token').value)">
                        <i class="bi bi-clipboard"></i> Copier
                    </button>
                </div>
            </div>
        @endif
        @if(session('api_success'))
            <div class="alert alert-success py-2">{{ session('api_success') }}</div>
        @endif
        @if(session('api_error'))
            <div class="alert alert-danger py-2">{{ session('api_error') }}</div>
        @endif

        @if($apiTokens->isNotEmpty())
            <div class="table-responsive mb-4">
                <table class="table table-sm align-middle small mb-0">
                    <thead><tr><th>Nom</th><th>Droits</th><th>Dernière utilisation</th><th>Expire le</th><th></th></tr></thead>
                    <tbody>
                    @foreach($apiTokens as $token)
                        <tr>
                            <td><strong>{{ $token->name }}</strong></td>
                            <td>@foreach($token->abilities ?? [] as $ability)<code class="me-1">{{ $ability }}</code>@endforeach</td>
                            <td>{{ $token->last_used_at?->format('d/m/Y H:i') ?? 'jamais' }}</td>
                            <td>
                                @if($token->expires_at?->isPast())
                                    <span class="text-danger">expiré</span>
                                @else
                                    {{ $token->expires_at?->format('d/m/Y') ?? '-' }}
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('profile.api-tokens.destroy', $token->id) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Révoquer ce jeton ? Le logiciel concerné ne pourra plus se connecter.')">
                                        <i class="bi bi-trash"></i> Révoquer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form method="POST" action="{{ route('profile.api-tokens.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="api-token-name">Nom du jeton</label>
                    <input type="text" id="api-token-name" name="name" maxlength="100" value="{{ old('name') }}" required
                           class="form-control @error('name', 'apiToken') is-invalid @enderror" placeholder="Mon application">
                    @error('name', 'apiToken')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="api-token-days">Validité</label>
                    <select id="api-token-days" name="days" class="form-select @error('days', 'apiToken') is-invalid @enderror">
                        @foreach($durations as $days)
                            <option value="{{ $days }}" @selected((int) old('days', 90) === $days)>{{ $days }} jours</option>
                        @endforeach
                    </select>
                    @error('days', 'apiToken')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Droits</label>
                    @foreach($abilities as $key => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="abilities[]" value="{{ $key }}" id="api-ability-{{ $loop->index }}"
                                   @checked(in_array($key, old('abilities', ['resources:read', 'reservations:read'])))>
                            <label class="form-check-label" for="api-ability-{{ $loop->index }}"><code>{{ $key }}</code> - {{ $label }}</label>
                        </div>
                    @endforeach
                    @error('abilities', 'apiToken')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="api-token-password">Votre mot de passe</label>
                    <input type="password" id="api-token-password" name="password" required autocomplete="current-password"
                           class="form-control @error('password', 'apiToken') is-invalid @enderror">
                    @error('password', 'apiToken')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Demandé pour confirmer la création. Maximum {{ \App\Services\ApiTokenService::PERSONAL_LIMIT }} jetons actifs.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-plus-lg me-1"></i> Créer le jeton</button>
        </form>
    </div>
</div>
