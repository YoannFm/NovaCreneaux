{{-- Section "Sauvegardes" de la page Mise a jour. Telecharger et supprimer demandent le mot de passe (fenetre en bas de ce fichier). --}}
@php($schedule = $backup['schedule'])

<div class="card shadow mb-4" id="sauvegardes">
    <div class="card-body">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <h5 class="mb-1"><i class="bi bi-database me-2"></i>Sauvegardes de la base de données</h5>
                <p class="text-muted small mb-0">
                    Export SQL compressé : comptes, réservations et réglages. Les fichiers du site sont sauvegardés à part à chaque mise à jour.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.backups.store') }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-cloud-arrow-down me-1"></i> Sauvegarder maintenant
                </button>
            </form>
        </div>

        @if(session('backup_success'))
            <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i> {{ session('backup_success') }}</div>
        @endif
        @if(session('backup_error'))
            <div class="alert alert-danger py-2"><i class="bi bi-shield-exclamation me-1"></i> {{ session('backup_error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <ul class="list-unstyled small mb-3">
            <li>
                Sauvegarde automatique :
                @if($schedule->enabled)
                    prochaine le <strong>{{ $backup['next']->translatedFormat('l j F à H:i') }}</strong>
                @else
                    <strong>désactivée</strong>
                @endif
            </li>
            <li>
                Dernière sauvegarde automatique réussie :
                {{ $backup['lastSuccess'] ? $backup['lastSuccess']->translatedFormat('j F Y à H:i') : 'aucune' }}
            </li>
        </ul>

        @if($backup['lastError'])
            <div class="alert alert-warning py-2">
                <i class="bi bi-exclamation-triangle me-1"></i>
                La dernière sauvegarde automatique a échoué : {{ $backup['lastError'] }} Une nouvelle tentative a lieu dans l'heure.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.backups.schedule') }}" class="border rounded p-3 mb-4">
            @csrf
            @method('PUT')
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="backup-enabled" name="enabled" value="1" @checked($schedule->enabled)>
                <label class="form-check-label" for="backup-enabled">Sauvegarde automatique</label>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-4">
                    <label class="form-label small mb-1" for="backup-frequency">Fréquence</label>
                    <select class="form-select form-select-sm" id="backup-frequency" name="frequency">
                        @foreach(\App\Services\Backup\BackupSchedule::FREQUENCIES as $value => $label)
                            <option value="{{ $value }}" @selected(old('frequency', $schedule->frequency) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 d-none" id="backup-weekday-group">
                    <label class="form-label small mb-1" for="backup-weekday">Jour</label>
                    <select class="form-select form-select-sm" id="backup-weekday" name="weekday">
                        @foreach(\App\Services\Backup\BackupSchedule::WEEKDAYS as $value => $label)
                            <option value="{{ $value }}" @selected((int) old('weekday', $schedule->weekday) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="backup-time">Heure</label>
                    <input type="time" class="form-control form-control-sm" id="backup-time" name="time" value="{{ old('time', $schedule->time) }}" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="backup-keep">Automatiques conservées</label>
                    <input type="number" class="form-control form-control-sm" id="backup-keep" name="keep" min="1" max="90" value="{{ old('keep', $schedule->keep) }}" required>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-save me-1"></i> Enregistrer</button>
                </div>
            </div>
            <div class="form-text mt-2">
                Les sauvegardes manuelles ne sont jamais supprimées automatiquement. Sans tâche cron sur le serveur, la sauvegarde due
                se lance à la première visite après l'heure prévue.
            </div>
        </form>

        <h6 class="mb-2">
            Sauvegardes enregistrées
            <span class="text-muted fw-normal">({{ count($backup['backups']) }}, {{ \Illuminate\Support\Number::fileSize($backup['totalSize'], 1) }})</span>
        </h6>

        @if(count($backup['backups']) === 0)
            <p class="text-muted mb-0">Aucune sauvegarde pour l'instant.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Date</th><th>Type</th><th>Taille</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($backup['backups'] as $item)
                            @php($label = $item['created_at']->translatedFormat('j F Y à H:i'))
                            <tr>
                                <td>{{ $label }}</td>
                                <td>
                                    @if($item['trigger'] === 'auto')
                                        <span class="badge bg-secondary">Automatique</span>
                                    @elseif($item['trigger'] === 'update')
                                        <span class="badge bg-info text-dark">Avant mise à jour</span>
                                    @else
                                        <span class="badge bg-primary">Manuelle</span>
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Number::fileSize($item['size'], 1) }}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-backup-action="download"
                                            data-backup-url="{{ route('admin.backups.download', $item['name']) }}" data-backup-label="{{ $label }}">
                                        <i class="bi bi-download me-1"></i> Télécharger
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-backup-action="delete"
                                            data-backup-url="{{ route('admin.backups.destroy', $item['name']) }}" data-backup-label="{{ $label }}">
                                        <i class="bi bi-trash me-1"></i> Supprimer
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="backup-password-modal" tabindex="-1" aria-labelledby="backup-modal-title" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content" id="backup-password-form" autocomplete="off">
            @csrf
            <input type="hidden" name="_method" value="POST" id="backup-method">
            <div class="modal-header">
                <h5 class="modal-title" id="backup-modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p id="backup-modal-text" class="mb-3"></p>
                <label class="form-label" for="backup-password">Votre mot de passe administrateur</label>
                <input type="password" class="form-control" id="backup-password" name="password" required autocomplete="current-password">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn" id="backup-modal-submit"></button>
            </div>
        </form>
    </div>
</div>

@push('footer-scripts')
<script>
(function () {
    var modalEl = document.getElementById('backup-password-modal');
    var form = document.getElementById('backup-password-form');
    var method = document.getElementById('backup-method');
    var password = document.getElementById('backup-password');
    var submit = document.getElementById('backup-modal-submit');

    document.querySelectorAll('[data-backup-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            var removing = button.dataset.backupAction === 'delete';
            form.action = button.dataset.backupUrl;
            method.value = removing ? 'DELETE' : 'POST';
            document.getElementById('backup-modal-title').textContent = removing ? 'Supprimer la sauvegarde' : 'Télécharger la sauvegarde';
            document.getElementById('backup-modal-text').textContent = (removing
                ? 'Cette sauvegarde sera supprimée définitivement : '
                : 'Cette sauvegarde contient toutes les données du site, y compris les comptes : ') + button.dataset.backupLabel + '.';
            submit.className = 'btn ' + (removing ? 'btn-danger' : 'btn-primary');
            submit.textContent = removing ? 'Supprimer' : 'Télécharger';
            password.value = '';
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });

    modalEl.addEventListener('shown.bs.modal', function () { password.focus(); });

    // Un telechargement ne recharge pas la page : on referme la fenetre et on efface le mot de passe
    form.addEventListener('submit', function () {
        if (method.value === 'POST') {
            setTimeout(function () { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); password.value = ''; }, 400);
        }
    });

    // Le jour de la semaine ne concerne que la frequence hebdomadaire
    var frequency = document.getElementById('backup-frequency');
    var weekday = document.getElementById('backup-weekday-group');
    function syncWeekday() { weekday.classList.toggle('d-none', frequency.value !== 'weekly'); }
    frequency.addEventListener('change', syncWeekday);
    syncWeekday();
})();
</script>
@endpush
