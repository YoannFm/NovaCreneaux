@extends('layouts.admin')
@php $pageTitle = 'Utilisateurs'; @endphp

@section('content')

{{-- Recherche --}}
<form class="row gx-3 align-items-center mb-3" action="{{ route('admin.users.index') }}" method="GET" role="search">
    <div class="col-md-4 col-12 mb-3">
        <div class="input-group">
            <input type="search" class="form-control" name="search"
                   value="{{ request('search') }}" placeholder="Rechercher">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </div>
</form>

{{-- Formulaire des actions groupees : les cases et les boutons s'y rattachent par l'attribut form="" (pas de formulaire imbrique) --}}
<form id="bulk-form" method="POST" action="{{ route('admin.users.bulk') }}">@csrf</form>

{{-- Table --}}
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col" style="width:2.5rem;">
                            <input type="checkbox" class="form-check-input" id="bulk-all" title="Tout sélectionner" aria-label="Tout sélectionner">
                        </th>
                        <th scope="col">#</th>
                        <th scope="col">Nom</th>
                        <th scope="col">Adresse Email</th>
                        <th scope="col">Classe</th>
                        <th scope="col">Rôle</th>
                        <th scope="col">Inscrit le</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input bulk-item" name="ids[]" value="{{ $u->id }}" form="bulk-form"
                                   aria-label="Sélectionner {{ $u->name }}">
                        </td>
                        <th scope="row">
                            {{ $u->id }}
                            @if($u->isAdmin())
                                <i class="bi bi-trophy text-warning" title="Administrateur" data-bs-toggle="tooltip"></i>
                            @endif
                            @if(!$u->is_active)
                                <i class="bi bi-slash-circle text-danger" title="Inactif" data-bs-toggle="tooltip"></i>
                            @endif
                        </th>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->classe ?? '-' }}</td>
                        <td>
                            @if($u->role)
                                <span class="badge" style="{{ $u->role->getBadgeStyle() }}">
                                    @if($u->role->icon)<i class="{{ $u->role->icon }} me-1"></i>@endif{{ $u->role->label }}
                                </span>
                            @else
                                <span class="badge bg-secondary">-</span>
                            @endif
                        </td>
                        <td>{{ $u->created_at->translatedFormat('j F Y') }}</td>
                        <td>
                            <a href="{{ route('admin.users.edit', $u) }}" class="mx-1"
                               title="Modifier" data-bs-toggle="tooltip">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-4" style="color:var(--text-muted);">
                            <i class="bi bi-people d-block mb-2" style="font-size:2rem;opacity:.3;"></i>
                            Aucun utilisateur trouvé.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->withQueryString()->links() }}

        <a class="btn btn-primary" href="{{ route('admin.users.create') }}">
            <i class="bi bi-plus-lg me-1"></i> Ajouter
        </a>
        <a href="{{ route('admin.users.import') }}" class="btn btn-secondary ms-1">
            <i class="bi bi-upload me-1"></i> Import CSV
        </a>
        <a href="{{ route('admin.users.export-zip') }}" class="btn btn-secondary ms-1">
            <i class="bi bi-file-zip me-1"></i> Exporter ZIP
        </a>
    </div>
</div>

{{-- Reserve la place de la barre pour ne pas masquer le bas de la liste --}}
<div id="bulk-spacer" class="d-none" style="height:9rem;"></div>

{{-- Barre d'actions : apparait des qu'une case est cochee --}}
<div id="bulk-bar" class="d-none" role="toolbar" aria-label="Actions sur la sélection">
    <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
        <span class="badge rounded-pill bg-primary fs-6" id="bulk-count">0</span>
        <strong id="bulk-label">utilisateur(s) sélectionné(s)</strong>
    </div>
    <div class="d-flex flex-wrap justify-content-center gap-2">
        <button type="submit" form="bulk-form" name="action" value="activate" class="btn btn-success">
            <i class="bi bi-check-circle me-1"></i> Activer
        </button>
        <button type="submit" form="bulk-form" name="action" value="deactivate" class="btn btn-secondary"
                onclick="return confirm('Désactiver les comptes sélectionnés ? Ils seront déconnectés.')">
            <i class="bi bi-lock me-1"></i> Désactiver
        </button>
        <button type="submit" form="bulk-form" name="action" value="reset_password" class="btn btn-primary"
                onclick="return confirm('Envoyer à chaque compte actif sélectionné un e-mail pour choisir un nouveau mot de passe ?')">
            <i class="bi bi-key me-1"></i> Réinitialiser MDP
        </button>
        <button type="submit" form="bulk-form" name="action" value="export" class="btn btn-info">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Exporter CSV
        </button>
        <button type="submit" form="bulk-form" name="action" value="delete" class="btn btn-danger"
                onclick="return confirm('Supprimer définitivement les comptes sélectionnés et leurs réservations ? Cette action est irréversible.')">
            <i class="bi bi-trash me-1"></i> Supprimer
        </button>
        <button type="button" class="btn btn-outline-secondary" id="bulk-cancel">
            <i class="bi bi-x-lg me-1"></i> Annuler
        </button>
    </div>
</div>

@push('header-styles')
<style>
    #bulk-bar {
        position: fixed; left: 50%; bottom: 1.25rem; transform: translateX(-50%);
        z-index: 1030; width: min(58rem, calc(100% - 2rem));
        padding: 1rem 1.25rem;
        background: var(--white); border: 1px solid var(--border); border-radius: 1rem;
        box-shadow: 0 .5rem 2rem rgba(0, 0, 0, .25);
    }
    @media (min-width: 992px) { #bulk-bar { left: calc(50% + 130px); } }
</style>
@endpush

@push('footer-scripts')
<script>
(function () {
    var items = Array.prototype.slice.call(document.querySelectorAll('.bulk-item'));
    var all = document.getElementById('bulk-all');
    var bar = document.getElementById('bulk-bar');
    var count = document.getElementById('bulk-count');

    function refresh() {
        var checked = items.filter(function (box) { return box.checked; }).length;
        count.textContent = checked;
        bar.classList.toggle('d-none', checked === 0);
        document.getElementById('bulk-spacer').classList.toggle('d-none', checked === 0);
        all.checked = checked > 0 && checked === items.length;
        all.indeterminate = checked > 0 && checked < items.length;
        items.forEach(function (box) { box.closest('tr').classList.toggle('table-active', box.checked); });
    }

    all.addEventListener('change', function () {
        items.forEach(function (box) { box.checked = all.checked; });
        refresh();
    });
    items.forEach(function (box) { box.addEventListener('change', refresh); });
    document.getElementById('bulk-cancel').addEventListener('click', function () {
        items.forEach(function (box) { box.checked = false; });
        refresh();
    });
    refresh();
})();
</script>
@endpush

@endsection
