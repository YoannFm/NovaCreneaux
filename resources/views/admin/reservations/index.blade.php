@extends('layouts.admin')
@php $pageTitle = 'Reservations'; @endphp
@section('content')

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Ressource</label>
                <select name="resource_id" class="form-select form-select-sm" style="min-width:160px">
                    <option value="">Toutes</option>
                    @foreach($resources as $r)
                        <option value="{{ $r->id }}" {{ $resourceId == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-download"></i> CSV
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="text-muted small">{{ $reservations->count() }} reservation(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Nom</th><th>Classe</th><th>Ressource</th><th>Creneau</th><th>Date</th><th>Reserve le</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reservations as $r)
                <tr>
                    <td>{{ $r->user->name }}</td>
                    <td>{{ $r->user->classe ?? '-' }}</td>
                    <td>{{ $r->slot->resource->name }}</td>
                    <td>{{ $r->slot->name }}</td>
                    <td>{{ $r->reservation_date->format('d/m/Y') }}</td>
                    <td>{{ $r->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancel-modal"
                                data-action="{{ route('admin.reservations.destroy', $r) }}"
                                data-label="{{ $r->user->name }} - {{ $r->slot->resource->name }}, {{ $r->slot->name }} ({{ $r->reservation_date->format('d/m/Y') }})"
                                title="Supprimer"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-4 text-muted">Aucune reservation pour cette date.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

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
                <div class="form-text">L'élève est prévenu par une notification sur le site et par e-mail, avec ce motif.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Supprimer</button>
            </div>
        </form>
    </div>
</div>

@push('footer-scripts')
<script>
document.getElementById('cancel-modal').addEventListener('show.bs.modal', function (event) {
    document.getElementById('cancel-form').action = event.relatedTarget.dataset.action;
    document.getElementById('cancel-label').textContent = event.relatedTarget.dataset.label;
    document.getElementById('cancel-reason').value = '';
});
</script>
@endpush

@endsection
