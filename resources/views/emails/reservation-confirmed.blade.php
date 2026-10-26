<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f5f5f5;padding:20px">
<div style="max-width:500px;margin:0 auto;background:#fff;border-radius:8px;padding:24px">
    <h2 style="color:#2978FF">✅ Réservation confirmée</h2>
    <p>Bonjour <strong>{{ $reservation->user->name }}</strong>,</p>
    <p>Votre réservation est confirmée :</p>
    <table style="width:100%;border-collapse:collapse">
        <tr><td style="padding:8px;border-bottom:1px solid #eee"><strong>Ressource</strong></td><td style="padding:8px;border-bottom:1px solid #eee">{{ $reservation->slot->resource->name }}</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #eee"><strong>Créneau</strong></td><td style="padding:8px;border-bottom:1px solid #eee">{{ $reservation->slot->name }}</td></tr>
        <tr><td style="padding:8px"><strong>Date</strong></td><td style="padding:8px">{{ $reservation->reservation_date->isoFormat('dddd D MMMM YYYY') }}</td></tr>
    </table>
</div></body></html>
