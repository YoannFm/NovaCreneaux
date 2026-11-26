@extends('layouts.auth')
@section('title', 'Double authentification')
@section('icon', 'bi-shield-lock-fill')
@section('heading', 'Double authentification')
@section('subtitle', 'Choisissez votre méthode de vérification.')

@push('styles')
<style>
    .auth-card h5, .auth-card .auth-sub { text-align: center; }
    .form-control.code-input { font-size: 1.4rem; letter-spacing: .3rem; text-align: center; }
    .method-tab { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .method-btn { flex: 1; min-width: 80px; padding: .5rem .6rem; background: var(--body-bg);
                  border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent); border-radius: 4px;
                  color: var(--text-muted); font-size: .75rem; font-weight: 600; cursor: pointer;
                  text-align: center; transition: border-color .15s, color .15s; }
    .method-btn:hover, .method-btn.active { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }
    .method-btn i { display: block; font-size: 1.2rem; margin-bottom: .25rem; }
    .method-panel { display: none; }
    .method-panel.active { display: block; }
</style>
@endpush

@section('content')
        @if(session('code_sent'))
        <div class="auth-success">
            <i class="bi bi-check-circle-fill"></i>
            Code envoyé {{ session('code_sent') === 'email' ? 'par e-mail' : 'par SMS' }}. Vérifiez votre boîte.
        </div>
        @endif

        {{-- Method tabs --}}
        @if(count($methods) > 1)
        <div class="method-tab" id="method-tabs">
            @foreach($methods as $m)
            <button type="button" class="method-btn {{ $loop->first ? 'active' : '' }}"
                    data-target="{{ $m }}"
                    onclick="switchMethod('{{ $m }}')">
                <i class="bi {{ match($m) {
                    'totp'     => 'bi-phone',
                    'email'    => 'bi-envelope',
                    'sms'      => 'bi-chat-dots',
                    'webauthn' => 'bi-usb-symbol',
                    default    => 'bi-shield',
                } }}"></i>
                {{ match($m) { 'totp' => 'Application', 'email' => 'E-mail', 'sms' => 'SMS', 'webauthn' => 'Clé physique', default => $m } }}
            </button>
            @endforeach
        </div>
        @endif

        {{-- TOTP panel --}}
        @if(in_array('totp', $methods))
        <div class="method-panel {{ in_array('totp', $methods) && $methods[0] === 'totp' ? 'active' : '' }}" id="panel-totp">
            <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1rem;">
                <i class="bi bi-phone me-1"></i> Entrez le code affiché dans votre application d'authentification.
            </p>
            <form method="POST" action="{{ route('mfa.challenge') }}">
                @csrf
                <input type="hidden" name="method" value="totp">
                <div class="mb-4">
                    <label for="code_totp" class="form-label">Code à 6 chiffres</label>
                    <input id="code_totp" type="text" name="code" inputmode="numeric" pattern="\d{6}"
                           class="form-control code-input" autocomplete="one-time-code" maxlength="6" placeholder="000000">
                </div>
                <button type="submit" class="btn-auth"><i class="bi bi-shield-check"></i> Vérifier</button>
            </form>
        </div>
        @endif

        {{-- Email OTP panel --}}
        @if(in_array('email', $methods))
        <div class="method-panel {{ !in_array('totp', $methods) && $methods[0] === 'email' ? 'active' : '' }}" id="panel-email">
            <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1rem;">
                <i class="bi bi-envelope me-1"></i> Un code vous sera envoyé par e-mail.
            </p>
            @if(!session('code_sent') || session('code_sent') !== 'email')
            <form method="POST" action="{{ route('mfa.challenge.send') }}" class="mb-3">
                @csrf
                <input type="hidden" name="method" value="email">
                <button type="submit" class="btn-secondary-auth">
                    <i class="bi bi-send"></i> Envoyer le code par e-mail
                </button>
            </form>
            @endif
            <form method="POST" action="{{ route('mfa.challenge') }}">
                @csrf
                <input type="hidden" name="method" value="email">
                <div class="mb-4">
                    <label for="code_email" class="form-label">Code reçu par e-mail</label>
                    <input id="code_email" type="text" name="code" inputmode="numeric"
                           class="form-control code-input" maxlength="6" placeholder="000000">
                </div>
                <button type="submit" class="btn-auth"><i class="bi bi-shield-check"></i> Vérifier</button>
            </form>
        </div>
        @endif

        {{-- SMS OTP panel --}}
        @if(in_array('sms', $methods))
        <div class="method-panel {{ count($methods) === 1 && $methods[0] === 'sms' ? 'active' : '' }}" id="panel-sms">
            <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1rem;">
                <i class="bi bi-chat-dots me-1"></i> Un code vous sera envoyé par SMS sur votre téléphone.
            </p>
            @if(!session('code_sent') || session('code_sent') !== 'sms')
            <form method="POST" action="{{ route('mfa.challenge.send') }}" class="mb-3">
                @csrf
                <input type="hidden" name="method" value="sms">
                <button type="submit" class="btn-secondary-auth">
                    <i class="bi bi-send"></i> Envoyer le code par SMS
                </button>
            </form>
            @endif
            <form method="POST" action="{{ route('mfa.challenge') }}">
                @csrf
                <input type="hidden" name="method" value="sms">
                <div class="mb-4">
                    <label for="code_sms" class="form-label">Code reçu par SMS</label>
                    <input id="code_sms" type="text" name="code" inputmode="numeric"
                           class="form-control code-input" maxlength="6" placeholder="000000">
                </div>
                <button type="submit" class="btn-auth"><i class="bi bi-shield-check"></i> Vérifier</button>
            </form>
        </div>
        @endif

        {{-- WebAuthn panel --}}
        @if(in_array('webauthn', $methods))
        <div class="method-panel {{ count($methods) === 1 && $methods[0] === 'webauthn' ? 'active' : '' }}" id="panel-webauthn">
            <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1.25rem;">
                <i class="bi bi-usb-symbol me-1"></i> Utilisez votre clé physique ou l'authentification biométrique.
            </p>
            <form method="POST" action="{{ route('mfa.challenge.webauthn') }}" id="webauthn-form">
                @csrf
                <input type="hidden" name="assertion" id="webauthn-assertion">
                <button type="button" class="btn-auth" id="webauthn-btn" onclick="startWebAuthn()">
                    <i class="bi bi-key"></i> Utiliser ma clé physique
                </button>
                <p id="webauthn-error" style="color:#ef4444;font-size:.8rem;margin-top:.75rem;display:none;"></p>
            </form>
        </div>
        @endif

        <div class="auth-links mt-3" style="gap:1.5rem;">
            <a href="{{ route('login') }}"><i class="bi bi-arrow-left me-1"></i>Retour à la connexion</a>
            @if(in_array('totp', $methods))
            <a href="{{ route('mfa.challenge.recovery.form') }}" style="color:var(--text-muted);">
                <i class="bi bi-key me-1"></i>Code de récupération
            </a>
            @endif
        </div>
