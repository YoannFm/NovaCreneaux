@extends('layouts.admin')
@php $pageTitle = 'Webhooks'; @endphp
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3 gap-3 flex-wrap">
    <p class="mb-0 small" style="color:var(--muted-text)">
        Un webhook prévient un autre service dès qu'il se passe quelque chose sur le site : réservation créée ou annulée, compte créé ou supprimé.
        Chaque envoi est signé avec un secret.
    </p>
    <a href="{{ route('admin.webhooks.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nouveau webhook</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small align-middle">
                <thead class="table-light">
                    <tr><th>Nom</th><th>Adresse</th><th>Événements</th><th>État</th><th>Dernier envoi réussi</th><th></th></tr>
                </thead>
                <tbody>
                @forelse($webhooks as $webhook)
                    <tr>
                        <td><strong>{{ $webhook->name }}</strong>@if($webhook->isDiscord()) <span class="badge bg-primary">Discord</span>@endif</td>
                        <td class="text-break"><code>{{ $webhook->url }}</code></td>
                        <td>@foreach($webhook->events as $event)<span class="badge bg-secondary me-1">{{ $event }}</span>@endforeach</td>
                        <td>
                            @if($webhook->is_active)
                                <span class="badge bg-success">Actif</span>
                            @else
                                <span class="badge bg-danger" title="Désactivé à la main ou après des échecs répétés">Désactivé</span>
                            @endif
                        </td>
                        <td>{{ $webhook->last_delivery_at?->format('d/m/Y H:i') ?? 'jamais' }}</td>
                        <td class="text-end"><a href="{{ route('admin.webhooks.edit', $webhook) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i> Gérer</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">Aucun webhook.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
