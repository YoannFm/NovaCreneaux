@extends('layouts.admin')
@php
    $pageTitle = 'Paramètres - Configuration';
    $limitFields = \App\Support\ReservationLimits::fields();
@endphp
@section('content')

<p class="text-muted mb-3">
    Ressources, créneaux, limites par rôle, textes et couleurs du site, dans un fichier à garder ou à reprendre sur une autre installation.
    Les secrets (mot de passe SMTP, jetons), les comptes et les réservations n'en font jamais partie.
</p>

@if($errors->import->any())
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> Rien n'a été importé :
        <ul class="mb-0 mt-1">
            @foreach(array_slice($errors->import->all(), 0, 8) as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-box-arrow-up me-1"></i> Exporter</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.settings.configuration.export') }}" class="js-clear-password">
                    @csrf
                    <label for="export-password" class="form-label small text-muted">Un fichier JSON avec la configuration actuelle du site.</label>
                    <input type="password" class="form-control mb-2" id="export-password" name="password" required
                           autocomplete="current-password" placeholder="Votre mot de passe administrateur">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-download me-1"></i> Télécharger la configuration
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-box-arrow-in-down me-1"></i> Importer</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.settings.configuration.import') }}" enctype="multipart/form-data">
                    @csrf
                    <label for="configuration" class="form-label small text-muted">
                        Ce qui existe déjà est mis à jour, ce qui manque est ajouté. <strong>Rien n'est supprimé</strong>
                        et les rôles administrateurs ne sont jamais modifiés.
                    </label>
                    <input type="file" class="form-control mb-2" id="configuration" name="configuration" accept=".json,application/json" required>
                    <div class="input-group">
                        <input type="password" class="form-control" name="password" required autocomplete="current-password"
                               placeholder="Votre mot de passe administrateur" aria-label="Votre mot de passe administrateur">
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-upload me-1"></i> Importer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<h5 class="mb-1">Modèles prêts à importer</h5>
<p class="text-muted small mb-3">
    Pour démarrer vite : chaque modèle ajoute ses ressources et ses créneaux, et règle les limites du rôle Utilisateur.
    Tout reste modifiable ensuite.
</p>

<div class="row g-3">
    @foreach($templates as $id => $template)
        <div class="col-12 col-md-6 col-xxl-3">
            <div class="card h-100 mb-0">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="stat flex-shrink-0"><i class="bi {{ $template['icon'] ?? 'bi-grid' }}"></i></div>
                        <h6 class="mb-0">{{ $template['title'] ?? $id }}</h6>
                    </div>
                    <p class="small text-muted">{{ $template['description'] ?? '' }}</p>

                    <ul class="small list-unstyled mb-3">
                        @foreach($template['resources'] ?? [] as $resource)
                            <li><i class="bi bi-collection me-1 text-muted"></i>{{ $resource['name'] }}
                                <span class="text-muted">({{ count($resource['slots'] ?? []) }} créneaux)</span></li>
                        @endforeach
                        @foreach($template['roles'] ?? [] as $role)
                            @foreach(array_intersect_key($limitFields, $role) as $key => $field)
                                @php($value = $role[$key] === \App\Support\ReservationLimits::UNLIMITED ? mb_strtolower($field['unlimited']) : "{$role[$key]} {$field['unit']}")
                                <li><i class="bi {{ $field['icon'] }} me-1 text-muted"></i>{{ $field['label'] }} : {{ $value }}</li>
                            @endforeach
                        @endforeach
                    </ul>

                    @php($question = 'Importer le modèle « ' . ($template['title'] ?? $id) . ' » ?' . (empty($template['roles']) ? '' : ' Les limites de réservation seront remplacées.'))
                    <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-auto" data-template-url="{{ route('admin.settings.configuration.template', $id) }}"
                            data-template-question="{{ $question }}">
                        <i class="bi bi-magic me-1"></i> Importer ce modèle
                    </button>
                </div>
            </div>
        </div>
    @endforeach
</div>
<div class="modal fade" id="template-modal" tabindex="-1" aria-labelledby="template-modal-title" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content" id="template-form" autocomplete="off">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="template-modal-title">Importer un modèle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p id="template-modal-text" class="mb-3"></p>
                <label class="form-label" for="template-password">Votre mot de passe administrateur</label>
                <input type="password" class="form-control" id="template-password" name="password" required autocomplete="current-password">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-magic me-1"></i> Importer</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('footer-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('template-modal');
    var password = document.getElementById('template-password');
    document.querySelectorAll('[data-template-url]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('template-form').action = button.dataset.templateUrl;
            document.getElementById('template-modal-text').textContent = button.dataset.templateQuestion;
            password.value = '';
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });
    modalEl.addEventListener('shown.bs.modal', function () { password.focus(); });

    // Un telechargement ne recharge pas la page : on efface le mot de passe saisi
    document.querySelectorAll('.js-clear-password').forEach(function (form) {
        form.addEventListener('submit', function () {
            setTimeout(function () { form.querySelector('input[type=password]').value = ''; }, 400);
        });
    });
});
</script>
@endpush
