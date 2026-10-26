@extends('layouts.admin')
@php $pageTitle = 'Créer un utilisateur'; @endphp

@section('content')

<div class="card shadow mb-4">
    <div class="card-body">
        <form action="{{ route('admin.users.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="nameInput">Nom</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror"
                       id="nameInput" name="name" value="{{ old('name') }}" required autofocus>
                @error('name')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="emailInput">Adresse Email</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror"
                       id="emailInput" name="email" value="{{ old('email') }}" required>
                @error('email')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="classeInput">Classe</label>
                <input type="text" class="form-control @error('classe') is-invalid @enderror"
                       id="classeInput" name="classe" value="{{ old('classe') }}" placeholder="Ex: TRM2">
                @error('classe')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="passwordInput">Mot de passe</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror"
                       id="passwordInput" name="password"
                       placeholder="Laisser vide pour générer automatiquement">
                @error('password')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="roleSelect">Rôle</label>
                <select class="form-select @error('role_id') is-invalid @enderror" id="roleSelect" name="role_id">
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id', $defaultRoleId) == $role->id)>
                            {{ $role->label }}{{ $role->is_admin ? ' (admin)' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('role_id')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                           value="1" @checked(old('is_active', true))
                           style="width:2.2rem;height:1.1rem;cursor:pointer;">
                    <label class="form-check-label" for="is_active">Compte actif</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Sauvegarder
            </button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary ms-1">Annuler</a>
        </form>
    </div>
</div>

@endsection
