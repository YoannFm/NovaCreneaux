@extends('layouts.admin')
@php $pageTitle = 'Édition du rôle ' . $role->label . ' (#' . $role->id . ')'; @endphp

@section('content')

<div class="card shadow mb-4">
    <div class="card-body">
        <form action="{{ route('admin.roles.update', $role) }}" method="POST">
            @csrf @method('PUT')

            @include('admin.roles._form', ['role' => $role])

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Sauvegarder
            </button>

            @if(!$role->locked)
            {{-- Le formulaire de suppression est plus bas, hors de celui-ci : deux formulaires ne s'imbriquent pas --}}
            <button type="submit" form="delete-role" class="btn btn-danger ms-1"
                    onclick="return confirm({{ \Illuminate\Support\Js::from('Supprimer le rôle « ' . $role->label . ' » ?') }})">
                <i class="bi bi-trash me-1"></i> Supprimer
            </button>
            @endif

            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary ms-1">Annuler</a>
        </form>

        @if(!$role->locked)
        <form id="delete-role" method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-none">
            @csrf @method('DELETE')
        </form>
        @endif
    </div>
</div>

@endsection
