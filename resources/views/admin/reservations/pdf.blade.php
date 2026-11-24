<!DOCTYPE html>
{{-- Liste des reservations d'un jour, rendue en PDF par App\Services\PdfRenderer : styles en ligne, aucune ressource externe --}}
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Réservations du {{ $date->translatedFormat('j F Y') }}</title>
<style>
    @page { margin: 1.6cm 1.4cm 1.8cm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1f2933; }
    .site { font-size: 9pt; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
    h1 { font-size: 17pt; margin: 2pt 0 4pt; }
    .meta { color: #4b5563; margin-bottom: 14pt; }
    table { width: 100%; border-collapse: collapse; }
    thead th { text-align: left; font-size: 8.5pt; text-transform: uppercase; color: #4b5563; border-bottom: 1.5pt solid #1f2933; padding: 5pt 6pt; }
    tbody td { padding: 4pt 6pt; border-bottom: .5pt solid #d1d5db; vertical-align: top; }
    tr { page-break-inside: avoid; }
    .group td { background: #eef2f7; font-weight: bold; border-bottom: .5pt solid #9ca3af; padding-top: 7pt; }
    .nowrap { white-space: nowrap; }
    .muted { color: #6b7280; }
    .empty { text-align: center; color: #6b7280; padding: 24pt 0; }
    footer { position: fixed; bottom: -1.1cm; left: 0; right: 0; font-size: 8pt; color: #6b7280; }
    footer .page { float: right; }
    footer .page:after { content: "Page " counter(page); }
</style>
</head>
<body>

<footer>
    <span class="page"></span>
    Édité le {{ now()->translatedFormat('j F Y à H:i') }}
</footer>

<div class="site">{{ setting('app_name', 'NovaCreneaux') }}</div>
<h1>Réservations du {{ $date->translatedFormat('l j F Y') }}</h1>
<div class="meta">
    {{ $resource?->name ?? 'Toutes les ressources' }} - {{ $reservations->count() }} réservation{{ $reservations->count() > 1 ? 's' : '' }}
</div>

@if($reservations->isEmpty())
    <p class="empty">Aucune réservation pour cette date.</p>
@else
    <table>
        <thead>
            <tr>
                <th style="width:17%;">Horaire</th>
                <th style="width:22%;">Créneau</th>
                <th>Nom</th>
                <th style="width:15%;">Classe</th>
                <th style="width:14%;">Réservé le</th>
            </tr>
        </thead>
        <tbody>
        @foreach($reservations->groupBy(fn ($r) => $r->slot->resource->name) as $resourceName => $rows)
            <tr class="group"><td colspan="5">{{ $resourceName }} ({{ $rows->count() }})</td></tr>
            @foreach($rows as $r)
                <tr>
                    <td class="nowrap">{{ substr($r->slot->start_time, 0, 5) }} - {{ substr($r->slot->end_time, 0, 5) }}</td>
                    <td>{{ $r->slot->name }}</td>
                    <td>{{ $r->user->name }}</td>
                    <td>{{ $r->user->classe ?? '-' }}</td>
                    <td class="muted nowrap">{{ $r->created_at->format('d/m H:i') }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
@endif

</body>
</html>
