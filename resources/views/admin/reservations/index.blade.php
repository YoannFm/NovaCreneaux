@extends('layouts.admin')
@php
    $pageTitle = 'Réservations';
    $day = fn (\Carbon\Carbon $d) => route('admin.reservations.index', array_filter(['date' => $d->toDateString(), 'resource_id' => $resourceId]));
    $exportUrl = fn (string $format, ?string $layout = null) => route('admin.reservations.index', array_filter(['date' => $date->toDateString(), 'resource_id' => $resourceId, 'export' => $format, 'layout' => $layout]));
    $addErrors = $errors->add->all();
@endphp
@section('content')

{{-- Barre de navigation : un jour a la fois --}}
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ $day($date->copy()->subDay()) }}" class="btn btn-outline-secondary" id="day-prev" title="Jour précédent (flèche gauche)" aria-label="Jour précédent">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div class="text-center px-2" style="min-width:13rem;">
                <div class="fw-semibold" style="font-size:1.1rem;">{{ ucfirst($date->locale('fr')->isoFormat('dddd D MMMM YYYY')) }}</div>
                <div class="small text-muted">
                    @if($date->isToday()) Aujourd'hui @elseif($date->isTomorrow()) Demain @elseif($date->isYesterday()) Hier @else &nbsp; @endif
                </div>
            </div>
            <a href="{{ $day($date->copy()->addDay()) }}" class="btn btn-outline-secondary" id="day-next" title="Jour suivant (flèche droite)" aria-label="Jour suivant">
                <i class="bi bi-chevron-right"></i>
            </a>
            @unless($date->isToday())
                <a href="{{ $day(now()) }}" class="btn btn-sm btn-link">Aujourd'hui</a>
            @endunless
        </div>

        <form method="GET" class="d-flex flex-wrap align-items-center gap-2" id="filters">
            <input type="date" name="date" class="form-control form-control-sm" value="{{ $date->toDateString() }}" aria-label="Aller à la date" style="width:auto;">
            <select name="resource_id" class="form-select form-select-sm" aria-label="Ressource" style="width:auto;min-width:10rem;">
                <option value="">Toutes les ressources</option>
                @foreach($resources as $r)
                    <option value="{{ $r->id }}" @selected($resourceId == $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn btn-sm btn-primary">Afficher</button></noscript>
        </form>

        <div class="d-flex flex-wrap align-items-center gap-2 ms-lg-auto">
            <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $total }} réservation{{ $total > 1 ? 's' : '' }}</span>
            <a href="{{ $exportUrl('csv') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach($pdfLayouts as $layout => $label)
                        <li><a class="dropdown-item" href="{{ $exportUrl('pdf', $layout) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#add-modal">
                <i class="bi bi-person-plus me-1"></i> Ajouter
            </button>
        </div>
    </div>
</div>

{{-- Planning du jour : ressource > creneau > personnes --}}
@forelse($planning as ['resource' => $resource, 'slots' => $slots])
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0"><i class="bi bi-building me-1"></i> {{ $resource->name }}</h5>
        <span class="small text-muted">{{ $slots->sum(fn ($row) => $row['reservations']->count()) }} réservation(s)</span>
    </div>
    <div class="card-body p-0">
        @forelse($slots as ['slot' => $slot, 'reservations' => $rows])
            @php
                $taken = $rows->count();
                $full = $taken >= $slot->max_capacity;
                $fill = $slot->max_capacity > 0 ? min(100, round($taken / $slot->max_capacity * 100)) : 100;
            @endphp
            <div class="slot-block {{ $loop->last ? '' : 'border-bottom' }}">
                <div class="d-flex flex-wrap align-items-center gap-3 px-3 py-2 slot-head">
                    <div class="fw-semibold" style="min-width:7.5rem;">{{ substr($slot->start_time, 0, 5) }} - {{ substr($slot->end_time, 0, 5) }}</div>
                    <div class="flex-grow-1">
                        {{ $slot->name }}
                        @if($slot->trashed())<span class="badge bg-secondary ms-1">Supprimé</span>
                        @elseif(!$slot->is_active)<span class="badge bg-secondary ms-1">Inactif</span>@endif
                    </div>
                    <div class="d-flex align-items-center gap-2 small" style="min-width:9rem;">
                        <div class="progress flex-grow-1" style="height:6px;" role="progressbar" aria-label="Remplissage" aria-valuenow="{{ $fill }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar {{ $full ? 'bg-danger' : '' }}" style="width:{{ $fill }}%;"></div>
                        </div>
                        <span class="text-muted text-nowrap">{{ $taken }} / {{ $slot->max_capacity }}</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#add-modal"
                            data-slot-id="{{ $slot->id }}" @disabled($full || !$slot->isOpenForBooking()) title="Ajouter une personne à ce créneau">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                @if($rows->isNotEmpty())
                    <ul class="list-unstyled mb-0 pb-2">
                        @foreach($rows as $r)
                            <li class="d-flex align-items-center gap-3 px-3 py-1 reservation-row">
                                <i class="bi bi-person text-muted"></i>
                                <div class="flex-grow-1">
                                    <span class="fw-medium">{{ $r->user->name }}</span>
                                    @if($r->user->classe)<span class="text-muted small ms-1">{{ $r->user->classe }}</span>@endif
                                </div>
                                <span class="small text-muted d-none d-md-inline">réservé le {{ $r->created_at->format('d/m à H:i') }}</span>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-bs-toggle="modal" data-bs-target="#cancel-modal"
                                        data-action="{{ route('admin.reservations.destroy', $r) }}"
                                        data-label="{{ $r->user->name }} - {{ $resource->name }}, {{ $slot->name }} ({{ $date->format('d/m/Y') }})"
                                        title="Supprimer" aria-label="Supprimer la réservation de {{ $r->user->name }}"><i class="bi bi-trash"></i></button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="text-muted small px-3 py-3 mb-0">Aucun créneau actif pour cette ressource.</p>
        @endforelse
    </div>
