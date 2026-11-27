@extends('layouts.admin')
@php $pageTitle = 'Paramètres - Général'; @endphp
@section('content')

<div class="card">
    <div class="card-body">

        {{-- Logo --}}
        <div class="mb-4 pb-4 border-bottom">
            <h6 class="fw-semibold mb-3">Logo du site</h6>
            @if($logo = \App\Support\SiteLogo::url())
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="{{ $logo }}" alt="Logo"
                         style="max-height:60px;max-width:180px;object-fit:contain;background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:6px;">
                    <form method="POST" action="{{ route('admin.settings.logo.delete') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i> Supprimer
                        </button>
                    </form>
                </div>
            @endif
            <form method="POST" action="{{ route('admin.settings.logo.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="input-group" style="max-width:400px;">
                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp" required>
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-upload"></i> Téléverser
                    </button>
                </div>
                <div class="form-text">PNG, JPG, GIF ou WebP, 2 Mo au plus.</div>
                @error('logo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </form>
        </div>

        <form method="POST" action="{{ route('admin.settings.general.update') }}">
            @csrf
            <div class="row gx-3">
                <div class="mb-3 col-md-6">
                    <label class="form-label">Nom du site <span class="text-danger">*</span></label>
                    <input type="text" name="app_name" class="form-control @error('app_name') is-invalid @enderror"
                           value="{{ old('app_name', $s['app_name'] ?? 'NovaCreneaux') }}" maxlength="100" required>
                    <div class="form-text">Affiché partout, dans les e-mails et dans l'application de double authentification.</div>
                    @error('app_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="mb-3 col-md-6">
                    <label class="form-label">URL du site</label>
                    <input type="url" name="site_url" class="form-control @error('site_url') is-invalid @enderror"
                           value="{{ old('site_url', $s['site_url'] ?? '') }}" placeholder="https://reservation.exemple.fr">
                    @error('site_url')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="site_description">Description du site</label>
                <textarea id="site_description" name="site_description" maxlength="{{ \App\Support\Customization::DESCRIPTION_MAX }}"
                          class="form-control @error('site_description') is-invalid @enderror"
                          rows="3">{{ old('site_description', setting('site_description', '')) }}</textarea>
                <div class="form-text">Une ou deux phrases, en texte simple : affichées par les moteurs de recherche sous le nom du site.</div>
                @error('site_description')<span class="invalid-feedback">{{ $message }}</span>@enderror
            </div>

            <div class="row gx-3">
                <div class="mb-3 col-md-6">
                    <label class="form-label">Fuseau horaire</label>
                    <select name="timezone" class="form-select @error('timezone') is-invalid @enderror">
                        @foreach($timezones as $tz)
                            <option value="{{ $tz }}" {{ old('timezone', $s['timezone'] ?? 'Europe/Paris') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
            </div>

            @include('admin.settings._customization')

            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

@endsection
