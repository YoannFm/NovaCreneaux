<!DOCTYPE html>
<html lang="fr" id="html-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vérification - {{ setting('app_name','NovaCreneaux') }}</title>
    <script>(function(){var t=localStorage.getItem('nc-theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/admin.css') }}" rel="stylesheet">
    <style>
        :root {
            --accent:       {{ setting('theme_accent', '#efb703') }};
            --accent-hover: color-mix(in srgb, var(--accent) 82%, black);
            --accent-light: color-mix(in srgb, var(--accent) 15%, transparent);
            --body-bg:      {{ setting('theme_body_bg',    '#111113') }};
            --card-bg:      {{ setting('theme_card_bg',    '#212227') }};
            --text-primary: {{ setting('theme_text_color', '#e2e8f0') }};
            --text-muted:   #9ca3af;
        }
        [data-bs-theme="light"] {
            --body-bg:      {{ setting('theme_light_body_bg',    '#f4f5f7') }};
            --card-bg:      {{ setting('theme_light_card_bg',    '#ffffff') }};
            --text-primary: {{ setting('theme_light_text_color', '#1a1a2e') }};
            --text-muted:   #6c757d;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { min-height: 100vh; background: var(--body-bg); color: var(--text-primary);
               display: flex; align-items: center; justify-content: center; padding: 1rem;
               font-family: system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased; }
        .auth-wrap { width: 100%; max-width: 460px; }
        .auth-brand { text-align: center; margin-bottom: 2rem; }
        .auth-brand .brand-name { font-size: 2rem; font-weight: 800; color: var(--accent); text-decoration: none; letter-spacing: -.5px; }
        .auth-brand .brand-sub  { font-size: .78rem; color: var(--text-muted); margin-top: .2rem; text-transform: uppercase; letter-spacing: 1px; }
        .auth-card { background: var(--card-bg); border: 2px solid var(--accent); border-radius: 4px; padding: 2.25rem 2rem; }
        .auth-card h5 { font-size: 1.05rem; font-weight: 700; margin-bottom: .2rem; }
        .auth-sub   { font-size: .8rem; color: var(--text-muted); margin-bottom: 1.5rem; }
        .form-label { font-size: .72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .7px; margin-bottom: .35rem; display: block; }
        .form-control { background: var(--body-bg) !important; border: 2px solid color-mix(in srgb, var(--accent) 40%, transparent) !important;
                        color: var(--text-primary) !important; border-radius: 0 !important; padding: .65rem .9rem; font-size: .9rem; width: 100%; transition: border-color .15s !important; }
        .form-control:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 3px var(--accent-light) !important; outline: none !important; }
        .form-control.is-invalid { border-color: #ef4444 !important; }
        .form-control.code-input { font-size: 1.4rem; letter-spacing: .3rem; text-align: center; }
        .btn-auth { width: 100%; padding: .72rem; background: var(--accent); border: none; border-radius: 0;
                    color: #212227; font-weight: 800; font-size: .85rem; text-transform: uppercase; letter-spacing: .8px;
                    cursor: pointer; transition: background .15s; display: flex; align-items: center; justify-content: center; gap: .5rem; }
        .btn-auth:hover { background: var(--accent-hover); }
        .btn-secondary-auth { width: 100%; padding: .65rem; background: transparent;
                              border: 2px solid color-mix(in srgb, var(--accent) 40%, transparent);
                              border-radius: 0; color: var(--text-muted); font-weight: 600; font-size: .82rem;
                              cursor: pointer; transition: border-color .15s, color .15s; display: flex; align-items: center; justify-content: center; gap: .5rem; }
        .btn-secondary-auth:hover { border-color: var(--accent); color: var(--accent); }
        .auth-error   { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3); border-radius: 3px; color: #ef4444; font-size: .82rem; padding: .65rem .9rem; margin-bottom: 1.25rem; display: flex; align-items: flex-start; gap: .5rem; }
        .auth-success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.3); border-radius: 3px; color: #22c55e; font-size: .82rem; padding: .65rem .9rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: .5rem; }
        .method-tab { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .method-btn { flex: 1; min-width: 80px; padding: .5rem .6rem; background: var(--body-bg);
                      border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent); border-radius: 4px;
                      color: var(--text-muted); font-size: .75rem; font-weight: 600; cursor: pointer;
                      text-align: center; transition: border-color .15s, color .15s; }
        .method-btn:hover, .method-btn.active { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }
        .method-btn i { display: block; font-size: 1.2rem; margin-bottom: .25rem; }
        .method-panel { display: none; }
        .method-panel.active { display: block; }
        .mfa-icon { width: 56px; height: 56px; border-radius: 50%; background: color-mix(in srgb, var(--accent) 15%, transparent);
                    display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--accent); margin: 0 auto 1.5rem; }
        .auth-links { display: flex; justify-content: center; margin-top: 1.25rem; font-size: .8rem; }
        .auth-links a { color: var(--text-muted); text-decoration: none; }
        .auth-links a:hover { color: var(--accent); }
        .theme-btn { position: fixed; top: 1rem; right: 1rem; background: var(--card-bg);
                     border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);
                     border-radius: 50%; width: 36px; height: 36px;
                     display: flex; align-items: center; justify-content: center;
                     cursor: pointer; color: var(--text-muted); font-size: .9rem; transition: color .15s, border-color .15s; }
        .theme-btn:hover { color: var(--accent); border-color: var(--accent); }
    </style>
