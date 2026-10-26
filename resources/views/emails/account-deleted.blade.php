<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f5f5f5;padding:20px">
<div style="max-width:500px;margin:0 auto;background:#fff;border-radius:8px;padding:24px">
    <h2 style="color:#ff5956">Compte supprimé</h2>
    <p>Bonjour <strong>{{ $name }}</strong>,</p>
    @if($byAdmin)
        <p>Votre compte {{ setting('app_name', 'NovaCreneaux') }} a été supprimé par un administrateur. Vos réservations ont été supprimées avec lui.</p>
        <p>Si vous pensez qu'il s'agit d'une erreur, rapprochez-vous du CDI.</p>
    @else
        <p>Votre compte {{ setting('app_name', 'NovaCreneaux') }} a bien été supprimé, à votre demande. Vos réservations ont été supprimées avec lui.</p>
        <p>Si vous n'êtes pas à l'origine de cette suppression, prévenez le CDI sans attendre.</p>
    @endif
</div></body></html>
