<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f5f5f5;padding:20px">
<div style="max-width:500px;margin:0 auto;background:#fff;border-radius:8px;padding:24px">
    <h2 style="color:#1f2933">Nouveau mot de passe</h2>
    <p>Bonjour <strong>{{ $name }}</strong>,</p>
    <p>Une demande de réinitialisation du mot de passe de votre compte {{ setting('app_name', 'NovaCreneaux') }} a été faite. Cliquez sur le bouton pour en choisir un nouveau :</p>
    <p style="text-align:center;margin:28px 0">
        <a href="{{ $url }}" style="background:{{ \App\Support\ThemeSettings::value(\App\Support\ThemeSettings::ACCENT) }};color:#212227;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:4px;display:inline-block">Choisir un nouveau mot de passe</a>
    </p>
    <p style="color:#555;font-size:14px">Ce lien est valable {{ $expiresInMinutes }} minutes et ne sert qu'une fois.</p>
    <p style="color:#555;font-size:14px">Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable.</p>
    <p style="color:#888;font-size:12px;word-break:break-all;margin-top:24px">Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur :<br>{{ $url }}</p>
</div></body></html>
