@extends('layouts.admin')
@php $pageTitle = $resource->exists ? 'Modifier une ressource' : 'Nouvelle ressource'; @endphp
@section('content')

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $resource->exists ? route('admin.resources.update', $resource) : route('admin.resources.store') }}">
                    @csrf @if($resource->exists) @method('PUT') @endif

                    <div class="mb-3">
                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $resource->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $resource->description) }}</textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Heure ouverture (veille) <span class="text-danger">*</span></label>
                            <input type="time" name="open_time" class="form-control"
                                   value="{{ old('open_time', substr($resource->open_time ?? '17:00:00', 0, 5)) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Heure fermeture (jour J) <span class="text-danger">*</span></label>
                            <input type="time" name="close_time" class="form-control"
                                   value="{{ old('close_time', substr($resource->close_time ?? '11:30:00', 0, 5)) }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jours fermes</label>
                        <div class="d-flex gap-3 flex-wrap">
                            @foreach(['1' => 'Lun', '2' => 'Mar', '3' => 'Mer', '4' => 'Jeu', '5' => 'Ven', '6' => 'Sam', '7' => 'Dim'] as $n => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="closed_days[]" value="{{ $n }}" id="d{{ $n }}"
                                    {{ in_array($n, old('closed_days', $resource->closed_days ?? [])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="d{{ $n }}">{{ $label }}</label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                                {{ old('is_active', $resource->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Ressource active</label>
                        </div>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                        <a href="{{ route('admin.resources.index') }}" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
