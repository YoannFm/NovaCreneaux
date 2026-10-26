@extends('install.layout')
@section('steps')
<div class="step-indicator"><div class="step done"></div><div class="step done"></div><div class="step done"></div><div class="step done"></div><div class="step active"></div><div class="step"></div><div class="step"></div></div>
@endsection
@section('content')
<h2 class="mb-1" style="font-weight:700">Configuration du site</h2>
<p style="color:var(--text-muted)" class="mb-4">Paramètres généraux de votre application.</p>

@if($errors->any())
<div class="alert alert-danger small mb-3">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('install.site-config.store') }}">
    @csrf

    <div class="mb-3">
        <label class="form-label small fw-semibold">Nom du site</label>
        <input type="text" name="app_name" class="form-control" value="{{ old('app_name', 'NovaCreneaux') }}" required>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">URL du site</label>
        <input type="url" name="app_url" class="form-control" value="{{ old('app_url', 'http://localhost:8000') }}" required>
        <div class="form-text" style="color:var(--text-muted)">Sans slash final. Ex : https://monsite.fr</div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Fuseau horaire</label>
        <select name="timezone" class="form-select">
            @php $current = old('timezone', 'Europe/Paris'); @endphp
            @foreach(timezone_identifiers_list() as $tz)
                <option value="{{ $tz }}" @selected($tz === $current)>{{ $tz }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Réservations max par jour (par utilisateur)</label>
        <input type="number" name="max_reservations_per_day" class="form-control" value="{{ old('max_reservations_per_day', 1) }}" min="1" max="100" required>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Inscriptions</label>
        <select name="registration_open" class="form-select">
            <option value="1" @selected(old('registration_open', '0') === '1')>Ouvertes</option>
            <option value="0" @selected(old('registration_open', '0') === '0')>Fermées (sur invitation)</option>
        </select>
    </div>

    <button type="submit" class="btn btn-accent w-100 mt-2">Continuer <i class="bi bi-arrow-right ms-1"></i></button>
</form>
@endsection
