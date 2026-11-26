@extends('layouts.auth')
@section('title', 'Confirmer votre adresse e-mail')
@section('icon', 'bi-envelope-check')
@section('heading', 'Confirmer votre adresse e-mail')
@section('subtitle', 'Pour continuer, confirmez que cette adresse vous appartient.')

@section('content')
        <p style="font-size:.88rem;">
            Un lien de confirmation est envoyé à <strong>{{ auth()->user()->email }}</strong>.
            Cliquez dessus pour accéder au site.
        </p>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn-auth">
                <i class="bi bi-send"></i> Envoyer le lien
            </button>
        </form>

        <p class="auth-note">
            <i class="bi bi-info-circle me-1"></i>
            Le lien reste valable {{ config('auth.verification.expire', 60) }} minutes. Pensez à regarder dans les courriers indésirables.
            Adresse erronée ? Contactez {{ \App\Support\Customization::get('contact_label') }}.
        </p>

        <div class="auth-links">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-link-auth"><i class="bi bi-box-arrow-left"></i> Se déconnecter</button>
            </form>
        </div>
@endsection