</div>
@empty
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-calendar-x d-block mb-2" style="font-size:1.6rem;opacity:.5;"></i>
        Aucune ressource active.
    </div>
</div>
@endforelse

{{-- Ajout par le personnel --}}
<div class="modal fade" id="add-modal" tabindex="-1" aria-labelledby="add-title" aria-hidden="true" @if($addErrors) data-reopen="1" @endif>
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.reservations.store') }}" class="modal-content" id="add-form">
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <input type="hidden" name="user_id" id="add-user-id" value="{{ old('user_id') }}">
            <div class="modal-header">
                <h5 class="modal-title" id="add-title"><i class="bi bi-person-plus me-1"></i> Ajouter une réservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Pour {{ $date->locale('fr')->isoFormat('dddd D MMMM') }}. Les limites du rôle et l'heure d'ouverture ne s'appliquent pas ;
                    la personne reçoit l'e-mail de confirmation habituel.
                </p>
                @if($addErrors)
                    <div class="alert alert-danger py-2 small">{{ $addErrors[0] }}</div>
                @endif

                <div class="mb-3">
                    <label class="form-label" for="add-search">Personne</label>
                    <div id="add-selected" class="d-flex align-items-center gap-2 mb-2 {{ old('user_id') ? '' : 'd-none' }}">
                        <span class="badge bg-primary-subtle text-primary-emphasis fs-6" id="add-selected-name">{{ old('user_name') }}</span>
                        <button type="button" class="btn btn-sm btn-link p-0" id="add-change">Changer</button>
                    </div>
                    <input type="hidden" name="user_name" id="add-user-name" value="{{ old('user_name') }}">
                    <input type="search" class="form-control {{ old('user_id') ? 'd-none' : '' }}" id="add-search" autocomplete="off"
                           placeholder="Nom, adresse e-mail ou {{ mb_strtolower(\App\Support\Customization::get('group_label')) }}" data-url="{{ route('admin.reservations.users') }}">
                    <div class="list-group mt-1" id="add-results" role="listbox" aria-label="Résultats"></div>
                </div>

                <div class="mb-1">
                    <label class="form-label" for="add-slot">Créneau</label>
                    <select class="form-select" id="add-slot" name="slot_id" required>
                        <option value="">Choisir un créneau</option>
                        @foreach($planning as ['resource' => $resource, 'slots' => $slots])
                            <optgroup label="{{ $resource->name }}">
                                @foreach($slots as ['slot' => $slot, 'reservations' => $rows])
                                    @continue(!$slot->isOpenForBooking())
                                    <option value="{{ $slot->id }}" @selected(old('slot_id') == $slot->id) @disabled($rows->count() >= $slot->max_capacity)>
                                        {{ substr($slot->start_time, 0, 5) }} - {{ $slot->name }} ({{ $rows->count() }}/{{ $slot->max_capacity }})
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" id="add-submit"><i class="bi bi-check-lg me-1"></i> Réserver</button>
            </div>
        </form>
    </div>
</div>

