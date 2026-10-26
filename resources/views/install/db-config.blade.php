@extends('install.layout')
@section('steps')
<div class="step-indicator"><div class="step done"></div><div class="step done"></div><div class="step active"></div><div class="step"></div><div class="step"></div><div class="step"></div></div>
@endsection
@section('content')
<h2 class="mb-1" style="font-weight:700">Base de données</h2>
<p style="color:var(--text-muted)" class="mb-4">Renseignez les identifiants de connexion MySQL.</p>

@if($errors->any())
<div class="alert alert-danger small mb-3">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('install.db-config.store') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-semibold">Hôte</label>
        <input type="text" name="db_host" class="form-control" value="{{ old('db_host', 'localhost') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold">Port</label>
        <input type="text" name="db_port" class="form-control" value="{{ old('db_port', '3306') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold">Nom de la base de données</label>
        <input type="text" name="db_database" class="form-control" value="{{ old('db_database', 'novacreneaux') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold">Utilisateur</label>
        <input type="text" name="db_username" class="form-control" value="{{ old('db_username', 'novacreneaux_user') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold">Mot de passe</label>
        <input type="password" name="db_password" class="form-control" value="{{ old('db_password') }}">
    </div>
    <button type="submit" class="btn btn-accent w-100 mt-2">Tester et continuer <i class="bi bi-arrow-right ms-1"></i></button>
</form>
@endsection
