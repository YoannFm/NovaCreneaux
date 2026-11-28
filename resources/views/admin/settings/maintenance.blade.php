@extends('layouts.admin')
@php $pageTitle = 'Paramètres - Maintenance'; @endphp
@section('content')

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.maintenance.update') }}">
            @csrf

            <div class="mb-4 pb-4 border-bottom">
                <label class="form-label fw-semibold">Message de maintenance</label>
                <div class="form-text mb-2">Affiché à tous les utilisateurs (hors administrateurs) lorsque le mode maintenance est actif.</div>
                <textarea id="maintenance_message" name="maintenance_message"
                          class="form-control">{{ old('maintenance_message', $s['maintenance_message'] ?? '') }}</textarea>
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

<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script>
(function () {
    function initTiny() {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        if (tinymce.get('maintenance_message')) tinymce.remove('#maintenance_message');
        tinymce.init({
            selector: '#maintenance_message',
            base_url: '{{ asset('vendor/tinymce') }}',
            license_key: 'gpl', promotion: false, height: 220,
            plugins: 'searchreplace autolink code link',
            toolbar: 'bold italic underline | link | removeformat code | undo redo',
            skin: dark ? 'oxide-dark' : 'oxide',
            content_css: dark ? 'dark' : 'default',
        });
    }
    initTiny();
    new MutationObserver(function(m) { m.forEach(function(mm) { if (mm.attributeName === 'data-bs-theme') initTiny(); }); })
        .observe(document.documentElement, { attributes: true });
})();
</script>
@endsection
