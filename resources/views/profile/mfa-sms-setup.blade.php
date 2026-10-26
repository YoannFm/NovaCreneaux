@extends('layouts.app')

@push('header-styles')
<style>
.setup-step {
    display: flex;
    gap: 1.25rem;
    padding: 1.25rem 0;
    border-bottom: 1px solid rgba(255,255,255,.06);
}
[data-bs-theme="light"] .setup-step { border-bottom-color: rgba(0,0,0,.07); }
.setup-step:last-child { border-bottom: none; padding-bottom: 0; }

.step-num {
    flex-shrink: 0;
    width: 36px; height: 36px;
    border-radius: 50%;
    background: var(--accent);
    color: #1a1a1a;
    font-weight: 800;
    font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
}
.step-title { font-weight: 700; font-size: .95rem; margin-bottom: .35rem; }
.step-desc  { font-size: .83rem; color: var(--text-muted); line-height: 1.55; margin: 0; }

.store-btns { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: .85rem; }
.store-btn {
    display: flex; align-items: center; gap: .6rem;
    background: var(--body-bg);
    border: 1.5px solid rgba(255,255,255,.1);
    border-radius: 8px;
    padding: .5rem .9rem;
    text-decoration: none;
    color: var(--text-primary);
    font-size: .8rem;
    transition: border-color .15s;
    min-width: 130px;
}
[data-bs-theme="light"] .store-btn { border-color: rgba(0,0,0,.12); }
.store-btn:hover { border-color: var(--accent); color: var(--text-primary); text-decoration: none; }
.store-btn i { font-size: 1.3rem; }
.store-btn .sub { font-size: .65rem; color: var(--text-muted); display: block; line-height: 1.1; }
.store-btn .name { font-weight: 700; font-size: .85rem; display: block; }

.qr-grid { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: .85rem; }
.qr-item { text-align: center; }
.qr-item img { border: 3px solid var(--accent); border-radius: 6px; image-rendering: pixelated; }
.qr-item .qr-label { font-size: .72rem; color: var(--text-muted); margin-top: .4rem; font-weight: 600; }

