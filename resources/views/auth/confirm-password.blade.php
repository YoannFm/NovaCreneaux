@extends('layouts.auth')
@section('title', 'Confirmer votre mot de passe')
@section('icon', 'bi-shield-lock')
@section('heading', 'Confirmer votre mot de passe')
@section('subtitle', 'Cette partie du site est protégée : saisissez de nouveau votre mot de passe pour continuer.')

@section('content')
        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <div class="mb-4">
                <label for="password" class="form-label">Mot de passe</label>
                <input id="password" type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autofocus autocomplete="current-password" placeholder="••••••••">
            </div>

            <button type="submit" class="btn-auth">
                <i class="bi bi-check2"></i> Confirmer
            </button>
        </form>

        @if($ssoEnabled)
            {{-- Un compte cree par la connexion de l'etablissement a un mot de passe aleatoire, jamais communique --}}
            <p class="auth-note">
                <i class="bi bi-info-circle me-1"></i>
                Vous vous connectez avec le compte de l'établissement et n'avez jamais choisi de mot de passe ?
                Déconnectez-vous, puis utilisez « Mot de passe oublié » sur la page de connexion.
            </p>

            <div class="auth-links">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-link-auth"><i class="bi bi-box-arrow-left"></i> Se déconnecter</button>
                </form>
            </div>
        @endif
@endsection
