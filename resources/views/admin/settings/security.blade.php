@extends('layouts.admin')
@php $pageTitle = 'Paramètres - Sécurité'; @endphp
@section('content')

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.security.update') }}">
            @csrf

            @php
                $switches = [
                    'force_email_verification' => [
                        'label' => "Forcer la vérification d'email",
                        'desc'  => "Les utilisateurs dont l'email n'est pas vérifié sont redirigés vers la page de vérification.",
                    ],
                    'require_2fa_admin' => [
                        'label' => 'Double authentification obligatoire pour les administrateurs',
                        'desc'  => 'Les comptes admin devront configurer la 2FA pour accéder au panneau.',
                    ],
                ];
            @endphp

            @foreach($switches as $key => $opt)
                <div class="d-flex justify-content-between align-items-start py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div style="flex:1;padding-right:2rem;">
                        <div class="fw-medium">{{ $opt['label'] }}</div>
                        <div class="text-muted" style="font-size:.8rem;">{{ $opt['desc'] }}</div>
                    </div>
                    <div class="form-check form-switch mb-0" style="flex-shrink:0;">
                        <input class="form-check-input" type="checkbox"
                               id="{{ $key }}" name="{{ $key }}" value="1"
                               style="width:2.5rem;height:1.25rem;cursor:pointer;"
                               {{ (($s[$key] ?? '0') === '1') ? 'checked' : '' }}>
                        <label class="form-check-label visually-hidden" for="{{ $key }}">{{ $opt['label'] }}</label>
                    </div>
                </div>
            @endforeach

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Enregistrer
                </button>
            </div>
        </form>

        {{-- SMS (MFA) --}}
        <hr class="my-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-chat-dots me-1"></i> SMS pour la double authentification</h6>
        <form method="POST" action="{{ route('admin.settings.security.update') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Fournisseur SMS</label>
                <select name="sms_driver" class="form-select" id="sms-driver-select"
                        onchange="updateSmsFields(this.value)" style="max-width:340px;">
                    <option value="disabled"    {{ ($s['sms_driver'] ?? 'disabled') === 'disabled'    ? 'selected' : '' }}>Désactivé</option>
                    <option value="ntfy"        {{ ($s['sms_driver'] ?? '') === 'ntfy'        ? 'selected' : '' }}>ntfy - push notification (gratuit, open-source)</option>
                    <option value="android_gw"  {{ ($s['sms_driver'] ?? '') === 'android_gw'  ? 'selected' : '' }}>Android SMS Gateway (vrai SMS, auto-hébergé)</option>
                    <option value="free_mobile" {{ ($s['sms_driver'] ?? '') === 'free_mobile' ? 'selected' : '' }}>Free Mobile (gratuit, abonnés Free Mobile)</option>
                    <option value="webhook"     {{ ($s['sms_driver'] ?? '') === 'webhook'     ? 'selected' : '' }}>HTTP Webhook (configurable)</option>
                </select>
            </div>

            {{-- ntfy --}}
            <div id="sms-ntfy" style="display:none;">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>ntfy</strong> est open-source et gratuit.
                    Utilisez <a href="https://ntfy.sh" target="_blank" rel="noopener">ntfy.sh</a> (cloud public) ou hébergez
                    votre propre serveur (<code>docker run -p 80:80 binwiederhier/ntfy serve</code>).
                    Les utilisateurs installent l'app ntfy et reçoivent une notification push avec le code.
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label">URL du serveur ntfy</label>
                        <input type="url" name="sms_ntfy_url" class="form-control"
                               value="{{ old('sms_ntfy_url', $s['sms_ntfy_url'] ?? 'https://ntfy.sh') }}"
                               placeholder="https://ntfy.sh">
                        <div class="form-text">Laissez <code>https://ntfy.sh</code> pour le service public gratuit.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Token d'accès (optionnel)</label>
                        <input type="password" name="sms_ntfy_token" class="form-control"
                               value="{{ old('sms_ntfy_token', $s['sms_ntfy_token'] ?? '') }}"
                               placeholder="tk_xxxxxxxx">
                        <div class="form-text">Uniquement si ACL activée.</div>
                    </div>
                </div>
            </div>

            {{-- Android SMS Gateway --}}
            <div id="sms-android-gw" style="display:none;">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    <a href="https://github.com/capcom6/android-sms-gateway" target="_blank" rel="noopener">android-sms-gateway</a>
                    est une app Android open-source qui expose une API HTTP locale.
                    Installez-la sur un téléphone Android connecté au réseau du lycée.
                    Les utilisateurs reçoivent de vrais SMS.
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">URL de l'API</label>
                        <input type="url" name="sms_android_url" class="form-control"
                               value="{{ old('sms_android_url', $s['sms_android_url'] ?? '') }}"
                               placeholder="http://192.168.1.x:8080">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Login</label>
                        <input type="text" name="sms_android_login" class="form-control"
                               value="{{ old('sms_android_login', $s['sms_android_login'] ?? 'user') }}"
                               placeholder="user">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="sms_android_pass" class="form-control"
                               value="{{ old('sms_android_pass', $s['sms_android_pass'] ?? '') }}"
                               placeholder="••••••">
                    </div>
                </div>
            </div>

            {{-- Free Mobile --}}
            <div id="sms-free-mobile" style="display:none;">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Login Free Mobile</label>
                        <input type="text" name="sms_free_mobile_user" class="form-control"
                               value="{{ old('sms_free_mobile_user', $s['sms_free_mobile_user'] ?? '') }}"
                               placeholder="Votre identifiant Free Mobile">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Clé API Free Mobile</label>
                        <input type="password" name="sms_free_mobile_key" class="form-control"
                               value="{{ old('sms_free_mobile_key', $s['sms_free_mobile_key'] ?? '') }}"
                               placeholder="••••••••••••••••">
                        <div class="form-text">Mon Compte → Mes Options → Notifications par SMS → Clé d'identification.</div>
                    </div>
                </div>
            </div>

            {{-- Webhook --}}
            <div id="sms-webhook" style="display:none;">
                <div class="mb-3">
                    <label class="form-label">URL du webhook</label>
                    <input type="url" name="sms_webhook_url" class="form-control"
                           value="{{ old('sms_webhook_url', $s['sms_webhook_url'] ?? '') }}"
                           placeholder="https://api.provider.com/send?to={to}&msg={message}">
                    <div class="form-text">Utilisez <code>{to}</code> pour le numéro et <code>{message}</code> pour le contenu.</div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Méthode HTTP</label>
                        <select name="sms_webhook_method" class="form-select">
                            <option value="GET"  {{ ($s['sms_webhook_method'] ?? 'GET') === 'GET'  ? 'selected' : '' }}>GET</option>
                            <option value="POST" {{ ($s['sms_webhook_method'] ?? 'GET') === 'POST' ? 'selected' : '' }}>POST</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Corps POST (optionnel)</label>
                        <input type="text" name="sms_webhook_body" class="form-control font-monospace"
                               value="{{ old('sms_webhook_body', $s['sms_webhook_body'] ?? '') }}"
                               placeholder='{"to":"{to}","message":"{message}"}'>
                        <div class="form-text">Laissez vide pour form-data standard.</div>
                    </div>
                </div>
            </div>

            <button type="submit" name="_sms_save" value="1" class="btn btn-primary btn-sm">
                <i class="bi bi-save me-1"></i> Enregistrer la configuration SMS/notification
            </button>
        </form>
        <hr class="my-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-diagram-3 me-1"></i> Proxys de confiance</h6>
        <p class="small" style="color:var(--muted-text)">
            Si le site est derrière un proxy (Varnish, par exemple), tous les visiteurs peuvent apparaître avec la même adresse IP :
            les limites de connexion et de l'API sont alors partagées et le journal d'activité est faux.
            Déclarez ici l'adresse du proxy pour que le site lise la vraie adresse du visiteur.
        </p>
        <ul class="small mb-3">
            <li>Adresse vue par le site : <code>{{ $proxy['seen'] }}</code></li>
            <li>Adresse de la connexion (proxy) : <code>{{ $proxy['remote'] }}</code></li>
            <li>En-tête <code>X-Forwarded-For</code> : <code>{{ $proxy['forwarded'] ?: 'absent' }}</code></li>
        </ul>
        @if($proxy['direct'])
            <div class="alert alert-success small">
                <i class="bi bi-check-circle me-1"></i>
                Le site reçoit directement la connexion du visiteur : aucun proxy à déclarer. Laissez ce champ vide.
            </div>
        @endif
        @if($proxy['suggestion'])
            <div class="alert alert-warning small">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Un proxy transmet l'adresse réelle, mais elle est ignorée. Saisissez <code>{{ $proxy['suggestion'] }}</code> ci-dessous.
            </div>
        @endif
        <form method="POST" action="{{ route('admin.settings.security.proxies') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="trusted_proxies">Adresses IP ou réseaux de confiance</label>
                <input type="text" name="trusted_proxies" id="trusted_proxies" maxlength="255"
                       class="form-control font-monospace @error('trusted_proxies') is-invalid @enderror"
                       value="{{ old('trusted_proxies', $s['trusted_proxies'] ?? '') }}" placeholder="127.0.0.1">
                @error('trusted_proxies')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">
                    Séparées par des virgules. Laissez vide si le site n'est pas derrière un proxy.
                    Évitez <code>*</code> : il fait confiance à n'importe quel expéditeur et permet de falsifier son adresse.
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i> Enregistrer</button>
        </form>
    </div>
</div>

<script>
function updateSmsFields(driver) {
    document.getElementById('sms-ntfy').style.display       = driver === 'ntfy'        ? '' : 'none';
    document.getElementById('sms-android-gw').style.display = driver === 'android_gw'  ? '' : 'none';
    document.getElementById('sms-free-mobile').style.display = driver === 'free_mobile' ? '' : 'none';
    document.getElementById('sms-webhook').style.display    = driver === 'webhook'     ? '' : 'none';
}
updateSmsFields('{{ $s['sms_driver'] ?? 'disabled' }}');
</script>
@endsection
