@extends('install.layout')
@section('steps')
<div class="step-indicator"><div class="step done"></div><div class="step active"></div><div class="step"></div><div class="step"></div><div class="step"></div></div>
@endsection
@section('content')
<h2 class="mb-1" style="font-weight:700">Prérequis</h2>
<p style="color:var(--text-muted)" class="mb-4">Vérification de l'environnement serveur.</p>
@foreach($checks as $check)
<div class="d-flex align-items-center justify-content-between py-2" style="border-bottom:1px solid var(--card-border)">
    <span>{{ $check['name'] }}@if($check['detail'] ?? null) <span style="color:var(--text-muted)" class="small">({{ $check['detail'] }})</span>@endif</span>
    @if($check['passed'])
        <span style="color:#22c55e"><i class="bi bi-check-circle-fill"></i></span>
    @else
        <span style="color:#ff5956"><i class="bi bi-x-circle-fill"></i></span>
    @endif
</div>
@endforeach
<div class="mt-4">
    @if($allPassed)
        <a href="{{ route('install.db-config') }}" class="btn btn-accent w-100">Continuer <i class="bi bi-arrow-right ms-1"></i></a>
    @else
        <div class="alert alert-danger small mb-3">Corrigez les erreurs ci-dessus avant de continuer.</div>
        <a href="{{ route('install.prerequisites') }}" class="btn btn-outline-secondary w-100">Réessayer</a>
    @endif
</div>
@endsection
