@extends('layouts.auth')
@section('title', 'Nouveau mot de passe')
@section('heading', 'Nouveau mot de passe')
@section('subtitle', 'Choisissez le mot de passe que vous utiliserez désormais pour vous connecter.')

@section('content')
        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="mb-3">
                <label for="email" class="form-label">Adresse email</label>
                <input id="email" type="email" name="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $request->email) }}"
                       required autocomplete="username">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Nouveau mot de passe</label>
                <input id="password" type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autofocus autocomplete="new-password" minlength="{{ \App\Support\Customization::passwordMinLength() }}"
                       placeholder="{{ \App\Support\Customization::passwordMinLength() }} caractères minimum">
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Confirmer le mot de passe</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="new-password" minlength="{{ \App\Support\Customization::passwordMinLength() }}">
            </div>

            <button type="submit" class="btn-auth">
                <i class="bi bi-shield-lock"></i> Enregistrer le mot de passe
            </button>
        </form>

        <div class="auth-links">
            <a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Retour à la connexion</a>
            <a href="{{ route('password.request') }}">Demander un nouveau lien</a>
        </div>
@endsection
