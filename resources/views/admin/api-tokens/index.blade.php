@extends('layouts.admin')
@php $pageTitle = 'Intégrations API'; @endphp
@section('content')

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

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="mb-0 small" style="color:var(--muted-text)">
        Un jeton permet à un autre logiciel d'utiliser le site au nom d'un compte, avec les droits choisis.
    </p>
    <a href="{{ route('api.docs') }}" class="btn btn-outline-secondary btn-sm" target="_blank">
        <i class="bi bi-book"></i> Documentation de l'API
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">Nouveau jeton</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.api-tokens.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="user_id">Compte</label>
                    <select name="user_id" id="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                        <option value="">Choisir un compte de service...</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} ({{ $user->email }}) - {{ $user->roleName() }}</option>
                        @endforeach
                    </select>
                    @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Préférez un compte dédié, sans droits d'administrateur : ses limites de réservation s'appliquent au jeton.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="name">Nom de l'intégration</label>
                    <input type="text" name="name" id="name" maxlength="100" value="{{ old('name') }}"
                           class="form-control @error('name') is-invalid @enderror" placeholder="Borne du CDI" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="days">Validité (jours)</label>
                    <input type="number" name="days" id="days" min="1" max="730" value="{{ old('days') }}"
                           class="form-control @error('days') is-invalid @enderror" placeholder="365 par défaut">
                    @error('days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Droits</label>
                    @foreach($abilities as $key => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="abilities[]" value="{{ $key }}" id="ability-{{ $loop->index }}"
                                   @checked(in_array($key, old('abilities', ['resources:read', 'reservations:read'])))>
                            <label class="form-check-label" for="ability-{{ $loop->index }}"><code>{{ $key }}</code> - {{ $label }}</label>
                        </div>
                    @endforeach
                    @error('abilities')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i> Créer le jeton</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Intégration</th>
                        <th>Compte</th>
                        <th>Droits</th>
                        <th>Dernière utilisation</th>
                        <th>Expire le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($tokens as $token)
                    <tr>
                        <td><strong>{{ $token->name }}</strong></td>
                        <td>{{ $token->tokenable?->email ?? '(compte supprimé)' }}</td>
                        <td>@foreach($token->abilities ?? [] as $ability)<code class="me-1">{{ $ability }}</code>@endforeach</td>
                        <td>{{ $token->last_used_at?->format('d/m/Y H:i') ?? 'jamais' }}</td>
                        <td>
                            @php $expires = $token->expires_at ?? $token->created_at->copy()->addMinutes(config('sanctum.expiration')); @endphp
                            <span @class(['text-danger' => $expires->isPast()])>{{ $expires->format('d/m/Y') }}</span>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.api-tokens.destroy', $token->id) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Révoquer ce jeton ? Le logiciel concerné ne pourra plus se connecter.')">
                                    <i class="bi bi-trash"></i> Révoquer
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">Aucun jeton.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
