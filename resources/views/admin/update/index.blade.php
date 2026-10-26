@extends('layouts.admin')
@php
    $pageTitle = 'Mise à jour';
    $release = $status['release'];
@endphp

@push('header-styles')
<style>
    .update-changelog { max-height: 16rem; overflow: auto; }
    .update-changelog h1, .update-changelog h2, .update-changelog h3 { font-size: 1rem; font-weight: 600; }
    .update-changelog > :last-child { margin-bottom: 0; }
</style>
@endpush

@push('footer-scripts')
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var feedback = document.getElementById('update-feedback');

    function show(type, text) {
        feedback.className = 'alert alert-' + type + ' mt-3';
        feedback.textContent = text;
    }

    function post(url) {
        return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (body) {
                    if (!response.ok) { throw new Error(body.message || 'Erreur ' + response.status); }
                    return body;
                });
            });
    }

    // data-update-route : une ou plusieurs etapes enchainees (separees par une virgule)
    document.querySelectorAll('[data-update-route]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.dataset.confirm && !confirm(button.dataset.confirm)) { return; }
            var steps = button.dataset.updateRoute.split(',');
            document.querySelectorAll('[data-update-route]').forEach(function (b) { b.disabled = true; });
            show('info', button.dataset.wait || 'Veuillez patienter, ne fermez pas cette page...');

            steps.reduce(function (chain, url) {
                return chain.then(function () { return post(url); }).then(function (body) { show('info', body.message); });
            }, Promise.resolve())
                .then(function () { window.location.reload(); })
                .catch(function (error) {
                    show('danger', error.message);
                    document.querySelectorAll('[data-update-route]').forEach(function (b) { b.disabled = false; });
                });
        });
    });
})();
</script>
@endpush

@section('content')
<div class="card shadow mb-4">
    <div class="card-body">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <div class="text-muted small text-uppercase">Version installée</div>
                <div class="fs-2 fw-bold" style="color:var(--accent-text)">v{{ $status['current'] }}</div>
            </div>
            <form method="POST" action="{{ route('admin.update.check') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i> Vérifier les mises à jour
                </button>
            </form>
        </div>

        @unless($status['signing_configured'])
            <div class="alert alert-danger">
                <i class="bi bi-shield-exclamation me-1"></i>
                <strong>Mises à jour désactivées.</strong> La clé publique de signature n'est pas configurée
                (<code>update_public_key</code> dans <code>config/nova.php</code>) ou l'extension PHP <code>sodium</code> est absente.
                Sans elle, le site ne peut pas vérifier qu'une archive vient bien de vous.
            </div>
        @endunless

        @if($status['pending'])
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Mise à jour à finaliser.</strong> Les fichiers de la version {{ $status['pending']['to'] }} sont installés, mais les
                migrations et le nettoyage des caches ne sont pas terminés. Le site reste en maintenance pour les visiteurs.
            </div>
            <button type="button" class="btn btn-primary" data-update-route="{{ route('admin.update.finalize') }}">
                <i class="bi bi-check2-circle me-1"></i> Terminer la mise à jour
            </button>

        @elseif($status['error'])
            <div class="alert alert-danger mb-0"><i class="bi bi-wifi-off me-1"></i> {{ $status['error'] }}</div>

        @elseif($status['has_update'])
            <h5>Version {{ $release['version'] }} disponible</h5>
            @if($release['published_at'])
                <p class="text-muted small">Publiée le {{ \Illuminate\Support\Carbon::parse($release['published_at'])->translatedFormat('j F Y') }}</p>
            @endif

            @if(trim($release['changelog']) !== '')
                <div class="update-changelog border rounded p-3 mb-3">{!! \App\Support\SafeMarkdown::toHtml($release['changelog']) !!}</div>
            @endif

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Avant de continuer :</strong> faites une sauvegarde de la base de données (section <a href="#sauvegardes">Sauvegardes</a> plus bas). Les fichiers remplacés sont
                sauvegardés automatiquement, mais pas la base, et les migrations ne sont pas réversibles.
                Le site passera en maintenance quelques instants pour les visiteurs.
            </div>

            @if($status['downloaded'])
                <p>L'archive est téléchargée. Elle sera vérifiée de nouveau (signature) avant l'installation.</p>
                <button type="button" class="btn btn-success" data-update-route="{{ route('admin.update.install') }},{{ route('admin.update.finalize') }}"
                        data-confirm="Installer la version {{ $release['version'] }} maintenant ? Le site passe en maintenance pendant l'opération."
                        data-wait="Installation en cours (une à deux minutes)... Ne fermez pas cette page.">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Installer la version {{ $release['version'] }}
                </button>
            @else
                <button type="button" class="btn btn-primary" data-update-route="{{ route('admin.update.download') }}"
                        data-wait="Téléchargement et vérification de la signature..." @disabled(!$status['signing_configured'])>
                    <i class="bi bi-cloud-download me-1"></i> Télécharger la version {{ $release['version'] }}
                </button>
            @endif

        @else
            <div class="alert alert-success mb-0">
                <i class="bi bi-check-circle me-1"></i>
                Vous utilisez la dernière version.
                @unless($release)<span class="text-muted">Aucune version n'a encore été publiée sur GitHub.</span>@endunless
            </div>
        @endif

        <div id="update-feedback" class="d-none"></div>

        @if($status['backup'])
            <hr class="my-4">
            <h6><i class="bi bi-clock-history me-1"></i> Revenir en arrière</h6>
            <p class="small text-muted mb-2">
                Sauvegarde des fichiers : <code>{{ $status['backup']['name'] }}</code>.
                Cette opération remet les fichiers de la version précédente ; <strong>la base de données n'est pas modifiée</strong>.
            </p>
            <button type="button" class="btn btn-outline-danger btn-sm" data-update-route="{{ route('admin.update.restore') }}"
                    data-confirm="Restaurer les fichiers de la version précédente ? La base de données reste telle quelle.">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Restaurer les fichiers précédents
            </button>
        @endif

        <p class="small text-muted mt-4 mb-0">
            Dernière vérification : {{ \Illuminate\Support\Carbon::parse($status['checked_at'])->translatedFormat('j F Y à H:i') }}.
            Seules les archives signées par la clé du projet sont installées.
        </p>
    </div>
</div>

@include('admin.update._backups')
@endsection
