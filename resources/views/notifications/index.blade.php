@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="bi bi-bell me-2"></i>Notifications</h2>
            @if(auth()->user()->unreadNotificationCount() > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-all me-1"></i> Tout marquer comme lu</button>
                </form>
            @endif
        </div>

        @forelse($notifications as $notification)
            @php
                [$icon, $color] = match($notification->type) {
                    'success' => ['bi-check-circle-fill', 'success'],
                    'warning' => ['bi-exclamation-triangle-fill', 'warning'],
                    'danger'  => ['bi-x-octagon-fill', 'danger'],
                    default   => ['bi-info-circle-fill', 'info'],
                };
            @endphp
            <div class="card mb-2 {{ $notification->isRead() ? '' : 'border-' . $color }}">
                <div class="card-body d-flex gap-3">
                    <i class="bi {{ $icon }} text-{{ $color }} fs-5"></i>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between gap-2">
                            <strong>{{ $notification->title }}</strong>
                            <span class="text-body-secondary small text-nowrap">{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                        @if($notification->message)
                            <div class="small mt-1" style="white-space:pre-line">{{ $notification->message }}</div>
                        @endif
                        <div class="d-flex gap-2 mt-2">
                            @unless($notification->isRead())
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-link p-0">Marquer comme lu</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link p-0 text-danger">Supprimer</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center text-body-secondary py-5">
                <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                Aucune notification.
            </div>
        @endforelse

        {{ $notifications->links() }}
    </div>
</div>
@endsection
