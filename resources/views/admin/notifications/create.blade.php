@extends('layouts.admin')
@section('pageTitle', 'Envoyer une notification')
@section('content')

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-megaphone"></i>
                <span class="fw-semibold">Envoyer une notification</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.notifications.store') }}">
                    @csrf

                    {{-- Destinataires --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Destinataires</label>
                        <div class="d-flex gap-4 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input target-radio" type="radio" name="target"
                                       id="targetAll" value="all" {{ old('target', 'all') === 'all' ? 'checked' : '' }}>
                                <label class="form-check-label" for="targetAll">
                                    <i class="bi bi-people-fill me-1"></i> Tous
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input target-radio" type="radio" name="target"
                                       id="targetUsers" value="users" {{ old('target') === 'users' ? 'checked' : '' }}>
                                <label class="form-check-label" for="targetUsers">
                                    <i class="bi bi-person-fill me-1"></i> Utilisateurs spécifiques
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input target-radio" type="radio" name="target"
                                       id="targetRoles" value="roles" {{ old('target') === 'roles' ? 'checked' : '' }}>
                                <label class="form-check-label" for="targetRoles">
                                    <i class="bi bi-shield-fill me-1"></i> Par rôle
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Utilisateurs spécifiques --}}
                    <div class="mb-4 section-users" style="{{ old('target') === 'users' ? '' : 'display:none' }}">
                        <label class="form-label fw-semibold">Utilisateurs</label>
                        <select name="user_ids[]" class="form-select" multiple size="6">
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ in_array($user->id, old('user_ids', [])) ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Maintenez Ctrl/Cmd pour sélectionner plusieurs utilisateurs.</div>
                        @error('user_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    {{-- Par rôle --}}
                    <div class="mb-4 section-roles" style="{{ old('target') === 'roles' ? '' : 'display:none' }}">
                        <label class="form-label fw-semibold">Rôles</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($roles as $role)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="role_ids[]" value="{{ $role->id }}"
                                           id="role{{ $role->id }}"
                                           {{ in_array($role->id, old('role_ids', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="role{{ $role->id }}">
                                        @if($role->icon)<i class="{{ $role->icon }} me-1"></i>@endif
                                        {{ $role->label }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('role_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    {{-- Titre --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="title">Titre *</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror"
                               id="title" name="title" value="{{ old('title') }}" required maxlength="255">
                        @error('title')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    {{-- Message --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="content">
                            Message
                            <span class="text-muted fw-normal small">(facultatif, texte long accepté)</span>
                        </label>
                        <textarea class="@error('content') is-invalid @enderror"
                                  id="content" name="content" rows="5">{{ old('content') }}</textarea>
                        @error('content')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    {{-- Niveau --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="level">Niveau</label>
                        <select class="form-select @error('level') is-invalid @enderror" id="level" name="level">
                            <option value="info"    {{ old('level') === 'info'    ? 'selected' : '' }}>Information</option>
                            <option value="success" {{ old('level') === 'success' ? 'selected' : '' }}>Succès</option>
                            <option value="warning" {{ old('level') === 'warning' ? 'selected' : '' }}>Avertissement</option>
                            <option value="danger"  {{ old('level') === 'danger'  ? 'selected' : '' }}>Danger</option>
                        </select>
                        @error('level')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-megaphone me-1"></i> Envoyer
                        </button>
                        <a href="{{ route('admin.notifications.index') }}" class="btn btn-outline-secondary">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('footer-scripts')
<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script>
document.querySelectorAll('.target-radio').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelector('.section-users').style.display = this.value === 'users' ? '' : 'none';
        document.querySelector('.section-roles').style.display = this.value === 'roles' ? '' : 'none';
    });
});

(function () {
    var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    tinymce.init({
        selector: '#content',
        base_url: '{{ asset('vendor/tinymce') }}',
        license_key: 'gpl',
        promotion: false,
        height: 300,
        plugins: 'autolink link lists',
        toolbar: 'bold italic underline | link | bullist numlist | removeformat | undo redo',
        menubar: false,
        skin: dark ? 'oxide-dark' : 'oxide',
        content_css: dark ? 'dark' : 'default',
        content_style: 'body { font-family: system-ui, sans-serif; font-size: 14px; }',
    });
})();
</script>
@endpush

@endsection
