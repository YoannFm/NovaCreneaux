@extends('layouts.admin')
@php $pageTitle = 'Extensions'; @endphp
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3 gap-3 flex-wrap">
    <p class="mb-0 small" style="color:var(--muted-text)">
        Une extension ajoute des pages, des permissions et des fonctions au site. C'est du code qui s'exécute avec tous les droits du site :
        n'installez que des extensions signées, issues du catalogue.
    </p>
    <form method="POST" action="{{ route('admin.plugins.refresh') }}">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> Actualiser le catalogue</button>
    </form>
</div>

@unless($signingConfigured)
    <div class="alert alert-warning">
        <i class="bi bi-shield-exclamation me-1"></i>
        La clé de signature des extensions n'est pas configurée : le catalogue et l'installation sont désactivés.
        @if($unsignedAllowed) Les extensions copiées à la main sont acceptées (mode développement). @endif
    </div>
@endunless

<div class="card mb-4">
    <div class="card-header">Extensions installées</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small align-middle">
                <thead class="table-light"><tr><th>Extension</th><th>Permissions</th><th>État</th><th></th></tr></thead>
                <tbody>
                @forelse($installed as $plugin)
                    <tr>
                        <td>
                            <strong>{{ $plugin->name() }}</strong>
                            @if($plugin->version()) <span class="text-muted">{{ $plugin->version() }}</span> @endif
                            <code class="ms-1">{{ $plugin->id }}</code>
                            @isset($updates[$plugin->id]) <span class="badge bg-info text-dark">Mise à jour {{ $updates[$plugin->id]['version'] }}</span> @endisset
                            <div class="text-muted">{{ $plugin->manifest['description'] ?? '' }}</div>
                            @foreach($plugin->problems as $problem)<div class="text-danger">{{ $problem }}</div>@endforeach
                            @if($plugin->failure)<div class="text-danger">Désactivée après une erreur : {{ $plugin->failure }}</div>@endif
                        </td>
                        <td>@foreach($plugin->permissions() as $label)<div>{{ $label }}</div>@endforeach</td>
                        <td>
                            @if($plugin->enabled)<span class="badge bg-success">Active</span>
                            @elseif($plugin->failure)<span class="badge bg-danger">En erreur</span>
                            @elseif($plugin->manifest === null)<span class="badge bg-danger">Invalide</span>
                            @else<span class="badge bg-secondary">Inactive</span>@endif
                            @if($plugin->manifest !== null && $plugin->signature === null)
                                <span class="badge bg-warning text-dark" title="Installée sans archive signée">Non signée</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if($plugin->enabled)
                                <form method="POST" action="{{ route('admin.plugins.disable', $plugin->id) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-outline-secondary">Désactiver</button></form>
                            @else
                                @if($plugin->manifest !== null)
                                    <form method="POST" action="{{ route('admin.plugins.enable', $plugin->id) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-primary">Activer</button></form>
                                @endif
                                <form method="POST" action="{{ route('admin.plugins.destroy', $plugin->id) }}" class="d-inline"
                                      onsubmit="return confirm('Supprimer les fichiers de cette extension ? Ses tables de base de données sont conservées.')">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            @endif
                            @isset($updates[$plugin->id])
                                <form method="POST" action="{{ route('admin.plugins.install', $plugin->id) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-info">Mettre à jour</button></form>
                            @endisset
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-4 text-muted">Aucune extension installée.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Catalogue</div>
    <div class="card-body p-0">
        @unless($signingConfigured)
            <div class="p-3 text-muted small">Catalogue indisponible tant que la clé de signature n'est pas configurée.</div>
        @elseif($catalog['error'])
            <div class="p-3 text-danger small">{{ $catalog['error'] }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 small align-middle">
                    <thead class="table-light"><tr><th>Extension</th><th>Permissions demandées</th><th></th></tr></thead>
                    <tbody>
                    @forelse($available as $entry)
                        <tr>
                            <td>
                                <strong>{{ $entry['name'] }}</strong> <span class="text-muted">{{ $entry['version'] }}</span>
                                @if($entry['author']) <span class="text-muted">par {{ $entry['author'] }}</span> @endif
                                <div class="text-muted">{{ $entry['description'] }}</div>
                            </td>
                            <td>@forelse($entry['permissions'] as $label)<div>{{ $label }}</div>@empty<span class="text-muted">Aucune</span>@endforelse</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.plugins.install', $entry['id']) }}">@csrf
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Installer</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center py-4 text-muted">Aucune autre extension disponible.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@endsection
