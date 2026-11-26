{{-- Pages d'erreur : autonomes (styles en ligne, aucun fichier compile) et prudentes. Une page 500 peut venir d'une
     base indisponible : les reglages sont lus avec rescue() et retombent sur les valeurs par defaut. --}}
@php
    $appName = rescue(fn () => setting('app_name', 'NovaCreneaux'), 'NovaCreneaux', false) ?: 'NovaCreneaux';
    $accent = rescue(fn () => setting('theme_accent', '#efb703'), '#efb703', false);
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $accent) ? $accent : '#efb703';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title') - {{ $appName }}</title>
    <style>
        :root { color-scheme: light dark; --accent: {{ $accent }}; }
        body { min-height: 100vh; margin: 0; padding: 1rem; box-sizing: border-box; display: flex; align-items: center; justify-content: center;
               font-family: system-ui, -apple-system, sans-serif; background: Canvas; color: CanvasText; }
        .box { max-width: 460px; text-align: center; }
        /* Texte a la couleur d'accent : assombri sur fond clair (l'or par defaut y serait peu lisible) */
        :root { --accent-text: var(--accent); }
        @media (prefers-color-scheme: light) { :root { --accent-text: color-mix(in srgb, var(--accent) 65%, black); } }
        .brand { font-weight: 800; font-size: 1.4rem; color: var(--accent-text); text-decoration: none; }
        .code { font-size: 4rem; font-weight: 800; line-height: 1; margin: 1.5rem 0 .5rem; color: var(--accent-text); }
        h1 { font-size: 1.35rem; margin: 0 0 .75rem; }
        p { line-height: 1.6; opacity: .8; white-space: pre-line; margin: 0 0 1rem; }
        .actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; margin-top: 1.5rem; }
        .actions a { padding: .6rem 1.1rem; border: 2px solid var(--accent); color: inherit; text-decoration: none; font-weight: 600; font-size: .9rem; }
        .actions a.primary { background: var(--accent); color: #212227; }
    </style>
</head>
<body>
    <main class="box">
        <a class="brand" href="{{ url('/') }}">{{ $appName }}</a>
        @hasSection('code')<div class="code">@yield('code')</div>@endif
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @section('actions')
                <a class="primary" href="{{ url('/') }}">Retour à l'accueil</a>
            @show
        </div>
    </main>
</body>
</html>
