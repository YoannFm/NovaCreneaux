@extends('layouts.admin')
@php
    $pageTitle = 'Édition de ' . $user->name;
    $ban = $user->activeBan();
@endphp

@section('content')

@if($ban)
<div class="alert alert-warning shadow" role="alert">
    <h5><i class="bi bi-exclamation-triangle me-1"></i> Utilisateur banni</h5>
    <ul class="mb-2">
        <li>Motif : {{ $ban->reason }}</li>
        <li>Date : {{ $ban->banned_at?->format('d/m/Y') ?? $ban->created_at->format('d/m/Y') }}</li>
        @if($ban->banned_until)
            <li>Jusqu'au : {{ $ban->banned_until->format('d/m/Y') }}</li>
        @else
            <li>Banni définitivement</li>
        @endif
    </ul>
</div>
@endif

<div class="row">

    {{-- Colonne gauche : Éditer le profil --}}
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Éditer le profil</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.users.update', $user) }}" method="POST" id="profile-form">
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" for="nameInput">Nom</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                               id="nameInput" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="emailInput">Adresse Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                               id="emailInput" name="email" value="{{ old('email', $user->email) }}" required>
                        @error('email')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="classeInput">Classe</label>
                        <input type="text" class="form-control @error('classe') is-invalid @enderror"
                               id="classeInput" name="classe"
                               value="{{ old('classe', $user->classe) }}" placeholder="Ex: TRM2">
                        @error('classe')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="passwordInput">Mot de passe</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror"
                               id="passwordInput" name="password"
                               placeholder="Laisser vide pour ne pas changer">
                        @error('password')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="roleSelect">Rôle</label>
                        <select class="form-select @error('role_id') is-invalid @enderror"
                                id="roleSelect" name="role_id">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>
                                    {{ $role->label }}{{ $role->is_admin ? ' (admin)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                   value="1" @checked(old('is_active', $user->is_active))
                                   style="width:2.2rem;height:1.1rem;cursor:pointer;">
                            <label class="form-check-label" for="is_active">Compte actif</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Sauvegarder
                    </button>

                    @if($user->id !== auth()->id())
                    {{-- Le formulaire de suppression est plus bas, hors de celui-ci : deux formulaires ne s'imbriquent pas --}}
                    <button type="submit" form="delete-user" class="btn btn-danger ms-1"
                            onclick="return confirm({{ \Illuminate\Support\Js::from('Supprimer définitivement ' . $user->name . ' ?') }})">
                        <i class="bi bi-trash me-1"></i> Supprimer
                    </button>
                    @endif
                </form>

                @if($user->id !== auth()->id())
                <form id="delete-user" method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-none">
                    @csrf @method('DELETE')
                </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Colonne droite : Informations --}}
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Informations de l'utilisateur</h5>
            </div>
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label" for="registerInput">Inscrit le</label>
                    <input type="text" class="form-control" id="registerInput"
                           value="{{ $user->created_at->translatedFormat('d/m/Y \à H:i') }}" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="emailVerifiedInput">Email vérifié</label>
                    @if($user->email_verified_at)
                        <input type="text" class="form-control text-success" id="emailVerifiedInput"
                               value="Oui, le {{ $user->email_verified_at->format('d/m/Y') }}" disabled>
                    @else
                        <div class="input-group">
                            <input type="text" class="form-control text-danger" id="emailVerifiedInput"
                                   value="Non" disabled>
                            <form method="POST" action="{{ route('admin.users.verify', $user) }}">
                                @csrf
                                <button class="btn btn-outline-success" type="submit">Vérifier</button>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label" for="mfaInput">Double authentification (MFA)</label>
                    @if($user->hasAnyMfa())
                        <div class="input-group">
                            <input type="text" class="form-control text-success" id="mfaInput"
                                   value="Oui" disabled>
                            <form method="POST" action="{{ route('admin.users.mfa-disable', $user) }}">
                                @csrf
                                <button class="btn btn-outline-danger" type="submit"
                                        onclick="return confirm({{ \Illuminate\Support\Js::from('Désactiver la MFA de ' . $user->name . ' ?') }})">
                                    Désactiver
                                </button>
                            </form>
                        </div>
                    @else
                        <input type="text" class="form-control text-danger" id="mfaInput"
                               value="Non" disabled>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label" for="reservationsInput">Réservations</label>
                    <input type="text" class="form-control" id="reservationsInput"
                           value="{{ $user->reservations()->count() }} réservation(s)" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="lastLoginInput">Dernière connexion</label>
                    <input type="text" class="form-control" id="lastLoginInput"
                           value="{{ $user->last_login_at ? $user->last_login_at->format('d/m/Y à H:i') : 'Jamais' }}" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="lastPasswordInput">Dernier changement de mot de passe</label>
                    <input type="text" class="form-control" id="lastPasswordInput"
                           value="{{ $user->password_changed_at ? $user->password_changed_at->format('d/m/Y à H:i') : 'Jamais' }}" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="lastIpInput">Adresse IP</label>
                    <input type="text" class="form-control" id="lastIpInput"
                           value="{{ $user->last_ip_address ?? '-' }}" disabled>
                </div>

            </div>
        </div>
    </div>

</div>

{{-- Logs --}}
@if(!$logs->isEmpty())
<div class="card shadow mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Logs</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Action</th>
                        <th scope="col">Détail</th>
                        <th scope="col">IP</th>
                        <th scope="col">Date</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($logs as $log)
                    <tr>
                        <th scope="row">{{ $log->id }}</th>
                        <td>{{ $log->action }}</td>
                        <td style="color:var(--text-muted);font-size:.85rem;">{{ $log->context ?? '-' }}</td>
                        <td style="font-size:.82rem;">{{ $log->ip_address ?? '-' }}</td>
                        <td style="font-size:.82rem;">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
</div>
@endif

@push('header-styles')
<style>
[data-bs-theme="light"] .text-success { color: #198754 !important; }
[data-bs-theme="light"] .text-danger  { color: #dc3545 !important; }
</style>
@endpush

@endsection
