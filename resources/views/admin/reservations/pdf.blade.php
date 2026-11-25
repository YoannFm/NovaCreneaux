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
    .slot { margin-bottom: 14pt; page-break-inside: avoid; }
    .slot-page { page-break-before: always; }
    h2 { font-size: 12pt; margin: 0 0 4pt; padding-bottom: 3pt; border-bottom: 1.5pt solid #1f2933; }
    h2 .count { float: right; font-size: 9.5pt; font-weight: normal; color: #4b5563; }
    .slot thead th { border-bottom-width: .75pt; }
    .box { display: inline-block; width: 9pt; height: 9pt; border: .75pt solid #1f2933; }
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

@php
    $groupLabel = \App\Support\Customization::get('group_label');
    $hours = fn ($slot) => substr($slot->start_time, 0, 5) . ' - ' . substr($slot->end_time, 0, 5);
    $byResource = $reservations->groupBy(fn ($r) => $r->slot->resource->name);
@endphp

@if($reservations->isEmpty())
    <p class="empty">Aucune réservation pour cette date.</p>
@elseif($layout === 'list')
    <table>
        <thead>
            <tr>
                <th style="width:17%;">Horaire</th>
                <th style="width:22%;">Créneau</th>
                <th>Nom</th>
                <th style="width:15%;">{{ $groupLabel }}</th>
                <th style="width:14%;">Réservé le</th>
            </tr>
        </thead>
        <tbody>
        @foreach($byResource as $resourceName => $rows)
            <tr class="group"><td colspan="5">{{ $resourceName }} ({{ $rows->count() }})</td></tr>
            @foreach($rows as $r)
                <tr>
                    <td class="nowrap">{{ $hours($r->slot) }}</td>
                    <td>{{ $r->slot->name }}</td>
                    <td>{{ $r->user->name }}</td>
                    <td>{{ $r->user->classe ?? '-' }}</td>
                    <td class="muted nowrap">{{ $r->created_at->format('d/m H:i') }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
@else
    {{-- Un bloc par creneau ; "pages" : chaque creneau commence une page, avec une case de presence --}}
    @foreach($byResource->flatMap(fn ($rows) => $rows->groupBy('slot_id')->values()) as $rows)
        @php($slot = $rows->first()->slot)
        <div class="slot {{ $layout === 'pages' && !$loop->first ? 'slot-page' : '' }}">
            <h2>
                <span class="count">{{ $rows->count() }} / {{ $slot->max_capacity }} places</span>
                {{ $slot->resource->name }} - {{ $hours($slot) }} - {{ $slot->name }}
            </h2>
            @if($layout === 'pages')<div class="muted" style="margin-bottom:6pt;">{{ ucfirst($date->translatedFormat('l j F Y')) }}</div>@endif
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th style="width:22%;">{{ $groupLabel }}</th>
                        <th style="width:16%;">Réservé le</th>
                        @if($layout === 'pages')<th style="width:11%;">Présent</th>@endif
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $r)
                    <tr>
                        <td>{{ $r->user->name }}</td>
                        <td>{{ $r->user->classe ?? '-' }}</td>
                        <td class="muted nowrap">{{ $r->created_at->format('d/m H:i') }}</td>
                        @if($layout === 'pages')<td><span class="box"></span></td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif

</body>
</html>
