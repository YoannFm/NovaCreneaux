@extends('layouts.app')
@php $pageTitle = 'Mes reservations'; @endphp
@section('content')

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="bi bi-bookmark-check me-2"></i>Mes réservations</h5>
    </div>
    <div class="card-body p-0">
        @if($reservations->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x" style="font-size:2rem;opacity:.4"></i>
                <p class="mt-2 mb-0">Aucune réservation.</p>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Ressource</th>
                        <th>Créneau</th>
                        <th>Réservé le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($reservations as $r)
                <tr>
                    <td>{!! $r->reservation_date->locale('fr')->isoFormat('ddd D MMM YYYY') !!}</td>
                    <td>{{ $r->slot->resource->name }}</td>
                    <td>{{ $r->slot->name }}</td>
                    <td class="text-muted">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        @php
                            // Meme regle que l'annulation elle-meme (Reservation::cancellableUntil : debut du creneau moins le delai du role)
                            $until = $r->cancellableUntil();
                            $untilText = $until->isToday() ? $until->format('H:i') : $until->locale('fr')->isoFormat('ddd D MMM [à] HH:mm');
                        @endphp
                        @if($r->isCancellableByOwner())
                        <form method="POST" action="{{ route('reservations.destroy', $r->id) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Annuler cette réservation ?')">
                                <i class="bi bi-x-lg"></i> Annuler
                            </button>
                        </form>
                        <div class="text-muted small mt-1">jusqu'à {{ $untilText }}</div>
                        @elseif(now()->lt($r->startsAt()))
                            <span class="text-muted small"><i class="bi bi-lock me-1"></i>Annulation impossible depuis {{ $untilText }}</span>
                        @else
                            <span class="text-muted small">Passée</span>
                        @endif
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $reservations->links() }}</div>
        @endif
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger mt-3">{{ $errors->first() }}</div>
@endif

@endsection
