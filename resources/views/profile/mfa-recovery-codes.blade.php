@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6 mx-auto">
        <h2 class="mb-1"><i class="bi bi-shield-check me-2 text-success"></i>Application TOTP activée</h2>
        <p class="text-muted mb-4">Sauvegardez vos codes de récupération avant de continuer.</p>

        <div class="card border-warning mb-4">
            <div class="card-header bg-warning text-dark fw-bold">
                <i class="bi bi-exclamation-triangle me-1"></i> Codes de récupération - à conserver précieusement
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Ces codes permettent d'accéder à votre compte si vous perdez l'accès à votre application d'authentification.
                    <strong>Chaque code ne peut être utilisé qu'une seule fois.</strong>
                    Conservez-les dans un endroit sûr.
                </p>

                <div class="p-3 mb-3 font-monospace"
                     style="background:var(--body-bg);border:2px solid var(--accent);border-radius:6px;user-select:all;">
                    @foreach($codes as $code)
                        <div style="font-size:.95rem;letter-spacing:.05em;line-height:1.8;">{{ $code }}</div>
                    @endforeach
                </div>

                <div class="d-flex gap-2 flex-wrap mb-4">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="downloadCodes()">
                        <i class="bi bi-download me-1"></i> Télécharger (.txt)
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyCodes(this)">
                        <i class="bi bi-clipboard me-1"></i> Copier
                    </button>
                </div>

                <form method="POST" action="{{ route('mfa.recovery-codes.confirm') }}">
                    @csrf
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirm-saved" required>
                        <label class="form-check-label" for="confirm-saved">
                            <strong>J'ai sauvegardé mes codes de récupération dans un endroit sûr.</strong>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Continuer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('footer-scripts')
<script>
var codes = @json($codes);

function downloadCodes() {
    var text = 'Codes de récupération - {{ setting('app_name','NovaCreneaux') }}\n';
    text += 'Générés le {{ now()->format('d/m/Y H:i') }}\n\n';
    text += codes.join('\n');
    text += '\n\nChaque code est à usage unique. Conservez ce fichier en lieu sûr.';

    var blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'codes-recuperation-mfa.txt';
    a.click();
}

function copyCodes(btn) {
    navigator.clipboard.writeText(codes.join('\n')).then(function() {
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Copié !';
        setTimeout(function() {
            btn.innerHTML = '<i class="bi bi-clipboard me-1"></i> Copier';
        }, 2000);
    });
}
</script>
@endpush
