@extends('layouts.admin')
@php $pageTitle = 'Création d\'un rôle'; @endphp

@section('content')

<div class="card shadow mb-4">
    <div class="card-body">
        <form action="{{ route('admin.roles.store') }}" method="POST">
            @csrf

            @include('admin.roles._form', ['role' => null])

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Sauvegarder
            </button>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary ms-1">Annuler</a>
        </form>
    </div>
</div>

@endsection
