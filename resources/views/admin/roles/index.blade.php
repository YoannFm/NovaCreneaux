@extends('layouts.admin')
@php $pageTitle = 'Rôles'; @endphp

@push('footer-scripts')
<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
<script>
const sortable = Sortable.create(document.getElementById('roles'), {
    animation: 150,
    handle: '.sortable-handle',
});

const saveBtn = document.getElementById('roles-save');

function notify(type, icon, text) {
    document.querySelectorAll('#roles-alert').forEach(el => el.remove());
    const msg = document.createElement('div');
    msg.id = 'roles-alert';
    msg.className = 'alert alert-' + type + ' alert-dismissible mt-3';
    msg.innerHTML = '<i class="bi bi-' + icon + ' me-1"></i><span></span>' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    msg.querySelector('span').textContent = text;
    document.querySelector('.container-fluid').prepend(msg);
}

saveBtn.addEventListener('click', function () {
    saveBtn.disabled = true;
    const ids = [].slice.call(sortable.el.children).map(c => c.dataset.id).reverse();
    fetch('{{ route('admin.roles.reorder') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ roles: ids }),
    })
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(data => notify('success', 'check-circle', data.message))
    .catch(() => notify('danger', 'exclamation-triangle', "L'ordre n'a pas pu être enregistré."))
    .finally(() => saveBtn.disabled = false);
});
</script>
@endpush

@section('content')

<div class="card shadow mb-4">
    <div class="card-body">

        <p class="text-body-secondary small">Glissez les rôles avec <i class="bi bi-arrows-move"></i> : le plus haut de la liste est le plus élevé. Cliquez ensuite sur Sauvegarder.</p>

        <ol class="list-unstyled mb-3" id="roles">
            @foreach($roles as $role)
            <li data-id="{{ $role->id }}" style="margin-bottom:.5rem;">
                <div class="card shadow-none">
                    <div class="card-body d-flex justify-content-between align-items-center">

                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-arrows-move sortable-handle" style="cursor:grab;color:var(--text-muted);"></i>

                            <span class="badge" style="{{ $role->getBadgeStyle() }};font-size:1.05em;">
                                @if($role->icon)<i class="{{ $role->icon }} me-1"></i>@endif{{ $role->label }}
                            </span>

                            <span class="text-body-secondary small">
                                (ID: {{ $role->id }}, Pouvoir: {{ $role->power }})
                            </span>

                            @if($role->locked)
                                <i class="bi bi-star text-info" title="Rôle par défaut" data-bs-toggle="tooltip"></i>
                            @endif
                            @unless($role->api_access)
                                <span class="badge text-bg-secondary" title="Les comptes de ce rôle n'ont pas accès à l'API" data-bs-toggle="tooltip">Sans API</span>
                            @endunless
                            @if($role->is_admin)
                                <i class="bi bi-trophy text-warning" title="Administrateur" data-bs-toggle="tooltip"></i>
                            @endif
                        </span>

                        <span class="d-flex align-items-center gap-1">
                            <a href="{{ route('admin.roles.edit', $role) }}" class="m-1" title="Modifier" data-bs-toggle="tooltip">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            @if(!$role->locked)
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-link m-1 p-0 text-danger"
                                        onclick="return confirm({{ \Illuminate\Support\Js::from('Supprimer le rôle « ' . $role->label . ' » ?') }})"
                                        title="Supprimer" data-bs-toggle="tooltip">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endif
                        </span>

                    </div>
                </div>
            </li>
            @endforeach
        </ol>

        <button type="button" class="btn btn-success me-1" id="roles-save">
            <i class="bi bi-save me-1"></i> Sauvegarder
        </button>

        <a class="btn btn-primary" href="{{ route('admin.roles.create') }}">
            <i class="bi bi-plus-lg me-1"></i> Ajouter
        </a>

    </div>
</div>

@endsection
