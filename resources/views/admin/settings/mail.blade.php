@extends('layouts.admin')
@php $pageTitle = 'Paramètres - E-mail'; @endphp
@section('content')

<div class="card">
    <div class="card-body">

        <form method="POST" action="{{ route('admin.settings.mail.update') }}">
            @csrf

            <div class="mb-4 pb-4 border-bottom">
                <div class="row gx-3">
                    <div class="mb-3 col-md-5">
                        <label class="form-label fw-semibold">Méthode d'envoi</label>
                        <select name="mail_driver" id="mail_driver" class="form-select"
                                onchange="toggleMailFields()">
                            <option value="disabled" {{ ($s['mail_driver'] ?? 'disabled') === 'disabled' ? 'selected' : '' }}>Désactivé</option>
                            <option value="sendmail" {{ ($s['mail_driver'] ?? '') === 'sendmail' ? 'selected' : '' }}>SendMail</option>
                            <option value="smtp"     {{ ($s['mail_driver'] ?? '') === 'smtp'     ? 'selected' : '' }}>SMTP</option>
                        </select>
                    </div>
                    <div class="mb-3 col-md-7" id="from-field">
                        <label class="form-label fw-semibold">Adresse expéditeur</label>
                        <input type="email" name="mail_from_address"
                               class="form-control @error('mail_from_address') is-invalid @enderror"
                               value="{{ old('mail_from_address', $s['mail_from_address'] ?? '') }}"
                               placeholder="noreply@example.com">
                        @error('mail_from_address')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            <div id="smtp-fields" class="mb-4 pb-4 border-bottom" style="display:none;">
                <h6 class="fw-semibold mb-3">Configuration SMTP</h6>
                <div class="row gx-3">
                    <div class="mb-3 col-md-8">
                        <label class="form-label">Hôte SMTP</label>
                        <input type="text" name="mail_smtp_host"
                               class="form-control @error('mail_smtp_host') is-invalid @enderror"
                               value="{{ old('mail_smtp_host', $s['mail_smtp_host'] ?? '') }}"
                               placeholder="smtp.gmail.com">
                        @error('mail_smtp_host')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Port</label>
                        <input type="number" name="mail_smtp_port"
                               class="form-control @error('mail_smtp_port') is-invalid @enderror"
                               value="{{ old('mail_smtp_port', $s['mail_smtp_port'] ?? '587') }}"
                               min="1" max="65535">
                        @error('mail_smtp_port')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="row gx-3">
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Chiffrement</label>
                        <select name="mail_smtp_encryption" class="form-select">
                            @foreach(['tls' => 'TLS', 'ssl' => 'SSL', 'starttls' => 'STARTTLS'] as $val => $lbl)
                                <option value="{{ $val }}" {{ old('mail_smtp_encryption', $s['mail_smtp_encryption'] ?? 'tls') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Nom d'utilisateur</label>
                        <input type="text" name="mail_smtp_username" class="form-control"
                               value="{{ old('mail_smtp_username', $s['mail_smtp_username'] ?? '') }}"
                               autocomplete="off">
                    </div>
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Mot de passe</label>
                        @include('admin.settings._secret_input', ['name' => 'mail_smtp_password'])
                    </div>
                </div>
            </div>

            <div class="mb-4 pb-4 border-bottom">
                <h6 class="fw-semibold mb-3">Suppression de comptes</h6>
                <input type="hidden" name="notify_account_deletion" value="0">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="notify_account_deletion" name="notify_account_deletion" value="1"
                           @checked(old('notify_account_deletion', $s['notify_account_deletion'] ?? '1') == '1')>
                    <label class="form-check-label" for="notify_account_deletion">Prévenir par e-mail les comptes supprimés par un administrateur</label>
                </div>
                <div class="form-text">Décochez avant une suppression en masse (fin d'année) pour ne pas envoyer un e-mail à chaque élève. Un utilisateur qui supprime lui-même son compte est toujours prévenu.</div>
            </div>

            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Enregistrer
                </button>
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#testMailModal">
                    <i class="bi bi-send me-1"></i> Envoyer un e-mail de test
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="testMailModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.settings.mail.test') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">E-mail de test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Adresse de destination</label>
                    <input type="email" name="test_email" class="form-control"
                           value="{{ auth()->user()->email }}" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Envoyer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleMailFields() {
    var driver = document.getElementById('mail_driver').value;
    document.getElementById('smtp-fields').style.display = driver === 'smtp' ? '' : 'none';
    document.getElementById('from-field').style.display  = driver === 'disabled' ? 'none' : '';
}
toggleMailFields();
</script>
@endsection
