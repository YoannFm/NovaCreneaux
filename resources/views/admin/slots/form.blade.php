@extends('layouts.admin')
@php $pageTitle = $slot->exists ? 'Modifier un creneau' : 'Nouveau creneau'; @endphp
@section('content')

<div class="d-flex align-items-center gap-3 mb-3">
    <a href="{{ route('admin.slots.index', $resource) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> {{ $resource->name }}
    </a>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $slot->exists ? route('admin.slots.update', [$resource, $slot]) : route('admin.slots.store', $resource) }}">
                    @csrf @if($slot->exists) @method('PUT') @endif

                    <div class="mb-3">
                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               placeholder="ex: 12h00 - 13h00"
                               value="{{ old('name', $slot->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Heure debut <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control"
                                   value="{{ old('start_time', substr($slot->start_time ?? '', 0, 5)) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Heure fin <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control"
                                   value="{{ old('end_time', substr($slot->end_time ?? '', 0, 5)) }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Capacite maximale <span class="text-danger">*</span></label>
                        <input type="number" name="max_capacity" class="form-control @error('max_capacity') is-invalid @enderror"
                               min="1" max="500"
                               value="{{ old('max_capacity', $slot->max_capacity ?? 40) }}" required>
                        @error('max_capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                                {{ old('is_active', $slot->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Creneau actif</label>
                        </div>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                        <a href="{{ route('admin.slots.index', $resource) }}" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