.topic-box {
    background: var(--body-bg);
    border: 2px dashed color-mix(in srgb, var(--accent) 50%, transparent);
    border-radius: 8px;
    padding: .9rem 1rem;
    margin-top: .85rem;
    font-size: .82rem;
    color: var(--text-muted);
    line-height: 1.6;
}
.topic-box strong { color: var(--text-primary); }
</style>
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6 mx-auto">

        @if($driver === 'ntfy')

        {{-- ═══════════════════════════════════════════════════════════════
             NTFY - guide pas à pas
             ═══════════════════════════════════════════════════════════════ --}}
        <h2 class="mb-1"><i class="bi bi-bell me-2"></i>Activer les notifications MFA</h2>
        <p class="text-muted mb-4" style="font-size:.88rem;">
            À chaque connexion, vous recevrez une notification avec un code sur votre téléphone.
        </p>

        @if($errors->any())
            <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-body">

                {{-- ÉTAPE 1 - Télécharger l'app --}}
                <div class="setup-step">
                    <div class="step-num">1</div>
                    <div style="flex:1;">
                        <div class="step-title">Installer l'application ntfy</div>
                        <p class="step-desc">
                            ntfy est une app gratuite et open-source qui reçoit des notifications push.
                            Téléchargez-la sur votre téléphone :
                        </p>

                        {{-- QR codes --}}
                        <div class="qr-grid">
                            <div class="qr-item">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode('https://play.google.com/store/apps/details?id=io.heckel.ntfy') }}"
                                     alt="QR Android" width="110" height="110">
                                <div class="qr-label"><i class="bi bi-android2 me-1"></i>Android</div>
                            </div>
                            <div class="qr-item">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode('https://apps.apple.com/app/ntfy/id1625396347') }}"
                                     alt="QR iPhone" width="110" height="110">
                                <div class="qr-label"><i class="bi bi-apple me-1"></i>iPhone</div>
                            </div>
                        </div>

                        <div class="store-btns mt-3">
                            <a href="https://play.google.com/store/apps/details?id=io.heckel.ntfy" target="_blank" rel="noopener" class="store-btn">
                                <i class="bi bi-google-play" style="color:#34a853;"></i>
                                <div>
                                    <span class="sub">Disponible sur</span>
                                    <span class="name">Google Play</span>
                                </div>
                            </a>
                            <a href="https://apps.apple.com/app/ntfy/id1625396347" target="_blank" rel="noopener" class="store-btn">
                                <i class="bi bi-apple" style="color:var(--text-primary);"></i>
                                <div>
                                    <span class="sub">Disponible sur</span>
                                    <span class="name">App Store</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- ÉTAPE 2 - Créer un topic --}}
                <div class="setup-step">
                    <div class="step-num">2</div>
                    <div style="flex:1;">
                        <div class="step-title">Créer votre topic personnel</div>
                        <p class="step-desc">
                            Dans l'app ntfy, appuyez sur <strong style="color:var(--text-primary);">+</strong>
                            en bas à droite, puis entrez un nom de topic.
                        </p>
                        <div class="topic-box">
                            <strong>Choisissez un topic difficile à deviner</strong>, par exemple :<br>
                            <code style="color:var(--accent-text);font-size:.9rem;">yoann-cdi-7429</code> ou
                            <code style="color:var(--accent-text);font-size:.9rem;">marie-notifications-8831</code><br>
                            <br>
                            ⚠️ N'utilisez pas un mot simple comme <code>test</code> ou votre prénom seul - n'importe qui pourrait s'abonner à ce topic.
                        </div>
                        <p class="step-desc mt-2">
                            Si votre administrateur a configuré un serveur ntfy privé, utilisez l'URL de ce serveur dans les paramètres de l'app (⚙️ → Serveur par défaut).
                        </p>
                    </div>
                </div>

                {{-- ÉTAPE 3 - Saisir le topic + envoyer le code --}}
                <div class="setup-step">
                    <div class="step-num">3</div>
                    <div style="flex:1;">
                        <div class="step-title">Entrer votre topic et recevoir le code de vérification</div>
                        <p class="step-desc mb-3">
                            Saisissez exactement le même topic que celui entré dans l'app ntfy.
                            Un code à 6 chiffres vous sera envoyé pour confirmer que tout fonctionne.
                        </p>
                        <form method="POST" action="{{ route('mfa.sms.setup.post') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Votre topic ntfy</label>
                                <input type="text" name="contact"
                                       class="form-control font-monospace @error('contact') is-invalid @enderror"
                                       placeholder="yoann-cdi-7429"
                                       value="{{ old('contact') }}"
                                       autofocus autocomplete="off"
                                       style="max-width:280px; letter-spacing:.03em;">
                                @error('contact')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i> Recevoir le code de vérification
                                </button>
                                <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        @elseif($driver === 'android_gw')

        {{-- ═══════════════════════════════════════════════════════════════
             Android SMS Gateway
             ═══════════════════════════════════════════════════════════════ --}}
        <h2 class="mb-1"><i class="bi bi-chat-dots me-2"></i>Activer la MFA par SMS</h2>
        <p class="text-muted mb-4" style="font-size:.88rem;">
            Vous recevrez un code par SMS sur votre téléphone à chaque connexion.
        </p>

        @if($errors->any())
            <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <div class="setup-step">
                    <div class="step-num">1</div>
                    <div>
                        <div class="step-title">Entrer votre numéro de téléphone</div>
                        <p class="step-desc mb-3">Un SMS avec un code à 6 chiffres vous sera envoyé pour confirmer votre numéro.</p>
                        <form method="POST" action="{{ route('mfa.sms.setup.post') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Numéro de téléphone</label>
                                <input type="tel" name="contact"
                                       class="form-control @error('contact') is-invalid @enderror"
                                       placeholder="+33 6 12 34 56 78"
                                       value="{{ old('contact') }}" autofocus style="max-width:240px;">
                                @error('contact')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                <div class="form-text">Format international : +33 6 XX XX XX XX</div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i> Envoyer le code de vérification
                                </button>
                                <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @else

        {{-- ═══════════════════════════════════════════════════════════════
             Autres drivers (Free Mobile, webhook)
             ═══════════════════════════════════════════════════════════════ --}}
        <h2 class="mb-1"><i class="bi bi-chat-dots me-2"></i>Activer la MFA par SMS</h2>
        <p class="text-muted mb-4" style="font-size:.88rem;">
            Un code à 6 chiffres vous sera envoyé par SMS à chaque connexion.
        </p>

        @if($errors->any())
            <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" action="{{ route('mfa.sms.setup.post') }}">
                    @csrf
                    <div class="mb-4" style="max-width:280px;">
                        <label class="form-label fw-semibold">Numéro de téléphone</label>
                        <input type="tel" name="contact"
                               class="form-control @error('contact') is-invalid @enderror"
                               placeholder="+33 6 12 34 56 78"
                               value="{{ old('contact') }}" autofocus>
                        @error('contact')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <div class="form-text">Format international recommandé : +33…</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i> Envoyer le code de vérification
                        </button>
                        <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>

        @endif

    </div>
</div>
@endsection
