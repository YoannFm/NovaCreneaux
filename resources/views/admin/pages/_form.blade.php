@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header small fw-semibold">
        {{ isset($page) ? "Modifier « {$page->title} »" : 'Nouvelle page' }}
    </div>
    <div class="card-body">
        <form method="POST" action="{{ isset($page) ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
            @csrf
            @if(isset($page)) @method('PUT') @endif

            <div class="mb-3">
                <label class="form-label fw-semibold">Titre *</label>
                <input type="text" name="title" id="titleInput"
                       class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title', $page->title ?? '') }}" required maxlength="200">
                @error('title') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <input type="text" name="description"
                       class="form-control @error('description') is-invalid @enderror"
                       value="{{ old('description', $page->description ?? '') }}" maxlength="500">
                @error('description') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Slug</label>
                <div class="input-group">
                    <span class="input-group-text text-muted small">{{ setting('site_url', url('/')) }}/p/</span>
                    <input type="text" name="slug" id="slugInput"
                           class="form-control @error('slug') is-invalid @enderror"
                           value="{{ old('slug', $page->slug ?? '') }}" maxlength="200">
                    @error('slug') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Contenu *</label>
                <textarea name="content" id="pageContent"
                          class="@error('content') is-invalid @enderror">{{ old('content', $page->content ?? '') }}</textarea>
                @error('content') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="restricted" id="restrictedToggle" value="1"
                           {{ old('restricted', ($page->is_restricted ?? false) ? '1' : '') === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="restrictedToggle">Restreindre par rôle</label>
                </div>
            </div>

            <div id="rolesSection" class="mb-3"
                 style="{{ old('restricted', ($page->is_restricted ?? false) ? '1' : '') === '1' ? '' : 'display:none' }}">
                <div class="d-flex flex-wrap gap-3">
                    @foreach($roles as $role)
                        @php $checked = in_array($role->id, old('roles', isset($page) ? $page->roles->pluck('id')->toArray() : [])); @endphp
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="roles[]"
                                   value="{{ $role->id }}" id="role{{ $role->id }}" {{ $checked ? 'checked' : '' }}>
                            <label class="form-check-label" for="role{{ $role->id }}">
                                @if($role->icon)<i class="{{ $role->icon }} me-1"></i>@endif
                                <span class="badge" style="background:{{ $role->color ?? '#6c757d' }}">{{ $role->label }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_enabled" id="enabledToggle" value="1"
                           {{ old('is_enabled', isset($page) ? ($page->is_enabled ? '1' : '') : '1') ? 'checked' : '' }}>
                    <label class="form-check-label" for="enabledToggle">Active</label>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i> Enregistrer
                </button>
                <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

@push('footer-scripts')
<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script>
(function () {
    var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    tinymce.init({
        selector: '#pageContent',
        base_url: '{{ asset("vendor/tinymce") }}',
        license_key: 'gpl',
        promotion: false,
        height: 420,
        plugins: 'autolink link lists table code',
        toolbar: 'styles bold italic underline strikethrough | link | alignleft aligncenter alignright | bullist numlist | removeformat | code | undo redo',
        toolbar_mode: 'wrap',
        menubar: 'file edit view insert format tools table',
        skin: dark ? 'oxide-dark' : 'oxide',
        content_css: dark ? 'dark' : 'default',
        content_style: 'body { font-family: system-ui, sans-serif; font-size: 14px; }',
    });
})();

@if(!isset($page))
document.getElementById('titleInput').addEventListener('input', function () {
    document.getElementById('slugInput').value = this.value
        .toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
});
@endif

document.getElementById('restrictedToggle').addEventListener('change', function () {
    document.getElementById('rolesSection').style.display = this.checked ? '' : 'none';
});
</script>
@endpush

@endsection
