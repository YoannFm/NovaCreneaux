@php $pageTitle = 'Mon espace'; @endphp
@extends('layouts.app')

@section('content')

<div class="card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h4 class="mb-1">Bonjour, {{ auth()->user()->name }} 👋</h4>
            <p class="text-muted mb-0" style="font-size:.9rem;">Gérez vos réservations depuis votre espace personnel.</p>
        </div>
        <a href="{{ route('reservations.index') }}" class="btn btn-primary">
            <i class="bi bi-calendar-plus me-1"></i> Réserver un créneau
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0"><i class="bi bi-calendar-check me-2"></i>Mes prochaines réservations</h5>
        <a href="{{ route('reservations.my') }}" class="btn btn-sm btn-outline-secondary">Tout voir</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Ressource</th>
                        <th>Créneau</th>
                        <th>Horaire</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($upcoming as $r)
                <tr>
                    <td><strong>{{ $r->reservation_date->format('d/m/Y') }}</strong>
                        @if($r->reservation_date->isToday())
                            <span class="badge bg-success ms-1" style="font-size:.65rem;">Aujourd'hui</span>
                        @elseif($r->reservation_date->isTomorrow())
                            <span class="badge bg-info ms-1" style="font-size:.65rem;">Demain</span>
                        @endif
                    </td>
                    <td>{{ $r->slot->resource->name ?? '-' }}</td>
                    <td>{{ $r->slot->name ?? '-' }}</td>
                    <td class="text-muted" style="font-size:.85rem;">
                        {{ substr($r->slot->start_time ?? '', 0, 5) }} – {{ substr($r->slot->end_time ?? '', 0, 5) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-x d-block mb-2" style="font-size:1.5rem;opacity:.3;"></i>
                        Aucune réservation à venir.
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
