@extends('layouts.auth')
@section('title', 'Code de récupération')
@section('icon', 'bi-key-fill')
@section('heading', 'Code de récupération')
@section('subtitle', "Entrez l'un de vos codes de récupération à usage unique. Ce code sera invalidé après utilisation.")

@section('content')
        <form method="POST" action="{{ route('mfa.challenge.recovery') }}">
            @csrf
            <div class="mb-4">
                <label for="code" class="form-label">Code de récupération</label>
                <input id="code" type="text" name="code"
                       class="form-control font-monospace @error('code') is-invalid @enderror"
                       placeholder="xxxxx-xxxxx"
                       autocomplete="off" autofocus maxlength="20"
                       style="letter-spacing:.1em; text-align:center;">
            </div>
            <button type="submit" class="btn-auth">
                <i class="bi bi-unlock"></i> Utiliser ce code
            </button>
        </form>

        <div class="auth-links">
            <a href="{{ route('mfa.challenge.form') }}"><i class="bi bi-arrow-left me-1"></i>Retour</a>
            <a href="{{ route('login') }}">Se reconnecter</a>
        </div>
@endsection
