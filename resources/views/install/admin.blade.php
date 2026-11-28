@extends('install.layout')
@section('steps')
<div class="step-indicator"><div class="step done"></div><div class="step done"></div><div class="step done"></div><div class="step done"></div><div class="step done"></div><div class="step active"></div><div class="step"></div></div>
@endsection
@section('content')
<h2 class="mb-1" style="font-weight:700">Compte administrateur</h2>
<p style="color:var(--text-muted)" class="mb-4">Créez le compte administrateur principal.</p>
<form method="POST" action="{{ route('install.admin.store') }}">
    @csrf
    <div class="mb-3"><label class="form-label">Nom *</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
    <div class="mb-3"><label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
    <div class="mb-3"><label class="form-label">Mot de passe * <span style="color:var(--text-muted)" class="small">(min. 8 caractères)</span></label>
        <input type="password" name="password" class="form-control" required></div>
    <div class="mb-4"><label class="form-label">Confirmer le mot de passe *</label>
        <input type="password" name="password_confirmation" class="form-control" required></div>
    @if($errors->any())<div class="alert alert-danger small mb-3">{{ $errors->first() }}</div>@endif
    <button type="submit" class="btn btn-accent w-100">Créer le compte <i class="bi bi-arrow-right ms-1"></i></button>
</form>
@endsection
