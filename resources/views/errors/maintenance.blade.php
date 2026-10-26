<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - {{ setting('app_name', 'NovaCreneaux') }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center;
               font-family: system-ui, sans-serif; background: Canvas; color: CanvasText; }
        .box { max-width: 440px; padding: 2rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 .75rem; }
        p { line-height: 1.6; opacity: .8; white-space: pre-line; }
        a { color: inherit; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Site en maintenance</h1>
        <p>{{ $message !== '' ? $message : 'Le service est temporairement indisponible.' }}</p>
        <p><a href="{{ route('login') }}">Connexion administrateur</a></p>
    </div>
</body>
</html>
