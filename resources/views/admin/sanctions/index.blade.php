@extends('layouts.admin')
@php $pageTitle = 'Sanctions'; @endphp
@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-people"></i> Gérer les utilisateurs
    </a>
</div>

@if($sanctions->isEmpty())
    <div class="card p-5 text-center">
        <i class="bi bi-shield-check" style="font-size:2.5rem;color:var(--mint)"></i>
        <p class="mt-3 mb-0" style="color:var(--muted-text)">Aucune sanction enregistrée.</p>
    </div>
@else
<div class="card p-4">
    <div class="table-responsive">
        <table class="table table-striped align-middle small">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Type</th>
                    <th>Raison</th>
                    <th>Suspendu jusqu'au</th>
                    <th>Appliqué par</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($sanctions as $s)
            <tr>
                <td><strong>{{ $s->user->name }}</strong><br><span style="color:var(--muted-text)">{{ $s->user->email }}</span></td>
                <td>
                    @if($s->type === 'warning')
                        <span class="badge-warning"><i class="bi bi-exclamation-triangle"></i> Avertissement</span>
                    @else
                        <span class="badge-danger"><i class="bi bi-slash-circle"></i> Ban</span>
                    @endif
                </td>
                <td style="max-width:260px;">{{ $s->reason }}</td>
                <td>
                    @if($s->type === 'warning') <span class="text-muted">-</span>
                    @elseif($s->isPermanent()) <span class="badge-danger">Permanent</span>
                    @else {{ $s->banned_until->format('d/m/Y') }} @if(!$s->isActive()) <span class="badge-accent ms-1">Expiré</span> @endif
                    @endif
                </td>
                <td>{{ $s->admin->name ?? '-' }}</td>
                <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.sanctions.destroy', $s) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Supprimer cette sanction ?')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $sanctions->links() }}
</div>
@endif
@endsection
