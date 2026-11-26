{{-- Editeur de texte : fichiers locaux (aucun service distant), reglages communs a tous les editeurs de l'administration. --}}
<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script>
    tinymce.overrideDefaults({ base_url: @json(asset('vendor/tinymce')), license_key: 'gpl', promotion: false, language: 'fr-FR' });
</script>
