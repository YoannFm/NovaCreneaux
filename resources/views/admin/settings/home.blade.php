@extends('layouts.admin')
@php $pageTitle = 'Paramètres - Accueil'; @endphp
@section('content')

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.home.update') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label fw-semibold">Message affiché sur la page d'accueil</label>
                <div class="form-text mb-2">Visible par tous les utilisateurs connectés en haut de la page de réservation. Laisser vide pour ne rien afficher.</div>
                <textarea id="home_message" name="home_message"
                          class="form-control">{{ old('home_message', $s['home_message'] ?? '') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script>
(function () {
    function initTiny() {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        if (tinymce.get('home_message')) tinymce.remove('#home_message');
        tinymce.init({
            selector: '#home_message',
            base_url: '{{ asset('vendor/tinymce') }}',
            license_key: 'gpl', promotion: false, height: 300,
            plugins: 'searchreplace autolink code link lists',
            toolbar: 'blocks bold italic underline | link | alignleft aligncenter alignright | bullist numlist | removeformat code | undo redo',
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
