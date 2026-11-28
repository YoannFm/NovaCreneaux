@extends('install.layout')
@section('steps')
<div class="step-indicator"><div class="step done"></div><div class="step done"></div><div class="step done"></div><div class="step active"></div><div class="step"></div><div class="step"></div><div class="step"></div></div>
@endsection
@section('content')
<h2 class="mb-1" style="font-weight:700">Base de données</h2>
<p style="color:var(--text-muted)" class="mb-4">Initialisation des tables et paramètres par défaut.</p>
@if($errors->any())
<div class="alert alert-danger small">{{ $errors->first() }}</div>
@else
<div class="alert small mb-4" style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e">
    <i class="bi bi-check-circle me-1"></i> Tables créées et paramètres initialisés avec succès.
</div>
@endif
<a href="{{ route('install.site-config') }}" class="btn btn-accent w-100">Continuer <i class="bi bi-arrow-right ms-1"></i></a>
@endsection
