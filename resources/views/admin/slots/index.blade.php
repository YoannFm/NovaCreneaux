@extends('layouts.admin')
@php $pageTitle = 'Creneaux - ' . $resource->name; @endphp
@section('content')

<div class="d-flex align-items-center gap-3 mb-3">
    <a href="{{ route('admin.resources.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Ressources
    </a>
    <a href="{{ route('admin.slots.create', $resource) }}" class="btn btn-primary btn-sm ms-auto">
        <i class="bi bi-plus-lg"></i> Nouveau creneau
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nom</th>
                        <th>Debut</th>
                        <th>Fin</th>
                        <th>Capacite</th>
                        <th>Reservations</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($slots as $s)
                <tr>
                    <td>{{ $s->name }}</td>
                    <td>{{ substr($s->start_time, 0, 5) }}</td>
                    <td>{{ substr($s->end_time, 0, 5) }}</td>
                    <td>{{ $s->max_capacity }}</td>
                    <td>{{ $s->reservations_count }}</td>
                    <td>
                        @if($s->is_active)
                            <span class="badge bg-success">Actif</span>
                        @else
                            <span class="badge bg-secondary">Inactif</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('admin.slots.edit', [$resource, $s]) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.slots.destroy', [$resource, $s]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Supprimer ce creneau ?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">Aucun creneau.</td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
