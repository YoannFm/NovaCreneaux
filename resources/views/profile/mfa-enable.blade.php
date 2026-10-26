@extends('layouts.app')
@section('content')
<div class="row justify-content: center">
    <div class="col-lg-7 col-xl-6 mx-auto">
        <h2 class="mb-4"><i class="bi bi-shield-lock me-2"></i>Activer la double authentification</h2>

        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-phone me-1"></i> Etape 1 - Configurer votre application</div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Utilisez une application d'authentification (Google Authenticator, Aegis, Authy, etc.)
                    et scannez le QR code ou entrez la cle manuellement.
                </p>

                {{-- QR code via qrserver.com (simple, ne transmet que l'URL TOTP sans donnee sensible) --}}
                <div class="text-center mb-4">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($otpauthUrl) }}"
                         alt="QR Code MFA" width="200" height="200"
                         style="border:4px solid var(--accent);border-radius:8px;image-rendering:pixelated;">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted text-uppercase">Cle secrete (saisie manuelle)</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" value="{{ $secret }}"
                               id="mfa-secret-input" readonly style="letter-spacing:.15em;">
                        <button class="btn btn-outline-secondary" type="button" onclick="
                            navigator.clipboard.writeText('{{ $secret }}');
                            this.innerHTML='<i class=\'bi bi-check-lg\'></i>';
                            setTimeout(()=>this.innerHTML='<i class=\'bi bi-clipboard\'></i>',1500);">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-check2-circle me-1"></i> Etape 2 - Confirmer le code</div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}</div>
                @endif

                <p class="text-muted small mb-3">
                    Entrez le code a 6 chiffres affiche dans votre application pour confirmer la configuration.
                </p>

                <form method="POST" action="{{ route('mfa.enable') }}">
                    @csrf
                    <div class="mb-4">
                        <label for="code" class="form-label">Code de verification</label>
                        <input type="text" name="code" id="code"
                               class="form-control font-monospace @error('code') is-invalid @enderror"
                               inputmode="numeric" pattern="\d{6}" maxlength="6"
                               autocomplete="one-time-code" autofocus
                               placeholder="000000"
                               style="max-width:200px;font-size:1.3rem;letter-spacing:.3em;text-align:center;">
                        @error('code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-check me-1"></i> Activer la MFA
                        </button>
                        <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
