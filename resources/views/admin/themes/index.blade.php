@extends('layouts.admin')
@section('title', 'Themes')
@php $pageTitle = 'Themes'; @endphp

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-palette2"></i>
                <span>Personnalisation du theme</span>
            </div>
            <div class="card-body">

                <ul class="nav nav-tabs mb-4" id="themeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dark-tab" data-bs-toggle="tab" data-bs-target="#tab-dark" type="button" role="tab">
                            <i class="bi bi-moon-fill me-1"></i> Mode sombre
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="light-tab" data-bs-toggle="tab" data-bs-target="#tab-light" type="button" role="tab">
                            <i class="bi bi-sun-fill me-1"></i> Mode clair
                        </button>
                    </li>
                </ul>

                <form method="POST" action="{{ route('admin.themes.update') }}">
                    @csrf
                    @method('PUT')

                    @if($errors->any())
                        <div class="alert alert-danger">Une couleur est invalide : utilisez le format #rrggbb.</div>
                    @endif

                    {{-- Couleur principale (partagee) --}}
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-circle-fill me-1" style="color:var(--accent-text)"></i>
                                Couleur principale (accent)
                            </label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="color" name="theme_accent" id="theme_accent"
                                       class="form-control form-control-color"
                                       value="{{ old('theme_accent', \App\Support\ThemeSettings::value(\App\Support\ThemeSettings::ACCENT)) }}"
                                       style="width:60px;height:38px;cursor:pointer;">
                                <input type="text" class="form-control font-monospace" id="theme_accent_hex"
                                       value="{{ old('theme_accent', \App\Support\ThemeSettings::value(\App\Support\ThemeSettings::ACCENT)) }}"
                                       placeholder="#efb703" maxlength="7" style="max-width:120px;">
                                <small class="text-muted">Boutons, liens actifs, surbrillance</small>
                            </div>
                        </div>
                    </div>

                    <div class="tab-content" id="themeTabsContent">
                        {{-- MODE SOMBRE --}}
                        <div class="tab-pane fade show active" id="tab-dark" role="tabpanel">
                            <div class="row g-3">
                                @foreach($dark as $name => $field)
                                    <div class="col-md-6 col-lg-3">
                                        @include('admin.themes._color_field', [
                                            'name'  => $name,
                                            'label' => $field['label'],
                                            'icon'  => $field['icon'],
                                            'value' => old($name, setting($name, $field['default'])),
                                        ])
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-generate-light">
                                    <i class="bi bi-magic me-1"></i> Generer le mode clair automatiquement
                                </button>
                            </div>
                        </div>

                        {{-- MODE CLAIR --}}
                        <div class="tab-pane fade" id="tab-light" role="tabpanel">
                            <div class="row g-3">
                                @foreach($light as $name => $field)
                                    <div class="col-md-6 col-lg-3">
                                        @include('admin.themes._color_field', [
                                            'name'  => $name,
                                            'label' => $field['label'],
                                            'icon'  => $field['icon'],
                                            'value' => old($name, setting($name, $field['default'])),
                                        ])
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Enregistrer le theme
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('footer-scripts')
<script>
(function () {
    // Sync hex input <-> color picker for accent
    const accentPicker = document.getElementById('theme_accent');
    const accentHex    = document.getElementById('theme_accent_hex');
    if (accentPicker && accentHex) {
        accentPicker.addEventListener('input', () => { accentHex.value = accentPicker.value; });
        accentHex.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(accentHex.value)) accentPicker.value = accentHex.value;
        });
    }

    // Sync all other color pickers with their hex inputs
    document.querySelectorAll('input[type="color"][data-hex-target]').forEach(picker => {
        const hexInput = document.getElementById(picker.dataset.hexTarget);
        if (!hexInput) return;
        picker.addEventListener('input', () => { hexInput.value = picker.value; });
        hexInput.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(hexInput.value)) picker.value = hexInput.value;
        });
    });

    // Auto-generate light mode from dark mode colors
    document.getElementById('btn-generate-light')?.addEventListener('click', () => {
        const map = {
            'theme_body_bg'  : 'theme_light_body_bg',
            'theme_card_bg'  : 'theme_light_card_bg',
            'theme_header_bg': 'theme_light_header_bg',
            'theme_text_color': 'theme_light_text_color',
        };
        for (const [darkKey, lightKey] of Object.entries(map)) {
            const darkVal = document.querySelector(`input[name="${darkKey}"][type="color"]`)?.value;
            if (!darkVal) continue;
            const light = invertColor(darkVal);
            const lightPicker = document.querySelector(`input[name="${lightKey}"][type="color"]`);
            const lightHex    = document.getElementById(lightKey + '_hex');
            if (lightPicker) lightPicker.value = light;
            if (lightHex)    lightHex.value    = light;
        }
        // Switch to light tab to show the result
        document.getElementById('light-tab')?.click();
    });

    function invertColor(hex) {
        const r = parseInt(hex.slice(1,3),16);
        const g = parseInt(hex.slice(3,5),16);
        const b = parseInt(hex.slice(5,7),16);
        // Simple lightness inversion
        const toHex = v => v.toString(16).padStart(2,'0');
        return '#' + toHex(255-r) + toHex(255-g) + toHex(255-b);
    }
})();
</script>
@endpush