@endsection

@push('scripts')
<script>
// Method tabs
function switchMethod(method) {
    document.querySelectorAll('.method-btn').forEach(b => b.classList.toggle('active', b.dataset.target === method));
    document.querySelectorAll('.method-panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + method));
}

// Auto-activate first method tab
(function(){
    @if(count($methods) > 1)
    switchMethod('{{ $methods[0] }}');
    @endif
})();

// WebAuthn
var webAuthnOptions = @json($webAuthnOptions);

function startWebAuthn() {
    if (!webAuthnOptions) { showError('Options WebAuthn non disponibles.'); return; }

    var opts = webAuthnOptions;

    // Decode challenge
    var challenge = base64urlDecode(opts.challenge);

    // Decode allowCredentials ids
    var allowCreds = (opts.allowCredentials || []).map(function(c) {
        return { type: c.type, id: base64urlDecode(c.id) };
    });

    navigator.credentials.get({
        publicKey: {
            challenge:         challenge,
            rpId:              opts.rpId,
            userVerification:  opts.userVerification || 'preferred',
            timeout:           opts.timeout || 60000,
            allowCredentials:  allowCreds,
        }
    }).then(function(credential) {
        var assertion = {
            id:    credential.id,
            rawId: arrayBufferToBase64url(credential.rawId),
            type:  credential.type,
            clientDataJSON:    arrayBufferToBase64url(credential.response.clientDataJSON),
            authenticatorData: arrayBufferToBase64url(credential.response.authenticatorData),
            signature:         arrayBufferToBase64url(credential.response.signature),
            userHandle:        credential.response.userHandle ? arrayBufferToBase64url(credential.response.userHandle) : null,
        };
        document.getElementById('webauthn-assertion').value = JSON.stringify(assertion);
        document.getElementById('webauthn-form').submit();
    }).catch(function(err) {
        showError('Erreur : ' + err.message);
    });
}

function showError(msg) {
    var el = document.getElementById('webauthn-error');
    el.textContent = msg; el.style.display = 'block';
}

function base64urlDecode(b64u) {
    var pad = b64u.length % 4;
    if (pad) b64u += '===='.slice(0, 4 - pad);
    var b64 = b64u.replace(/-/g, '+').replace(/_/g, '/');
    var binary = atob(b64);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes.buffer;
}

function arrayBufferToBase64url(buf) {
    var bytes = new Uint8Array(buf);
    var str = '';
    for (var i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

// Auto-submit WebAuthn if it's the only method
@if(count($methods) === 1 && $methods[0] === 'webauthn')
window.addEventListener('load', function() { setTimeout(startWebAuthn, 500); });
@endif
</script>
@endpush
