@extends('layouts.admin')
@php $pageTitle = 'Paramètres - Maintenance'; @endphp
@section('content')

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.maintenance.update') }}">
            @csrf

            <div class="mb-4 pb-4 border-bottom">
                <label class="form-label fw-semibold" for="maintenance_message">Message de maintenance</label>
                <div class="form-text mb-2">Affiché à tous les utilisateurs (hors administrateurs) lorsque le mode maintenance est actif. Texte simple, 500 caractères au plus.</div>
                <textarea id="maintenance_message" name="maintenance_message" rows="3" maxlength="500"
                          class="form-control @error('maintenance_message') is-invalid @enderror">{{ old('maintenance_message', \App\Support\Customization::plainText($s['maintenance_message'] ?? '')) }}</textarea>
                @error('maintenance_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex justify-content-between align-items-center py-3">
                <div>
                    <div class="fw-medium">Activer le mode maintenance</div>
                    <div class="text-muted" style="font-size:.8rem;">Les utilisateurs non-administrateurs verront le message de maintenance.</div>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox"
                           id="maintenance_mode" name="maintenance_mode" value="1"
                           style="width:2.5rem;height:1.25rem;cursor:pointer;"
                           {{ (($s['maintenance_mode'] ?? '0') === '1') ? 'checked' : '' }}>
                    <label class="form-check-label visually-hidden" for="maintenance_mode">Mode maintenance</label>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
