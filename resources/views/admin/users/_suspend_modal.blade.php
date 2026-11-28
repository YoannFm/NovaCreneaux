{{--
    Fenetre "Suspendre" partagee par la liste et la fiche d'un utilisateur. Un bouton l'ouvre avec
    data-bs-toggle="modal" data-bs-target="#suspend-modal" data-action="(route admin.sanctions.store)"
    data-user-id="..." data-name="...". Apres un refus de validation (sac "suspend"), elle se rouvre
    sur le meme compte avec la saisie.
--}}
@php
    $suspendErrors = $errors->suspend;
    $reopenFor = $suspendErrors->any() ? \App\Models\User::find(old('suspend_user_id')) : null;
    $permanent = old('permanent') === '1';
    $tomorrow = now()->addDay()->toDateString();
@endphp

<div class="modal fade" id="suspend-modal" tabindex="-1" aria-labelledby="suspend-title" aria-hidden="true"
     @if($reopenFor) data-reopen="1" @endif>
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="suspend-form"
              action="{{ $reopenFor ? route('admin.sanctions.store', $reopenFor) : '' }}">
            @csrf
            <input type="hidden" name="type" value="ban">
            <input type="hidden" name="suspend_user_id" id="suspend-user-id" value="{{ $reopenFor?->id }}">

            <div class="modal-header">
                <h5 class="modal-title" id="suspend-title">
                    <i class="bi bi-pause-circle me-1"></i> Suspendre <span id="suspend-name">{{ $reopenFor?->name }}</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Le compte reste accessible, mais ne peut plus réserver de créneau (site et API) pendant la suspension.
                </p>

                @if($suspendErrors->any())
                    <div class="alert alert-danger py-2 small">
                        @foreach($suspendErrors->all() as $message)<div>{{ $message }}</div>@endforeach
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label" for="suspend-reason">Raison</label>
                    <textarea class="form-control" id="suspend-reason" name="reason" rows="3" maxlength="1000" required
                              placeholder="Ex. comportement inapproprié le 24/11">{{ old('reason') }}</textarea>
                    <div class="form-text">Affichée lors de chaque tentative de réservation.</div>
                </div>

                <div class="mb-3">
                    <span class="form-label d-block">Gravité</span>
                    <div class="d-flex flex-wrap gap-2" role="radiogroup" aria-label="Gravité">
                        @foreach(\App\Models\Sanction::SEVERITIES as $key => [$label, $color])
                            <input type="radio" class="btn-check" name="severity" value="{{ $key }}" id="severity-{{ $key }}"
                                   autocomplete="off" required @checked(old('severity', 'moderate') === $key)>
                            <label class="btn btn-sm btn-outline-{{ $color }}" for="severity-{{ $key }}">{{ $label }}</label>
                        @endforeach
                    </div>
                </div>

                <div class="mb-2">
                    <span class="form-label d-block">Durée</span>
                    <div class="d-flex flex-wrap gap-3 mb-2">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="permanent" value="0" id="suspend-temporary" @checked(!$permanent)>
                            <label class="form-check-label" for="suspend-temporary">Temporaire</label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="permanent" value="1" id="suspend-permanent" @checked($permanent)>
                            <label class="form-check-label" for="suspend-permanent">Définitive</label>
                        </div>
                    </div>

                    <div id="suspend-until-group" @if($permanent) hidden @endif>
                        <label class="form-label small mb-1" for="suspend-until">Jusqu'au (inclus)</label>
                        <input type="date" class="form-control" id="suspend-until" name="banned_until" style="max-width:12rem;"
                               min="{{ $tomorrow }}" value="{{ old('banned_until') }}" @disabled($permanent) required>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            @foreach(['1 jour' => 1, '3 jours' => 3, '1 semaine' => 7, '2 semaines' => 14, '1 mois' => 30] as $label => $days)
                                <button type="button" class="btn btn-sm btn-outline-secondary js-suspend-days" data-days="{{ $days }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-warning"><i class="bi bi-pause-circle me-1"></i> Suspendre</button>
            </div>
        </form>
    </div>
</div>

@push('footer-scripts')
<script>
(function () {
    var modal = document.getElementById('suspend-modal');
    var form = document.getElementById('suspend-form');
    var until = document.getElementById('suspend-until');
    var untilGroup = document.getElementById('suspend-until-group');

    // Le bouton qui ouvre la fenetre dit quel compte suspendre
    modal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) return; // reouverture apres une erreur : le formulaire est deja rempli
        form.action = trigger.dataset.action;
        document.getElementById('suspend-user-id').value = trigger.dataset.userId;
        document.getElementById('suspend-name').textContent = trigger.dataset.name;
    });

    // Definitive : pas de date de fin (champ grise et non envoye)
    form.querySelectorAll('input[name="permanent"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var permanent = document.getElementById('suspend-permanent').checked;
            untilGroup.hidden = permanent;
            until.disabled = permanent;
        });
    });

    // Raccourcis de duree : date de fin = aujourd'hui + N jours (date locale)
    form.querySelectorAll('.js-suspend-days').forEach(function (button) {
        button.addEventListener('click', function () {
            var date = new Date();
            date.setDate(date.getDate() + parseInt(button.dataset.days, 10));
            until.value = [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
        });
    });

    // Bootstrap (admin.js) est charge en defer : disponible a DOMContentLoaded
    if (modal.dataset.reopen) {
        document.addEventListener('DOMContentLoaded', function () {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    }
})();
</script>
@endpush
