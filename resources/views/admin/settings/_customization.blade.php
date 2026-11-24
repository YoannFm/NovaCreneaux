{{-- Textes et choix propres a l'etablissement (App\Support\Customization). Champ vide = valeur par defaut, affichee en exemple. --}}
@use('App\Support\Customization')
@php
    $default = fn (string $key) => Customization::GENERAL[$key][0];
    $value = fn (string $key) => old($key, $s[$key] ?? '');
    $text = [
        'app_tagline' => ['Sous-titre', 'Sous le nom du site, sur les pages de connexion.'],
        'email_placeholder' => ["Exemple d'adresse e-mail", 'Montré dans les champs e-mail (connexion, mot de passe oublié, inscription).'],
        'contact_label' => ['Qui contacter', 'Complète « contactez … » dans les e-mails et les messages de suspension. Ex. : le CDI.'],
    ];
@endphp

<h6 class="fw-semibold mt-4 mb-1">Personnalisation</h6>
<p class="text-muted small mb-3">Un champ vide reprend la valeur grisée.</p>

@foreach($text as $key => [$label, $help])
    <div class="mb-3">
        <label class="form-label" for="{{ $key }}">{{ $label }}</label>
        <input type="text" id="{{ $key }}" name="{{ $key }}" class="form-control @error($key) is-invalid @enderror"
               value="{{ $value($key) }}" placeholder="{{ $default($key) }}">
        <div class="form-text">{{ $help }}</div>
        @error($key)<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>
@endforeach

<div class="row gx-3">
    <div class="mb-3 col-md-4">
        <label class="form-label" for="user_term">Une personne qui réserve</label>
        <input type="text" id="user_term" name="user_term" class="form-control @error('user_term') is-invalid @enderror"
               value="{{ $value('user_term') }}" placeholder="{{ $default('user_term') }}">
    </div>
    <div class="mb-3 col-md-4">
        <label class="form-label" for="user_term_plural">Au pluriel</label>
        <input type="text" id="user_term_plural" name="user_term_plural" class="form-control @error('user_term_plural') is-invalid @enderror"
               value="{{ $value('user_term_plural') }}" placeholder="{{ $default('user_term_plural') }}">
    </div>
    <div class="mb-3 col-md-4">
        <label class="form-label" for="user_term_feminine">Genre</label>
        <select id="user_term_feminine" name="user_term_feminine" class="form-select">
            <option value="0" @selected($value('user_term_feminine') !== '1')>Masculin (un, le)</option>
            <option value="1" @selected($value('user_term_feminine') === '1')>Féminin (une, la)</option>
        </select>
    </div>
    <div class="form-text mt-n2 mb-3">Utilisé dans les messages : « un élève », « l'élève », « les élèves ».</div>
</div>

<div class="row gx-3">
    <div class="mb-3 col-md-6">
        <label class="form-label" for="group_label">Nom du champ « Classe »</label>
        <input type="text" id="group_label" name="group_label" class="form-control @error('group_label') is-invalid @enderror"
               value="{{ $value('group_label') }}" placeholder="{{ $default('group_label') }}">
        <div class="form-text">Ex. : Service, Section, Équipe.</div>
    </div>
    <div class="mb-3 col-md-6">
        <label class="form-label" for="group_placeholder">Exemple pour ce champ</label>
        <input type="text" id="group_placeholder" name="group_placeholder" class="form-control @error('group_placeholder') is-invalid @enderror"
               value="{{ $value('group_placeholder') }}" placeholder="{{ $default('group_placeholder') }}">
    </div>
</div>

<div class="mb-4" style="max-width:20rem;">
    <label class="form-label" for="default_theme">Thème par défaut</label>
    <select id="default_theme" name="default_theme" class="form-select">
        @foreach(Customization::THEMES as $theme => $label)
            <option value="{{ $theme }}" @selected(($value('default_theme') ?: $default('default_theme')) === $theme)>{{ $label }}</option>
        @endforeach
    </select>
    <div class="form-text">Tant que le visiteur n'a pas choisi lui-même avec le bouton soleil ou lune.</div>
</div>
