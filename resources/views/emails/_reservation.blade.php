{{-- Detail d'une reservation dans un e-mail. Parametre : $reservation (avec slot.resource) --}}
@php $cell = 'padding:8px 0;border-bottom:1px solid #eee;'; @endphp
<table style="width:100%;border-collapse:collapse;margin:8px 0 4px;font-size:15px;">
    <tr><td style="{{ $cell }}color:#6b7280;width:38%;">Ressource</td><td style="{{ $cell }}"><strong>{{ $reservation->slot->resource->name }}</strong></td></tr>
    <tr><td style="{{ $cell }}color:#6b7280;">Créneau</td><td style="{{ $cell }}">{{ $reservation->slot->name }}</td></tr>
    <tr><td style="padding:8px 0;color:#6b7280;">Date</td><td style="padding:8px 0;">{{ $reservation->reservation_date->isoFormat('dddd D MMMM YYYY') }}</td></tr>
</table>
