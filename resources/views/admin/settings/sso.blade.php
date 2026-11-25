@extends('layouts.admin')
@php
    use App\Support\SsoSettings;
    $pageTitle = "Paramètres - Compte de l'établissement";
    $driver = old('sso_driver', $s['sso_driver'] ?? SsoSettings::NONE);
    $field = fn (string $key, string $default = '') => old($key, $s[$key] ?? $default);
@endphp
@section('content')

<p class="text-muted mb-3">
    Permet de se connecter avec le compte de l'établissement (ENT, annuaire) au lieu d'un mot de passe propre au site.
    La connexion par mot de passe reste toujours possible, en secours. Le compte du site est retrouvé par son adresse e-mail.
</p>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.sso.update') }}">
            @csrf

            <div class="mb-4 pb-4 border-bottom"><div class="row gx-3">
                <div class="mb-3 col-md-5">
                    <label class="form-label fw-semibold" for="sso_driver">Méthode</label>
                    <select name="sso_driver" id="sso_driver" class="form-select @error('sso_driver') is-invalid @enderror">
                        @foreach(SsoSettings::DRIVERS as $value => $label)
                            <option value="{{ $value }}" @selected($driver === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3 col-md-7">
                    <label class="form-label fw-semibold" for="sso_label">Texte du bouton</label>
                    <input type="text" name="sso_label" id="sso_label" class="form-control @error('sso_label') is-invalid @enderror"
                           value="{{ $field('sso_label') }}" maxlength="80" placeholder="{{ SsoSettings::DEFAULT_LABEL }}">
                    @error('sso_label')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="sso-callback">Adresse de retour à déclarer auprès du service</label>
                    <input type="text" id="sso-callback" class="form-control font-monospace" value="{{ route('sso.callback') }}" readonly>
                </div>
            </div></div>

            <div class="mb-4 pb-4 border-bottom" data-sso-driver="{{ SsoSettings::OIDC }}">
                <h6 class="fw-semibold mb-3">OpenID Connect</h6>
                <div class="row gx-3">
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="oidc_issuer">Émetteur (issuer)</label>
                        <input type="url" name="oidc_issuer" id="oidc_issuer" class="form-control @error('oidc_issuer') is-invalid @enderror"
                               value="{{ $field('oidc_issuer') }}" placeholder="https://connexion.exemple.fr/realms/etablissement">
                        @error('oidc_issuer')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <div class="form-text">Le site lit <code>/.well-known/openid-configuration</code> à cette adresse.</div>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="oidc_scopes">Portées</label>
                        <input type="text" name="oidc_scopes" id="oidc_scopes" class="form-control @error('oidc_scopes') is-invalid @enderror"
                               value="{{ $field('oidc_scopes') }}" placeholder="{{ SsoSettings::DEFAULT_SCOPES }}">
                        @error('oidc_scopes')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="oidc_client_id">Identifiant du client</label>
                        <input type="text" name="oidc_client_id" id="oidc_client_id" class="form-control @error('oidc_client_id') is-invalid @enderror"
                               value="{{ $field('oidc_client_id') }}" autocomplete="off">
                        @error('oidc_client_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-3 col-md-6">
                        <label class="form-label">Secret du client</label>
                        @include('admin.settings._secret_input', ['name' => 'oidc_client_secret'])
                    </div>
                </div>
            </div>

            <div class="mb-4 pb-4 border-bottom" data-sso-driver="{{ SsoSettings::CAS }}">
                <h6 class="fw-semibold mb-3">CAS</h6>
                <div class="row gx-3">
                    <div class="mb-3 col-md-7">
                        <label class="form-label" for="cas_url">Adresse du serveur CAS</label>
                        <input type="url" name="cas_url" id="cas_url" class="form-control @error('cas_url') is-invalid @enderror"
                               value="{{ $field('cas_url') }}" placeholder="https://cas.exemple.fr/cas">
                        @error('cas_url')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-3 col-md-5">
                        <label class="form-label" for="cas_email_attribute">Attribut de l'adresse e-mail</label>
                        <input type="text" name="cas_email_attribute" id="cas_email_attribute" class="form-control @error('cas_email_attribute') is-invalid @enderror"
                               value="{{ $field('cas_email_attribute') }}" placeholder="{{ SsoSettings::DEFAULT_CAS_ATTRIBUTE }}">
                        @error('cas_email_attribute')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-3 py-2">
                <div>
                    <div class="fw-medium">Créer le compte d'une adresse inconnue</div>
                    <div class="text-muted small">
                        Désactivé : seules les personnes qui ont déjà un compte sur le site peuvent se connecter ainsi.
                        Activé : un compte est créé avec le rôle par défaut (domaines autorisés de Paramètres > Authentification respectés).
                    </div>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="sso_auto_create" name="sso_auto_create" value="1"
                           style="width:2.5rem;height:1.25rem;cursor:pointer;" @checked($field('sso_auto_create', '0') === '1')>
                    <label class="form-check-label visually-hidden" for="sso_auto_create">Créer le compte d'une adresse inconnue</label>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('footer-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('sso_driver');
    function sync() {
        document.querySelectorAll('[data-sso-driver]').forEach(function (block) {
            block.classList.toggle('d-none', block.dataset.ssoDriver !== select.value);
        });
    }
    select.addEventListener('change', sync);
    sync();
});
</script>
@endpush
