@extends('layouts.auth')
@section('title', 'Mot de passe oublié')
@section('heading', 'Mot de passe oublié')
@section('subtitle', "Indiquez l'adresse e-mail de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.")

@section('content')
        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-4">
                <label for="email" class="form-label">Adresse email</label>
                <input id="email" type="email" name="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}"
                       required autofocus autocomplete="username"
                       placeholder="prenom@rascol.net">
            </div>

            <button type="submit" class="btn-auth">
                <i class="bi bi-envelope"></i> Recevoir le lien
            </button>
        </form>

        <p class="auth-note">
            <i class="bi bi-info-circle me-1"></i>
            Le lien reste valable {{ config('auth.passwords.users.expire') }} minutes. Pensez à regarder dans les courriers indésirables.
        </p>

        <div class="auth-links">
            <a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Retour à la connexion</a>
        </div>
@endsection