</head>
<body>

<button class="theme-btn" id="theme-toggle" title="Changer le thème">
    <i class="bi bi-sun-fill" id="theme-icon"></i>
</button>

<div class="auth-wrap">
    <div class="auth-brand">
        <a href="{{ url('/') }}" class="brand-name">{{ setting('app_name','NovaCreneaux') }}</a>
        <div class="brand-sub">{{ setting('app_description', 'Réservation de créneaux CDI') }}</div>
    </div>

    <div class="auth-card">
        <div class="mfa-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <h5 class="text-center">Double authentification</h5>
        <div class="auth-sub text-center">Choisissez votre méthode de vérification.</div>

        @if($errors->any())
        <div class="auth-error">
            <i class="bi bi-exclamation-circle-fill flex-shrink-0" style="margin-top:.1rem;"></i>
            <span>{{ $errors->first() }}</span>
        </div>
        @endif
        @if(session('error'))
        <div class="auth-error">
            <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
        @endif
        @if(session('code_sent'))
        <div class="auth-success">
            <i class="bi bi-check-circle-fill"></i>
            Code envoyé {{ session('code_sent') === 'email' ? 'par email' : 'par SMS' }}. Vérifiez votre boîte.
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
                {{ match($m) { 'totp' => 'Application', 'email' => 'Email', 'sms' => 'SMS', 'webauthn' => 'Clé physique', default => $m } }}
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
                <i class="bi bi-envelope me-1"></i> Un code vous sera envoyé par email.
            </p>
            @if(!session('code_sent') || session('code_sent') !== 'email')
            <form method="POST" action="{{ route('mfa.challenge.send') }}" class="mb-3">
                @csrf
                <input type="hidden" name="method" value="email">
                <button type="submit" class="btn-secondary-auth">
                    <i class="bi bi-send"></i> Envoyer le code par email
                </button>
            </form>
            @endif
            <form method="POST" action="{{ route('mfa.challenge') }}">
                @csrf
                <input type="hidden" name="method" value="email">
                <div class="mb-4">
                    <label for="code_email" class="form-label">Code reçu par email</label>
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
    </div>
</div>

<script src="{{ asset('assets/vendor/admin.js') }}" defer></script>
<script>
// Theme toggle
(function(){
    var root=document.getElementById('html-root'),icon=document.getElementById('theme-icon');
    function apply(){var t=root.getAttribute('data-bs-theme')||'dark';icon.className=t==='dark'?'bi bi-sun-fill':'bi bi-moon-fill';}
    apply();
    document.getElementById('theme-toggle').addEventListener('click',function(){
        var next=root.getAttribute('data-bs-theme')==='dark'?'light':'dark';
        root.setAttribute('data-bs-theme',next);localStorage.setItem('nc-theme',next);apply();
    });
})();

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
</body>
</html>
