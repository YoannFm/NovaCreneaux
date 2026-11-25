{{-- Detail d'une entree du journal (fenetre de l'oeil) : phrase complete puis avant / apres (App\Support\ActivityChanges) --}}
@use('App\Support\ActivityChanges')
@php
    // Evenements de compte : une phrase plutot qu'un intitule
    $verbs = ['Connexion' => "s'est connecté(e)", 'Déconnexion' => "s'est déconnecté(e)", 'Inscription' => "s'est inscrit(e)"];
    $changes = $log->changes ?? [];
    $hasOld = collect($changes)->contains(fn ($change) => array_key_exists('old', $change));
    $hasNew = collect($changes)->contains(fn ($change) => array_key_exists('new', $change));
@endphp

<p class="mb-2">
    <strong>{{ $log->user_name }}</strong>
    {{ $verbs[$log->action] ?? '- ' . $log->action }}
    le {{ $log->created_at->format('d/m/Y') }} à {{ $log->created_at->format('H:i:s') }}
    @if($log->ip_address) depuis l'adresse IP <code>{{ $log->ip_address }}</code>@endif.
</p>
@if($log->context)
    <p class="text-muted small mb-3">{{ $log->context }}</p>
@endif

@if($changes)
    <div class="table-responsive">
        <table class="table table-sm align-top mb-0 small">
            <thead>
                <tr>
                    <th style="width:30%;">Champ</th>
                    @if($hasOld)<th>{{ $hasNew ? 'Avant' : 'Valeur supprimée' }}</th>@endif
                    @if($hasNew)<th>{{ $hasOld ? 'Après' : 'Valeur enregistrée' }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($changes as $field => $change)
                    <tr>
                        <td class="fw-medium">{{ ActivityChanges::label($field) }}</td>
                        @if($hasOld)<td class="text-break {{ $hasNew ? 'text-danger-emphasis' : '' }}">{{ array_key_exists('old', $change) ? ActivityChanges::display($change['old']) : '' }}</td>@endif
                        @if($hasNew)<td class="text-break {{ $hasOld ? 'text-success-emphasis' : '' }}">{{ array_key_exists('new', $change) ? ActivityChanges::display($change['new']) : '' }}</td>@endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-muted small mb-0">Pas de détail supplémentaire pour cette action.</p>
@endif