{{-- Suppression avec motif --}}
<div class="modal fade" id="cancel-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="cancel-form">
            @csrf @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title">Supprimer la réservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3" id="cancel-label"></p>
                <label class="form-label" for="cancel-reason">Motif (facultatif)</label>
                <input type="text" class="form-control" id="cancel-reason" name="reason" maxlength="300" placeholder="Ex. salle fermée ce jour-là">
                <div class="form-text">La personne est prévenue par une notification sur le site et par e-mail, avec ce motif.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Supprimer</button>
            </div>
        </form>
    </div>
</div>

@push('header-styles')
<style>
    .slot-head { background: var(--light-bg); }
    .reservation-row:hover { background: var(--light-bg); }
</style>
@endpush

@push('footer-scripts')
<script>
(function () {
    // Filtres : la date ou la ressource choisie s'affiche aussitot
    var filters = document.getElementById('filters');
    filters.querySelectorAll('input, select').forEach(function (field) {
        field.addEventListener('change', function () { filters.submit(); });
    });

    // Fleches du clavier : jour precedent ou suivant (sauf pendant une saisie ou avec une fenetre ouverte)
    document.addEventListener('keydown', function (event) {
        if (event.altKey || event.ctrlKey || event.metaKey || document.querySelector('.modal.show')) return;
        if (/^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement.tagName)) return;
        if (event.key === 'ArrowLeft') document.getElementById('day-prev').click();
        if (event.key === 'ArrowRight') document.getElementById('day-next').click();
    });

    // Suppression
    document.getElementById('cancel-modal').addEventListener('show.bs.modal', function (event) {
        document.getElementById('cancel-form').action = event.relatedTarget.dataset.action;
        document.getElementById('cancel-label').textContent = event.relatedTarget.dataset.label;
        document.getElementById('cancel-reason').value = '';
    });

    // Ajout : le bouton "+" d'un creneau le preselectionne ; la personne se cherche par son nom
    var addModal = document.getElementById('add-modal');
    var search = document.getElementById('add-search');
    var results = document.getElementById('add-results');
    var userId = document.getElementById('add-user-id');
    var userName = document.getElementById('add-user-name');
    var selected = document.getElementById('add-selected');
    var timer = null;

    function choose(user) {
        userId.value = user ? user.id : '';
        userName.value = user ? user.label : '';
        document.getElementById('add-selected-name').textContent = user ? user.label : '';
        selected.classList.toggle('d-none', !user);
        search.classList.toggle('d-none', !!user);
        results.replaceChildren();
        if (!user) { search.value = ''; search.focus(); }
    }

    addModal.addEventListener('show.bs.modal', function (event) {
        if (event.relatedTarget && event.relatedTarget.dataset.slotId) {
            document.getElementById('add-slot').value = event.relatedTarget.dataset.slotId;
        }
    });
    addModal.addEventListener('shown.bs.modal', function () { if (!userId.value) search.focus(); });
    document.getElementById('add-change').addEventListener('click', function () { choose(null); });

    search.addEventListener('input', function () {
        clearTimeout(timer);
        var term = search.value.trim();
        if (term.length < 2) { results.replaceChildren(); return; }
        timer = setTimeout(function () {
            fetch(search.dataset.url + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : []; })
                .then(function (users) {
                    results.replaceChildren();
                    if (!users.length) {
                        var none = document.createElement('div');
                        none.className = 'list-group-item small text-muted';
                        none.textContent = 'Aucun compte trouvé.';
                        results.append(none);
                    }
                    users.forEach(function (user) {
                        // Texte uniquement (textContent) : un nom ne peut pas injecter de HTML
                        var item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2';
                        var who = document.createElement('span');
                        who.textContent = user.name + (user.classe ? ' (' + user.classe + ')' : '');
                        var detail = document.createElement('span');
                        detail.className = 'small ' + (user.suspended ? 'text-danger' : 'text-muted');
                        detail.textContent = user.suspended ? 'Suspendu' : user.email;
                        item.append(who, detail);
                        item.addEventListener('click', function () { choose({ id: user.id, label: who.textContent }); });
                        results.append(item);
                    });
                });
        }, 250);
    });

    document.getElementById('add-form').addEventListener('submit', function (event) {
        if (!userId.value) { event.preventDefault(); search.focus(); }
    });

    // Bootstrap (admin.js) est charge en defer : disponible a DOMContentLoaded
    if (addModal.dataset.reopen) {
        document.addEventListener('DOMContentLoaded', function () { bootstrap.Modal.getOrCreateInstance(addModal).show(); });
    }
})();
</script>
@endpush

@endsection
