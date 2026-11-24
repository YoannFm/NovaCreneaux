@extends('layouts.auth')
@section('title', 'Connexion')
@section('heading', 'Connexion')
@section('subtitle', 'Entrez vos identifiants pour accéder à votre espace.')

@section('content')
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Adresse email</label>
                <input id="email" type="email" name="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}"
                       required autofocus autocomplete="username"
                       placeholder="prenom@rascol.net">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Mot de passe</label>
                <input id="password" type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="current-password"
                       placeholder="••••••••">
            </div>

            <div class="form-check mb-4">
                <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                <label for="remember_me" class="form-check-label">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn-auth">
                <i class="bi bi-box-arrow-in-right"></i> Se connecter
            </button>
        </form>

        <div class="auth-links">
            @if(Route::has('password.request'))
                <a href="{{ route('password.request') }}">Mot de passe oublié ?</a>
            @else
                <span></span>
            @endif
            @if(setting('registration_open','0') === '1')
                <a href="{{ route('register') }}" class="main-link">Créer un compte &rarr;</a>
            @endif
        </div>
@endsection
