{{-- Champ pour un secret : sa valeur n'est jamais reaffichee. Vide = conserver ; la case efface la valeur enregistree. --}}
@php($isSet = $secrets[$name] ?? false)
<input type="password" name="{{ $name }}" class="form-control" value="" autocomplete="new-password"
       placeholder="{{ $isSet ? 'Enregistré, inchangé' : ($placeholder ?? '') }}"
       @if($isSet) title="Un secret enregistré n'est jamais réaffiché. Laissez le champ vide pour le conserver." @endif>
@if($isSet)
    <div class="form-check mt-1">
        <input class="form-check-input" type="checkbox" name="clear_secrets[]" value="{{ $name }}" id="clear-{{ $name }}">
        <label class="form-check-label small" for="clear-{{ $name }}">Effacer la valeur enregistrée</label>
    </div>
@endif
