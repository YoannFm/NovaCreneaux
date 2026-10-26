@extends('layouts.admin')
@php $pageTitle = 'Paramètres - Authentification'; @endphp
@section('content')

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.auth.update') }}">
            @csrf

            <div class="mb-4 pb-4 border-bottom">
                <label class="form-label fw-semibold">Conditions d'inscription</label>
                <div class="form-text mb-2">Texte affiché et à accepter lors de l'inscription. Laisser vide pour désactiver.</div>
                <textarea id="registration_terms" name="registration_terms"
                          class="form-control">{{ old('registration_terms', $s['registration_terms'] ?? '') }}</textarea>
            </div>

            <div class="mb-4 pb-4 border-bottom">
                <label class="form-label fw-semibold">Domaines email autorisés</label>
                <div class="form-text mb-3">
                    Seules les adresses appartenant à ces domaines pourront s'inscrire.
                    Laisser vide pour accepter tous les domaines.
                </div>

                @php
                    $rawDomains = $s['allowed_email_domain'] ?? '';
                    $domainList = array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', $rawDomains))));
                    if (empty($domainList)) $domainList = [''];
                @endphp

                <div id="domain-list" style="max-width:380px;">
                    @foreach($domainList as $i => $d)
                    <div class="domain-row d-flex align-items-center gap-2 mb-2">
                        <input type="text"
                               name="allowed_email_domain[]"
                               class="form-control"
                               value="{{ $d }}"
                               placeholder="ex : rascol.net"
                               style="font-family:monospace;font-size:.875rem;">
                        <button type="button" class="btn btn-sm btn-outline-danger domain-remove"
                                title="Supprimer" tabindex="-1">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    @endforeach
                </div>

                <button type="button" id="domain-add"
                        class="btn btn-sm btn-outline-secondary mt-1">
                    <i class="bi bi-plus-lg me-1"></i> Ajouter un domaine
                </button>

                <div class="form-text mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Ex : <code>rascol.net</code> autorise <em>prenom@rascol.net</em>
                </div>
                @error('allowed_email_domain')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <h6 class="fw-semibold mb-0">Options</h6>
                @php
                    $switches = [
                        'registration_open'      => ['label' => "Autoriser l'inscription des utilisateurs",            'desc' => 'Désactiver pour fermer les inscriptions.'],
                        'allow_name_change'      => ['label' => 'Permettre aux utilisateurs de changer leur nom',      'desc' => 'Si désactivé, le nom est verrouillé après inscription.'],
                        'allow_account_deletion' => ['label' => 'Permettre aux utilisateurs de supprimer leur compte', 'desc' => 'Si désactivé, la suppression de compte est bloquée.'],
                    ];
                @endphp
                @foreach($switches as $key => $opt)
                <div class="d-flex justify-content-between align-items-start py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div style="flex:1;padding-right:2rem;">
                        <div class="fw-medium">{{ $opt['label'] }}</div>
                        <div class="text-muted" style="font-size:.8rem;">{{ $opt['desc'] }}</div>
                    </div>
                    <div class="form-check form-switch mb-0" style="flex-shrink:0;">
                        <input class="form-check-input" type="checkbox"
                               id="{{ $key }}" name="{{ $key }}" value="1"
                               style="width:2.5rem;height:1.25rem;cursor:pointer;"
                               {{ (($s[$key] ?? '0') === '1') ? 'checked' : '' }}>
                        <label class="form-check-label visually-hidden" for="{{ $key }}">{{ $opt['label'] }}</label>
                    </div>
                </div>
                @endforeach
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
    // TinyMCE
    function initTiny() {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        if (tinymce.get('registration_terms')) tinymce.remove('#registration_terms');
        tinymce.init({
            selector: '#registration_terms',
            base_url: '{{ asset('vendor/tinymce') }}',
            license_key: 'gpl', promotion: false, height: 280,
            plugins: 'searchreplace autolink code link lists',
            toolbar: 'blocks bold italic underline | link | bullist numlist | removeformat code | undo redo',
            skin: dark ? 'oxide-dark' : 'oxide',
            content_css: dark ? 'dark' : 'default',
        });
    }
    initTiny();
    new MutationObserver(function(m) { m.forEach(function(mm) { if (mm.attributeName === 'data-bs-theme') initTiny(); }); })
        .observe(document.documentElement, { attributes: true });

    // Domaines dynamiques
    var list = document.getElementById('domain-list');

    function makeRow(value) {
        var row = document.createElement('div');
        row.className = 'domain-row d-flex align-items-center gap-2 mb-2';
        row.innerHTML =
            '<input type="text" name="allowed_email_domain[]" class="form-control"' +
            ' value="' + (value || '') + '" placeholder="ex : rascol.net"' +
            ' style="font-family:monospace;font-size:.875rem;">' +
            '<button type="button" class="btn btn-sm btn-outline-danger domain-remove" title="Supprimer" tabindex="-1">' +
            '<i class="bi bi-x-lg"></i></button>';
        return row;
    }

    function updateRemoveButtons() {
        var rows = list.querySelectorAll('.domain-row');
        rows.forEach(function (r) {
            r.querySelector('.domain-remove').disabled = rows.length === 1;
        });
    }

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.domain-remove');
        if (!btn) return;
        var rows = list.querySelectorAll('.domain-row');
        if (rows.length > 1) { btn.closest('.domain-row').remove(); updateRemoveButtons(); }
    });

    document.getElementById('domain-add').addEventListener('click', function () {
        var row = makeRow('');
        list.appendChild(row);
        row.querySelector('input').focus();
        updateRemoveButtons();
    });

    updateRemoveButtons();
})();
</script>
@endsection
