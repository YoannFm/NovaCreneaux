@extends('layouts.admin')
@php $pageTitle = 'Tableau de bord'; @endphp
@section('content')

<style>
.card { transition: box-shadow .15s, transform .1s; }
.card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.15); transform: translateY(-2px); }
</style>

<div class="row g-3 mb-4">
    @foreach([
        ['label' => 'Utilisateurs',              'value' => $stats['users'],     'icon' => 'people',         'color' => 'primary',  'route' => route('admin.users.index')],
        ['label' => 'Ressources',                'value' => $stats['resources'], 'icon' => 'collection',     'color' => 'success',  'route' => route('admin.resources.index')],
        ['label' => 'Créneaux',                  'value' => $stats['slots'],     'icon' => 'clock',          'color' => 'warning',  'route' => route('admin.resources.index')],
        ['label' => "Réservations aujourd'hui",  'value' => $stats['today'],     'icon' => 'calendar-check', 'color' => 'info',     'route' => route('admin.reservations.index')],
        ['label' => 'Total réservations',        'value' => $stats['total'],     'icon' => 'bar-chart',      'color' => 'danger',   'route' => route('admin.reservations.index')],
    ] as $s)
    <div class="col-6 col-md-4 col-xl">
        <a href="{{ $s['route'] }}" style="text-decoration:none;color:inherit;">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col mt-0">
                        <h5 class="card-title">{{ $s['label'] }}</h5>
                    </div>
                    <div class="col-auto">
                        <div class="stat text-{{ $s['color'] }} h3">
                            <i class="bi bi-{{ $s['icon'] }}"></i>
                        </div>
                    </div>
                </div>
                <h1 class="mt-1 mb-3">{{ $s['value'] }}</h1>
            </div>
        </div>
        </a>
    </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Dernières réservations</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Utilisateur</th>
                        <th>Classe</th>
                        <th>Ressource</th>
                        <th>Créneau</th>
                        <th>Date</th>
                        <th>Réservé le</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($recent as $r)
                <tr>
                    <td><strong>{{ $r->user->name }}</strong></td>
                    <td class="text-muted">{{ $r->user->classe ?? '-' }}</td>
                    <td>{{ $r->slot->resource->name ?? '-' }}</td>
                    <td>{{ $r->slot->name ?? '-' }}</td>
                    <td>{{ $r->reservation_date->format('d/m/Y') }}</td>
                    <td class="text-muted">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-x" style="font-size:1.5rem;opacity:.4"></i>
                        <p class="mt-2 mb-0">Aucune réservation.</p>
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
