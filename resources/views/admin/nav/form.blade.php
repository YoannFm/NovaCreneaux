@extends('layouts.admin')
@php
    $pageTitle = $item ? 'Édition l\'élément de la navbar ' . $item->id : 'Ajouter un élément';
    $action    = $item ? route('admin.nav.update', $item->id) : route('admin.nav.store');
    $method    = $item ? 'PUT' : 'POST';
@endphp

@section('content')

<div class="mb-3">
    <a href="{{ route('admin.nav.index') }}" class="text-muted small" style="text-decoration:none;">
        <i class="bi bi-arrow-left me-1"></i>Retour à la navigation
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $action }}">
            @csrf
            @if($method === 'PUT') @method('PUT') @endif

            @if($errors->any())
                <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
            @endif

            {{-- Ligne 1 : Nom + Icône + Type --}}
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                    <input type="text" name="label"
                           class="form-control @error('label') is-invalid @enderror"
                           value="{{ old('label', $item?->label) }}"
                           placeholder="Accueil" maxlength="60" required autofocus>
                    @error('label')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Icône</label>
                    <div class="input-group">
                        <span class="input-group-text" id="icon-preview-wrap" style="min-width:42px;justify-content:center;">
                            <i class="bi {{ old('icon', $item?->icon ?? 'bi-link-45deg') }}" id="icon-preview-i"></i>
                        </span>
                        <input type="text" name="icon" id="f-icon"
                               class="form-control font-monospace"
                               value="{{ old('icon', $item?->icon) }}"
                               placeholder="bi-link-45deg" maxlength="60"
                               oninput="updateIcon(this.value)">
                    </div>
                    <div class="form-text">
                        Cherchez sur <a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">Bootstrap Icons</a>
                        - copiez le nom de la classe (ex : <code>bi-house</code>).
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                    <select name="type" id="f-type" class="form-select" onchange="updateType(this.value)">
                        @foreach($typeOptions as $value => $label)
                        <option value="{{ $value }}" {{ old('type', $item?->type ?? 'external') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- URL - visible seulement si type = external --}}
            <div class="mb-4" id="url-row" style="{{ old('type', $item?->type ?? 'external') !== 'external' ? 'display:none;' : '' }}">
                <label class="form-label fw-semibold">URL <span class="text-danger">*</span></label>
                <input type="text" name="url"
                       class="form-control @error('url') is-invalid @enderror"
                       value="{{ old('url', $item?->url) }}"
                       placeholder="https://… ou /chemin/interne" maxlength="500"
                       id="f-url">
                @error('url')<span class="invalid-feedback">{{ $message }}</span>@enderror
            </div>

            <hr style="border-color:rgba(255,255,255,.08);margin: 1.25rem 0;">

            {{-- Toggles --}}
            <div class="d-flex flex-column gap-3 mb-4">
                <div class="d-flex align-items-center justify-content-between gap-3" style="max-width:420px;">
                    <div>
                        <div class="fw-medium" style="font-size:.88rem;">Ouvrir dans un nouvel onglet</div>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="target_blank" id="t-blank"
                               style="width:2.2rem;height:1.1rem;cursor:pointer;"
                               onchange="document.getElementById('f-target').value = this.checked ? '_blank' : '_self'"
                               {{ old('target', $item?->target) === '_blank' ? 'checked' : '' }}>
                    </div>
                </div>
                <input type="hidden" name="target" id="f-target"
                       value="{{ old('target', $item?->target ?? '_self') }}">

                <div class="d-flex align-items-center justify-content-between gap-3" style="max-width:420px;">
                    <div>
                        <div class="fw-medium" style="font-size:.88rem;">Visible</div>
                        <div class="text-muted" style="font-size:.75rem;">Afficher ce lien dans la navigation</div>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="t-active"
                               style="width:2.2rem;height:1.1rem;cursor:pointer;"
                               {{ old('is_active', $item?->is_active ?? true) ? 'checked' : '' }}>
                    </div>
                </div>
            </div>

            <hr style="border-color:rgba(255,255,255,.08);margin: 1.25rem 0;">

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-floppy me-1"></i> Sauvegarder
                </button>
                @if($item)
                {{-- Le formulaire de suppression est plus bas, hors de celui-ci : deux formulaires ne s'imbriquent pas --}}
                <button type="submit" form="delete-nav" class="btn btn-danger"
                        onclick="return confirm('Supprimer « {{ $item->label }} » ?')">
                    <i class="bi bi-trash me-1"></i> Supprimer
                </button>
                @endif
                <a href="{{ route('admin.nav.index') }}" class="btn btn-outline-secondary">Annuler</a>
            </div>
        </form>

        @if($item)
        <form id="delete-nav" method="POST" action="{{ route('admin.nav.destroy', $item->id) }}" class="d-none">
            @csrf @method('DELETE')
        </form>
        @endif
    </div>
</div>

<script>
function updateIcon(val) {
    document.getElementById('icon-preview-i').className = 'bi ' + (val || 'bi-link-45deg');
}

function updateType(val) {
    document.getElementById('url-row').style.display = val === 'external' ? '' : 'none';
    if (val !== 'external') {
        document.getElementById('f-url').removeAttribute('required');
    } else {
        document.getElementById('f-url').setAttribute('required', '');
    }
}
</script>
@endsection
