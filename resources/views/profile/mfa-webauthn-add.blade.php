@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5 mx-auto">
        <h2 class="mb-4"><i class="bi bi-usb-symbol me-2"></i>Ajouter une clé physique</h2>

        <div class="card">
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <p class="text-muted small mb-3">
                    Associez une clé de sécurité physique (YubiKey, etc.), Touch ID ou Windows Hello à votre compte.
                </p>
                <form method="POST" action="{{ route('mfa.webauthn.register') }}" id="webauthn-form">
                    @csrf
                    <input type="hidden" name="attestation" id="attestation-input">
                    <div class="mb-3" style="max-width:280px;">
                        <label class="form-label">Nom de la clé (optionnel)</label>
                        <input type="text" name="key_name" class="form-control" placeholder="Ma YubiKey" maxlength="50">
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-primary" id="register-btn" onclick="startRegistration()">
                            <i class="bi bi-key me-1"></i> Associer la clé
                        </button>
                        <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                    <p id="webauthn-error" class="text-danger small mt-2" style="display:none;"></p>
                    <p id="webauthn-status" class="text-success small mt-2" style="display:none;"></p>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('footer-scripts')
<script>
function startRegistration() {
    var btn = document.getElementById('register-btn');
    btn.disabled = true;
    document.getElementById('webauthn-error').style.display = 'none';

    fetch('{{ route('mfa.webauthn.options') }}', {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    })
    .then(r => r.json())
    .then(opts => {
        var challenge = base64urlDecode(opts.challenge);
        var userId    = base64urlDecode(opts.user.id);

        return navigator.credentials.create({
            publicKey: {
                challenge: challenge,
                rp:        opts.rp,
                user:      { id: userId, name: opts.user.name, displayName: opts.user.displayName },
                pubKeyCredParams: opts.pubKeyCredParams,
                authenticatorSelection: opts.authenticatorSelection,
                timeout:   opts.timeout,
                attestation: opts.attestation,
            }
        });
    })
    .then(credential => {
        var attestation = {
            id:   credential.id,
            type: credential.type,
            clientDataJSON:    arrayBufferToBase64url(credential.response.clientDataJSON),
            attestationObject: arrayBufferToBase64url(credential.response.attestationObject),
        };
        document.getElementById('attestation-input').value = JSON.stringify(attestation);
        document.getElementById('webauthn-status').textContent = 'Clé détectée ! Envoi en cours…';
        document.getElementById('webauthn-status').style.display = 'block';
        document.getElementById('webauthn-form').submit();
    })
    .catch(err => {
        btn.disabled = false;
        var el = document.getElementById('webauthn-error');
        el.textContent = 'Erreur : ' + err.message;
        el.style.display = 'block';
    });
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
</script>
@endpush
