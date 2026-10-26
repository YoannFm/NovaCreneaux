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
                        <form method="POST" action="{{ route('admin.reservations.destroy', $r) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></button>
                        </form>
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

@endsection
