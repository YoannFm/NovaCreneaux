@extends('layouts.admin')
@php $pageTitle = 'Journaux d\'activité'; @endphp
@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible mb-4" role="alert">
    <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Filtres --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.logs.index') }}" class="row g-3 align-items-end">
            <div class="col-sm-3">
                <label class="form-label" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.7px;">Utilisateur</label>
                <input type="text" name="user" class="form-control" value="{{ request('user') }}" placeholder="Nom...">
            </div>
            <div class="col-sm-3">
                <label class="form-label" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.7px;">Action</label>
                <select name="action" class="form-select">
                    <option value="">Toutes les actions</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.7px;">Du</label>
                <input type="date" name="from" class="form-control" value="{{ request('from') }}">
            </div>
            <div class="col-sm-2">
                <label class="form-label" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.7px;">Au</label>
                <input type="date" name="to" class="form-control" value="{{ request('to') }}">
            </div>
            <div class="col-sm-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
                <a href="{{ route('admin.logs.index') }}" class="btn btn-outline-secondary flex-fill"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Tableau --}}
<div class="card">
    <div class="card-body p-0">

        <div class="d-flex align-items-center justify-content-between px-4 py-3"
             style="border-bottom:1px solid rgba(255,255,255,.06)">
            <span class="text-muted" style="font-size:.82rem;">{{ $logs->total() }} entrée(s)</span>
            @if($oldCount > 0)
            <form method="POST" action="{{ route('admin.logs.purge') }}"
                  onsubmit="return confirm('Supprimer {{ $oldCount }} log(s) de plus de 90 jours ?')">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash me-1"></i> Purger &gt; 90 jours ({{ $oldCount }})
                </button>
            </form>
            @else
            <span class="text-muted" style="font-size:.78rem;">
                <i class="bi bi-check-circle me-1" style="color:#22c55e"></i> Aucun log de plus de 90 jours
            </span>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:.83rem;">
                <thead>
                    <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
                        <th class="px-4 py-3 fw-semibold text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.7px;white-space:nowrap;">Date / Heure</th>
                        <th class="px-3 py-3 fw-semibold text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.7px;">Utilisateur</th>
                        <th class="px-3 py-3 fw-semibold text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.7px;">Action</th>
                        <th class="px-3 py-3 fw-semibold text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.7px;">Contexte</th>
                        <th class="px-3 py-3 fw-semibold text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.7px;">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    @php
                        $action = $log->action;
                        $isGreen = str_contains($action, 'Connexion') || str_contains($action, 'Inscription');
                        $isRed   = str_contains($action, 'supprimé') || str_contains($action, 'annulé') || str_contains($action, 'Déconnexion');
                        $badgeBg    = $isGreen ? 'var(--bs-success-bg-subtle)'  : ($isRed ? 'var(--bs-danger-bg-subtle)'  : 'var(--bs-secondary-bg)');
                        $badgeColor = $isGreen ? 'var(--bs-success-text-emphasis)' : ($isRed ? 'var(--bs-danger-text-emphasis)' : 'var(--bs-secondary-color)');
                    @endphp
                    <tr>
                        <td class="px-4 py-2 text-muted" style="white-space:nowrap;font-family:monospace;font-size:.78rem;">
                            {{ $log->created_at->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="px-3 py-2 fw-medium" style="white-space:nowrap;">{{ $log->user_name }}</td>
                        <td class="px-3 py-2">
                            <span style="background:{{ $badgeBg }};color:{{ $badgeColor }};font-weight:600;font-size:.75rem;padding:.25rem .55rem;border-radius:3px;white-space:nowrap;">
                                {{ $action }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-muted" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $log->context }}">
                            {{ $log->context ?? '-' }}
                        </td>
                        <td class="px-3 py-2 text-muted" style="font-family:monospace;font-size:.78rem;white-space:nowrap;">
                            {{ $log->ip_address ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-journal-x d-block mb-2" style="font-size:2rem;opacity:.3;"></i>
                            Aucune activité enregistrée
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
        <div class="px-4 py-3" style="border-top:1px solid rgba(255,255,255,.06)">
            {{ $logs->links() }}
        </div>
        @endif

    </div>
</div>
@endsection
