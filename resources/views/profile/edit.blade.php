@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <h2 class="mb-4"><i class="bi bi-person-circle me-2"></i>Mon profil</h2>

        {{-- Informations --}}
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person me-1"></i> Informations personnelles</div>
            <div class="card-body">
                @if(session('status') === 'profile-updated')
                    <div class="alert alert-success alert-dismissible">
                        <i class="bi bi-check-circle me-1"></i> Profil mis a jour.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label">Nom complet</label>
                        @if(setting('allow_name_change','1') === '1')
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user->name) }}" required>
                            @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        @else
                            <input type="text" class="form-control" value="{{ $user->name }}" disabled>
                            <div class="form-text">La modification du nom est desactivee par l'administrateur.</div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adresse email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $user->email) }}" required>
                        @error('email')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Classe</label>
                        <input type="text" name="classe" class="form-control" placeholder="ex: 2nde A"
                               value="{{ old('classe', $user->classe) }}">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Enregistrer
                    </button>
                </form>
            </div>
        </div>

        {{-- Mot de passe --}}
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-key me-1"></i> Mot de passe</div>
            <div class="card-body">
                @if(session('status') === 'password-updated')
                    <div class="alert alert-success alert-dismissible">
                        <i class="bi bi-check-circle me-1"></i> Mot de passe mis a jour.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">Mot de passe actuel</label>
                        <input type="password" name="current_password" class="form-control @error('current_password','updatePassword') is-invalid @enderror" required>
                        @error('current_password','updatePassword')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="password" class="form-control @error('password','updatePassword') is-invalid @enderror" required>
                        @error('password','updatePassword')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirmer le nouveau mot de passe</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Mettre a jour
                    </button>
                </form>
            </div>
        </div>

        {{-- Preferences de theme --}}
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-palette me-1"></i> Preferences de theme</div>
            <div class="card-body">
                @if(session('status') === 'theme-updated')
                    <div class="alert alert-success alert-dismissible">
                        <i class="bi bi-check-circle me-1"></i> Preferences de theme mises a jour.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                <form method="POST" action="{{ route('profile.theme') }}">
                    @csrf @method('PATCH')
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Couleur principale personnalisee</label>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <input type="color" name="accent_color" id="accent_color"
                                   class="form-control form-control-color"
                                   value="{{ $user->accent_color ?: setting('theme_accent','#efb703') }}"
                                   style="width:60px;height:38px;cursor:pointer;">
                            <input type="text" id="accent_hex" class="form-control font-monospace"
                                   value="{{ $user->accent_color ?: setting('theme_accent','#efb703') }}"
                                   maxlength="7" style="max-width:120px;">
                            @if($user->accent_color)
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset-accent">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reinitialiser
                            </button>
                            @endif
                        </div>
                        <div class="form-text">Laissez la valeur par defaut pour utiliser la couleur globale.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Enregistrer
                    </button>
                </form>
            </div>
        </div>

        {{-- Double authentification (MFA) --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-shield-lock me-1"></i> Double authentification (MFA)</span>
                @if($user->hasAnyMfa())
                    <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Activee</span>
                @else
                    <span class="badge bg-secondary"><i class="bi bi-shield me-1"></i>Desactivee</span>
                @endif
            </div>
            <div class="card-body">
                @foreach(['mfa-totp-enabled'=>'Application TOTP activee.','mfa-totp-disabled'=>'Application TOTP desactivee.',
                          'mfa-email-enabled'=>'MFA par email activee.','mfa-email-disabled'=>'MFA par email desactivee.',
                          'mfa-sms-enabled'=>'MFA par SMS activee.','mfa-sms-disabled'=>'MFA par SMS desactivee.',
                          'mfa-webauthn-added'=>'Cle physique ajoutee.','mfa-webauthn-removed'=>'Cle physique supprimee.'] as $key => $msg)
                    @if(session('status') === $key)
                        <div class="alert alert-{{ str_contains($key,'disabled') || str_contains($key,'removed') ? 'warning' : 'success' }} alert-dismissible py-2">
                            <i class="bi bi-check-circle me-1"></i>{{ $msg }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                @endforeach

                {{-- TOTP --}}
                <div class="d-flex align-items-start justify-content-between py-3 border-bottom">
                    <div>
                        <div class="fw-semibold small d-flex align-items-center gap-2">
                            <i class="bi bi-phone"></i> Application d'authentification (TOTP)
                            @if($user->hasTotp())
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Actif</span>
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:.78rem;">Google Authenticator, Aegis, Authy… Code generee toutes les 30 s.</div>
                    </div>
                    <div class="flex-shrink-0 ms-3">
                        @if($user->hasTotp())
                            <div class="d-flex gap-1 flex-wrap">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#totp-regen-form" title="Régénérer les codes de récupération">
                                    <i class="bi bi-key"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#totp-disable-form">
                                    <i class="bi bi-shield-x"></i> Désactiver
                                </button>
                            </div>
                            <div class="collapse mt-2" id="totp-regen-form">
                                <p class="text-muted" style="font-size:.78rem;">Régénérer des codes de récupération (les anciens seront invalidés).</p>
                                <form method="POST" action="{{ route('mfa.recovery-codes.regenerate') }}">
                                    @csrf
                                    <input type="password" name="password" class="form-control form-control-sm mb-1" placeholder="Mot de passe" required style="max-width:200px;">
                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-arrow-repeat me-1"></i>Régénérer
                                    </button>
                                </form>
                            </div>
                            <div class="collapse mt-2" id="totp-disable-form">
                                <form method="POST" action="{{ route('mfa.totp.disable') }}">
                                    @csrf @method('DELETE')
                                    <input type="password" name="password" class="form-control form-control-sm mb-1" placeholder="Mot de passe" required style="max-width:200px;">
                                    <button type="submit" class="btn btn-sm btn-danger">Confirmer</button>
                                </form>
                            </div>
                        @else
                            <a href="{{ route('mfa.totp.enable.form') }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Activer
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Email OTP --}}
                <div class="d-flex align-items-start justify-content-between py-3 border-bottom">
                    <div>
                        <div class="fw-semibold small d-flex align-items-center gap-2">
                            <i class="bi bi-envelope"></i> Email (code OTP)
                            @if($user->hasEmailMfa())
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Actif</span>
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:.78rem;">Code a 6 chiffres envoye a {{ $user->email }}</div>
                    </div>
                    <div class="flex-shrink-0 ms-3">
                        @if($user->hasEmailMfa())
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#email-disable-form">
                                <i class="bi bi-shield-x"></i> Desactiver
                            </button>
                            <div class="collapse mt-2" id="email-disable-form">
                                <form method="POST" action="{{ route('mfa.email.disable') }}">
                                    @csrf @method('DELETE')
                                    <input type="password" name="password" class="form-control form-control-sm mb-1" placeholder="Mot de passe" required style="max-width:200px;">
                                    <button type="submit" class="btn btn-sm btn-danger">Confirmer</button>
                                </form>
                            </div>
                        @else
                            <form method="POST" action="{{ route('mfa.email.enable') }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-circle"></i> Activer
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- SMS OTP --}}
                <div class="d-flex align-items-start justify-content-between py-3 border-bottom">
                    <div>
                        <div class="fw-semibold small d-flex align-items-center gap-2">
                            <i class="bi bi-chat-dots"></i> SMS (code OTP)
                            @if($user->hasSmsMfa())
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Actif</span>
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:.78rem;">
                            @if($user->hasSmsMfa())
                                Numero : {{ $user->mfa_phone }}
                            @else
                                Code envoye par SMS sur votre telephone.
                            @endif
                        </div>
                    </div>
                    <div class="flex-shrink-0 ms-3">
                        @if($user->hasSmsMfa())
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#sms-disable-form">
                                <i class="bi bi-shield-x"></i> Desactiver
                            </button>
                            <div class="collapse mt-2" id="sms-disable-form">
                                <form method="POST" action="{{ route('mfa.sms.disable') }}">
                                    @csrf @method('DELETE')
                                    <input type="password" name="password" class="form-control form-control-sm mb-1" placeholder="Mot de passe" required style="max-width:200px;">
                                    <button type="submit" class="btn btn-sm btn-danger">Confirmer</button>
                                </form>
                            </div>
                        @else
                            <a href="{{ route('mfa.sms.setup') }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Activer
                            </a>
                        @endif
                    </div>
                </div>

                {{-- WebAuthn --}}
                <div class="pt-3">
                    <div class="fw-semibold small d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-usb-symbol"></i> Cle physique (WebAuthn / FIDO2)
                        @if($user->hasWebAuthn())
                            <span class="badge bg-success-subtle text-success border border-success-subtle">{{ count($user->webAuthnCredentials()) }} cle(s)</span>
                        @endif
                    </div>
                    <div class="text-muted mb-2" style="font-size:.78rem;">YubiKey, Touch ID, Windows Hello, Face ID, etc.</div>

                    @foreach($user->webAuthnCredentials() as $cred)
                    <div class="d-flex align-items-center gap-2 mb-2 p-2" style="background:var(--body-bg);border-radius:6px;">
                        <i class="bi bi-key text-muted"></i>
                        <span style="font-size:.82rem;">{{ $cred['name'] ?? 'Cle' }}</span>
                        <span class="text-muted" style="font-size:.72rem;">{{ substr($cred['created_at'] ?? '', 0, 10) }}</span>
                        <form method="POST" action="{{ route('mfa.webauthn.remove', $cred['id']) }}" class="ms-auto d-inline">
                            @csrf @method('DELETE')
                            <input type="password" name="password" class="form-control form-control-sm" placeholder="Mot de passe" required style="max-width:150px;display:inline-block;width:auto;">
                            <button type="submit" class="btn btn-xs btn-outline-danger btn-sm"
                                    onclick="return confirm('Supprimer cette cle ?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                    @endforeach

                    <a href="{{ route('mfa.webauthn.add') }}" class="btn btn-sm btn-outline-primary mt-1">
                        <i class="bi bi-plus-circle me-1"></i> Ajouter une cle physique
                    </a>
                </div>
            </div>
        </div>

        {{-- Suppression du compte --}}
        @if(setting('allow_account_deletion','1') === '1' && !auth()->user()->is_admin)
        <div class="card border-danger">
            <div class="card-header text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Zone de danger</div>
            <div class="card-body">
                <p class="text-muted small mb-3">La suppression de votre compte est irreversible. Toutes vos donnees seront perdues.</p>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="bi bi-trash me-1"></i> Supprimer mon compte
                </button>
            </div>
        </div>

        <div class="modal fade" id="deleteModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Supprimer le compte</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Etes-vous certain de vouloir supprimer votre compte ? Cette action est irreversible.</p>
                        <form method="POST" action="{{ route('profile.destroy') }}" id="delete-form">
                            @csrf @method('DELETE')
                            <div class="mb-3">
                                <label class="form-label">Confirmez votre mot de passe</label>
                                <input type="password" name="password"
                                       class="form-control @error('password','userDeletion') is-invalid @enderror"
                                       placeholder="Votre mot de passe" required>
                                @error('password','userDeletion')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" form="delete-form" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i> Supprimer definitivement
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('footer-scripts')
<script>
(function () {
    const picker = document.getElementById('accent_color');
    const hex    = document.getElementById('accent_hex');
    const reset  = document.getElementById('btn-reset-accent');
    const defaultAccent = '{{ setting('theme_accent','#efb703') }}';
    if (picker && hex) {
        picker.addEventListener('input', () => { hex.value = picker.value; });
        hex.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) picker.value = hex.value;
        });
    }
    if (reset) {
        reset.addEventListener('click', () => {
            if (picker) picker.value = defaultAccent;
            if (hex)    hex.value    = defaultAccent;
            // mark as reset by setting the hidden name value to empty
            picker.name = '';
            const hidden = document.createElement('input');
            hidden.type = 'hidden'; hidden.name = 'accent_color'; hidden.value = '';
            picker.closest('form').appendChild(hidden);
            reset.disabled = true;
        });
    }
})();
</script>
@endpush
