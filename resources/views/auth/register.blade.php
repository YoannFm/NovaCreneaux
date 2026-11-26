@extends('layouts.auth')
@section('title', 'Inscription')
@section('heading', 'Créer un compte')
@section('subtitle', 'Les champs marqués d\'un astérisque sont obligatoires.')

@section('content')
        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Nom complet *</label>
                <input id="name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name') }}" required autofocus autocomplete="name">
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Adresse e-mail *</label>
                <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required autocomplete="username"
                       placeholder="{{ \App\Support\Customization::get('email_placeholder') }}">
            </div>

            <div class="mb-3">
                <label for="classe" class="form-label">{{ \App\Support\Customization::get('group_label') }}</label>
                <input id="classe" type="text" name="classe" maxlength="{{ \App\Models\User::CLASSE_MAX }}" class="form-control @error('classe') is-invalid @enderror"
                       value="{{ old('classe') }}" placeholder="{{ \App\Support\Customization::get('group_placeholder') }}">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe *</label>
                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="new-password" placeholder="••••••••">
                <div class="auth-note mt-1">Au moins {{ \App\Support\Customization::passwordMinLength() }} caractères.</div>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Confirmer le mot de passe *</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control"
                       required autocomplete="new-password" placeholder="••••••••">
            </div>

            @if(!empty(strip_tags($terms ?? '')))
                <div class="mb-4">
                    <div class="border rounded p-3 mb-2" style="max-height:160px;overflow-y:auto;font-size:.82rem;">
                        {!! \App\Support\HtmlSanitizer::clean($terms) !!}
                    </div>
                    <div class="form-check">
                        <input class="form-check-input @error('terms_accepted') is-invalid @enderror"
                               type="checkbox" name="terms_accepted" id="terms_accepted" value="1" @checked(old('terms_accepted')) required>
                        <label class="form-check-label" for="terms_accepted">J'accepte les conditions d'utilisation</label>
                    </div>
                </div>
            @endif

            <button type="submit" class="btn-auth">
                <i class="bi bi-person-plus"></i> S'inscrire
            </button>
        </form>

        <div class="auth-links">
            <a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Déjà un compte ? Se connecter</a>
        </div>
@endsection
