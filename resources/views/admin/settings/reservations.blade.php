@extends('layouts.admin')
@php $pageTitle = 'Paramètres - Réservations'; @endphp
@section('content')

<div class="card">
    <div class="card-body">
        <p class="text-muted mb-4" style="font-size:.875rem;">
            Limites appliquées par rôle. <strong>-1</strong> = illimité.
        </p>

        <form method="POST" action="{{ route('admin.settings.reservations.update') }}">
            @csrf

            <div class="table-responsive">
                <table class="table align-middle mb-4">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:140px;">Rôle</th>
                            <th class="text-center" style="min-width:120px;">
                                Max / jour
                                <i class="bi bi-info-circle text-muted ms-1"
                                   data-bs-toggle="tooltip" title="-1 = illimité"></i>
                            </th>
                            <th class="text-center" style="min-width:120px;">
                                Max / semaine
                                <i class="bi bi-info-circle text-muted ms-1"
                                   data-bs-toggle="tooltip" title="-1 = illimité"></i>
                            </th>
                            <th class="text-center" style="min-width:160px;">
                                Délai annulation (min)
                                <i class="bi bi-info-circle text-muted ms-1"
                                   data-bs-toggle="tooltip" title="Délai minimum avant le créneau pour annuler. -1 = toujours possible."></i>
                            </th>
                            <th class="text-center" style="min-width:140px;">
                                Jours à l'avance
                                <i class="bi bi-info-circle text-muted ms-1"
                                   data-bs-toggle="tooltip" title="Nombre de jours visibles à l'avance dans le calendrier."></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($roles as $role)
                    <tr>
                        <td>
                            <span class="badge" style="{{ $role->getBadgeStyle() }}">{{ $role->label }}</span>
                            @if($role->is_admin)
                                <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:.7rem;">admin</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <input type="number" name="roles[{{ $role->id }}][max_reservations_day]"
                                   class="form-control form-control-sm text-center"
                                   value="{{ $role->max_reservations_day }}"
                                   min="-1" max="99" style="width:75px;margin:0 auto;">
                        </td>
                        <td class="text-center">
                            <input type="number" name="roles[{{ $role->id }}][max_reservations_week]"
                                   class="form-control form-control-sm text-center"
                                   value="{{ $role->max_reservations_week }}"
                                   min="-1" max="99" style="width:75px;margin:0 auto;">
                        </td>
                        <td class="text-center">
                            <input type="number" name="roles[{{ $role->id }}][cancel_deadline_minutes]"
                                   class="form-control form-control-sm text-center"
                                   value="{{ $role->cancel_deadline_minutes }}"
                                   min="-1" max="10080" style="width:85px;margin:0 auto;">
                        </td>
                        <td class="text-center">
                            <input type="number" name="roles[{{ $role->id }}][advance_days]"
                                   class="form-control form-control-sm text-center"
                                   value="{{ $role->advance_days }}"
                                   min="1" max="365" style="width:75px;margin:0 auto;">
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
    new bootstrap.Tooltip(el);
});
</script>
@endsection
