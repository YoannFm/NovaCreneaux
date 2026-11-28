@extends('layouts.admin')
@section('pageTitle', 'Notifications')
@section('content')

<div class="d-flex justify-content-end mb-4">
    <a href="{{ route('admin.notifications.create') }}" class="btn btn-primary">
        <i class="bi bi-megaphone me-1"></i> Envoyer une notification
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Utilisateur</th>
                        <th>Titre</th>
                        <th>Type</th>
                        <th>Lu</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $notif)
                        @php
                            [$badge, $label] = match($notif->type) {
                                'success' => ['bg-success',              'Succes'],
                                'warning' => ['bg-warning text-dark',    'Avertissement'],
                                'danger'  => ['bg-danger',               'Danger'],
                                default   => ['bg-info text-dark',       'Information'],
                            };
                        @endphp
                        <tr>
                            <td class="text-muted small">{{ $notif->id }}</td>
                            <td>{{ $notif->user->name ?? '-' }}</td>
                            <td>{{ $notif->title }}</td>
                            <td><span class="badge {{ $badge }}">{{ $label }}</span></td>
                            <td>
                                @if($notif->read_at)
                                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Oui</span>
                                @else
                                    <span class="text-muted"><i class="bi bi-circle me-1"></i>Non</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $notif->created_at->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.notifications.destroy', $notif) }}"
                                      id="del-notif-{{ $notif->id }}">
                                    @csrf @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="document.getElementById('confirmDeleteForm').action='{{ route('admin.notifications.destroy', $notif) }}';new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-bell-slash d-block mb-2" style="font-size:2rem;opacity:.3"></i>
                                Aucune notification.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($notifications->hasPages())
<div class="mt-4">{{ $notifications->links() }}</div>
@endif

@endsection
