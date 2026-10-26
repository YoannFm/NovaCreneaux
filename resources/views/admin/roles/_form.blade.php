@push('footer-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const iconInput = document.getElementById('iconInput');
    if (iconInput) {
        iconInput.addEventListener('input', function () {
            document.getElementById('icon-preview').className = this.value || 'bi bi-star';
        });
    }
});
</script>
@endpush

@csrf

{{-- Nom | Icône | Couleur --}}
<div class="row gx-3">

    <div class="mb-3 col-md-5">
        <label class="form-label" for="labelInput">Nom</label>
        <input type="text" class="form-control @error('label') is-invalid @enderror"
               id="labelInput" name="label"
               value="{{ old('label', $role->label ?? '') }}" required autofocus>
        @error('label')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
    </div>

    <div class="mb-3 col-md-5">
        <label class="form-label" for="iconInput">Icône</label>
        <div class="input-group">
            <span class="input-group-text"><i id="icon-preview" class="{{ old('icon', $role->icon ?? '') ?: 'bi bi-star' }}"></i></span>
            <input type="text" class="form-control @error('icon') is-invalid @enderror"
                   id="iconInput" name="icon"
                   value="{{ old('icon', $role->icon ?? '') }}"
                   placeholder="bi bi-star">
        </div>
        @error('icon')<span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>@enderror
        <small class="form-text">
            Vous pouvez avoir la liste des icônes disponibles sur
            <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Bootstrap Icons</a>.
        </small>
    </div>

    <div class="mb-3 col-md-2">
        <label class="form-label" for="colorInput">Couleur</label>
        <input type="color"
               class="form-control form-control-color @error('color') is-invalid @enderror"
               id="colorInput" name="color"
               value="{{ old('color', $role->color ?? '#3b7ddd') }}" required
               style="width:100%;height:38px;cursor:pointer;padding:2px 4px;">
        @error('color')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
    </div>

</div>

{{-- Permissions --}}
<h3>Permissions</h3>

<div class="mb-3">
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="adminSwitch" name="is_admin" value="1"
               @checked(old('is_admin', $role->is_admin ?? false))
               @disabled(isset($role) && $role->locked)
               data-bs-toggle="collapse" data-bs-target="#permissionsGroup"
               style="width:2.2rem;height:1.1rem;cursor:pointer;">
        <label class="form-check-label" for="adminSwitch">Administrateur</label>
    </div>
    <small class="form-text text-info">
        Lorsque le rôle est administrateur, il a toutes les permissions.
        @if(isset($role) && $role->locked)
            <em class="text-body-secondary">(Verrouillé — rôle système)</em>
        @endif
    </small>
</div>

<div class="mb-3">
    <input type="hidden" name="api_access" value="0">
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="apiAccessSwitch" name="api_access" value="1"
               @checked(old('api_access', $role->api_access ?? true))
               style="width:2.2rem;height:1.1rem;cursor:pointer;">
        <label class="form-check-label" for="apiAccessSwitch">Accès à l'API</label>
    </div>
    <small class="form-text text-info">
        Permet aux comptes de ce rôle de créer leurs propres jetons d'API depuis leur profil.
        Sans cet accès, les jetons existants de ces comptes cessent de fonctionner.
    </small>
</div>

{{-- Limites (collapsées quand admin est actif) --}}
<div id="permissionsGroup" class="{{ (old('is_admin', $role->is_admin ?? false)) ? 'collapse' : '' }}">
    <div class="card card-body mb-3">
        <h5 class="mb-1" style="font-size:1rem;font-weight:600;">Accès à l'administration</h5>
        <p class="text-body-secondary small">
            Les comptes de ce rôle peuvent gérer les sections cochées. Les réglages, rôles, mises à jour,
            sauvegardes et jetons d'API restent réservés aux administrateurs, de même que la gestion des comptes administrateurs.
        </p>
        @php $granted = old('permissions', $role->permissions ?? []); @endphp
        <div class="row">
            @foreach(\App\Support\Permission::all() as $key => $label)
                <div class="col-md-6">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="perm-{{ $key }}"
                               name="permissions[]" value="{{ $key }}" @checked(in_array($key, $granted, true))>
                        <label class="form-check-label" for="perm-{{ $key }}">{{ $label }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        @error('permissions.*')<span class="text-danger small">{{ $message }}</span>@enderror
    </div>
    <div class="card card-body mb-3 pb-0">
        <h5 class="mb-3" style="font-size:1rem;font-weight:600;">Limites de réservation</h5>
        <div class="row gx-3">
            <div class="col-sm-6 col-md-3 mb-3">
                <label class="form-label">Max / jour</label>
                <input type="number" name="max_reservations_day" class="form-control"
                       value="{{ old('max_reservations_day', $role->max_reservations_day ?? 2) }}"
                       min="1" max="99">
                <div class="form-text" style="font-size:.73rem;">Réservations par jour.</div>
            </div>
            <div class="col-sm-6 col-md-3 mb-3">
                <label class="form-label">Max / semaine</label>
                <input type="number" name="max_reservations_week" class="form-control"
                       value="{{ old('max_reservations_week', $role->max_reservations_week ?? 5) }}"
                       min="1" max="99">
                <div class="form-text" style="font-size:.73rem;">Réservations par semaine.</div>
            </div>
            <div class="col-sm-6 col-md-3 mb-3">
                <label class="form-label">Annulation (min)</label>
                <input type="number" name="cancel_deadline_minutes" class="form-control"
                       value="{{ old('cancel_deadline_minutes', $role->cancel_deadline_minutes ?? 30) }}"
                       min="0" max="1440">
                <div class="form-text" style="font-size:.73rem;">Délai avant le créneau.</div>
            </div>
            <div class="col-sm-6 col-md-3 mb-3">
                <label class="form-label">Réserver à l'avance (j)</label>
                <input type="number" name="advance_days" class="form-control"
                       value="{{ old('advance_days', $role->advance_days ?? 14) }}"
                       min="1" max="365">
                <div class="form-text" style="font-size:.73rem;">Jours max à l'avance.</div>
            </div>
        </div>
    </div>
</div>
