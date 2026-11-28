@extends('install.layout')
@section('steps')
<div class="step-indicator"><div class="step active"></div><div class="step"></div><div class="step"></div><div class="step"></div><div class="step"></div></div>
@endsection
@section('content')
<h2 class="mb-1" style="font-weight:700">Bienvenue !</h2>
<p style="color:var(--text-muted)" class="mb-4">NovaCreneaux est un logiciel flexible de réservation de créneaux. Cet assistant va vous guider pour configurer votre installation en quelques étapes.</p>
<ul class="list-unstyled mb-4">
    @foreach(['Gestion multi-ressources configurables','Réservations avec règles flexibles','Interface admin complète','Import CSV d\'utilisateurs','Notifications email'] as $f)
    <li class="py-2" style="border-bottom:1px solid var(--card-border);color:var(--text-muted)">
        <i class="bi bi-check-circle me-2" style="color:#22c55e"></i>{{ $f }}
    </li>
    @endforeach
</ul>
<a href="{{ route('install.prerequisites') }}" class="btn btn-accent w-100">Commencer l'installation <i class="bi bi-arrow-right ms-1"></i></a>
@endsection
