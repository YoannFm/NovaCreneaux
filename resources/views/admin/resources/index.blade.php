@extends('layouts.admin')
@php $pageTitle = 'Ressources'; @endphp
@section('content')

<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.resources.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Nouvelle ressource
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nom</th>
                        <th>Créneaux</th>
                        <th>Ouverture</th>
                        <th>Fermeture</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($resources as $r)
                <tr>
                    <td>
                        <strong>{{ $r->name }}</strong>
                        @if($r->description)
                            <br><span class="text-muted small">{{ $r->description }}</span>
                        @endif
                    </td>
                    <td>{{ $r->slots_count }}</td>
                    <td>{{ substr($r->open_time, 0, 5) }}</td>
                    <td>{{ substr($r->close_time, 0, 5) }}</td>
                    <td>
                        @if($r->is_active)
                            <span class="badge bg-success">Actif</span>
                        @else
                            <span class="badge bg-secondary">Inactif</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('admin.resources.edit', $r) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="{{ route('admin.slots.index', $r) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-clock"></i> Créneaux
                            </a>
                            <form method="POST" action="{{ route('admin.resources.destroy', $r) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Supprimer cette ressource ?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">Aucune ressource.</td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
