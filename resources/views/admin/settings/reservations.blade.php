@extends('layouts.admin')
@php
    $pageTitle = 'Paramètres - Réservations';
    // Apres un refus de validation, l'etat des interrupteurs vient du formulaire envoye, pas de la base
    $resubmitted = session()->hasOldInput();
@endphp
@section('content')

<p class="text-muted mb-3">
    Limites appliquées aux comptes de chaque rôle, sur le site comme par l'API.
</p>

@if($errors->any())
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> Rien n'a été enregistré : corrigez les valeurs signalées.
    </div>
@endif

<form method="POST" action="{{ route('admin.settings.reservations.update') }}">
    @csrf

    <div class="row g-3">
        @foreach($roles as $role)
        <div class="col-12 col-xl-6">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center flex-wrap gap-2">
                    <span class="badge" style="{{ $role->getBadgeStyle() }}">
                        @if($role->icon)<i class="{{ $role->icon }} me-1"></i>@endif{{ $role->label }}
                    </span>
                    <span class="text-muted small">{{ $role->users_count }} compte{{ $role->users_count > 1 ? 's' : '' }}</span>
                    @if($role->is_admin)
                        <span class="badge bg-primary-subtle text-primary-emphasis ms-auto">Administrateurs</span>
                    @endif
                </div>
                <div class="card-body py-1">
                    @foreach($fields as $key => $field)
                        @php
                            $id = "limit-{$role->id}-{$key}";
                            $path = "roles.{$role->id}";
                            $unlimited = $field['unlimited'] !== null && ($resubmitted
                                ? (bool) old("{$path}.unlimited.{$key}")
                                : $role->{$key} === \App\Support\ReservationLimits::UNLIMITED);
                        @endphp
                        <div class="limit-row d-flex flex-wrap align-items-center gap-3 py-3 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="stat flex-shrink-0"><i class="bi {{ $field['icon'] }}"></i></div>
                            <div class="flex-grow-1 limit-label">
                                <label class="fw-medium d-block" for="{{ $id }}">{{ $field['label'] }}</label>
                                <div class="text-muted small">{{ $field['help'] }}</div>
                            </div>
                            <div class="limit-input">
                                <div class="input-group input-group-sm">
                                    <input type="number" id="{{ $id }}" name="roles[{{ $role->id }}][{{ $key }}]"
                                           class="form-control text-end @error("{$path}.{$key}") is-invalid @enderror"
                                           value="{{ old("{$path}.{$key}", $unlimited ? '' : $role->{$key}) }}"
                                           min="{{ $field['min'] }}" max="{{ $field['max'] }}" required @disabled($unlimited)
                                           @if($field['unlimited'] !== null) placeholder="∞" @endif>
                                    <span class="input-group-text">{{ $field['unit'] }}</span>
                                </div>
                                @if($field['unlimited'] !== null)
                                    <div class="form-check form-switch small mt-1 mb-0">
                                        <input class="form-check-input js-unlimited" type="checkbox" value="1"
                                               id="{{ $id }}-unlimited" name="roles[{{ $role->id }}][unlimited][{{ $key }}]"
                                               data-target="{{ $id }}" @checked($unlimited)>
                                        <label class="form-check-label" for="{{ $id }}-unlimited">{{ $field['unlimited'] }}</label>
                                    </div>
                                @endif
                                @error("{$path}.{$key}")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i> Enregistrer
        </button>
    </div>
</form>

@push('header-styles')
<style>
    /* Libelle et saisie sur une ligne ; la saisie passe dessous seulement sur un ecran etroit */
    .limit-label { flex: 1 1 9rem; min-width: 9rem; }
    .limit-input { width: 10.5rem; }
    .limit-input .input-group-text { width: 5.5rem; justify-content: center; }
    .limit-row .stat { font-size: 1.1rem; color: var(--primary); }
</style>
@endpush

@push('footer-scripts')
<script>
// "Illimite" coche : le champ est grise et n'est pas envoye ; decoche, il redevient obligatoire
document.querySelectorAll('.js-unlimited').forEach(function (toggle) {
    var input = document.getElementById(toggle.dataset.target);
    toggle.addEventListener('change', function () {
        input.disabled = toggle.checked;
        if (!toggle.checked) input.focus();
    });
});
</script>
@endpush

@endsection
