<!DOCTYPE html>
<html lang="fr" id="html-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Connexion - {{ setting('app_name','NovaCreneaux') }}</title>

    <script>
    (function () {
        var t = localStorage.getItem('nc-theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', t);
    })();
    </script>

    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/admin.css') }}" rel="stylesheet">

    <style>
        :root {
            --accent:       {{ setting('theme_accent', '#efb703') }};
            --accent-hover: color-mix(in srgb, var(--accent) 82%, black);
            --accent-light: color-mix(in srgb, var(--accent) 15%, transparent);
            --body-bg:      {{ setting('theme_body_bg',    '#111113') }};
            --card-bg:      {{ setting('theme_card_bg',    '#212227') }};
            --text-primary: {{ setting('theme_text_color', '#e2e8f0') }};
            --text-muted:   #9ca3af;
        }
        [data-bs-theme="light"] {
            --body-bg:      {{ setting('theme_light_body_bg',    '#f4f5f7') }};
            --card-bg:      {{ setting('theme_light_card_bg',    '#ffffff') }};
            --text-primary: {{ setting('theme_light_text_color', '#1a1a2e') }};
            --text-muted:   #6c757d;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            min-height: 100vh;
            background: var(--body-bg);
            color: var(--text-primary);
            display: flex; align-items: center; justify-content: center;
            padding: 1rem;
            font-family: system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        ::selection { background: var(--accent); color: #212227; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 3px; }

        .auth-wrap { width: 100%; max-width: 420px; }

        .auth-brand { text-align: center; margin-bottom: 2rem; }
        .auth-brand .brand-name {
            font-size: 2rem; font-weight: 800;
            color: var(--accent); text-decoration: none; letter-spacing: -.5px;
        }
        .auth-brand .brand-sub {
            font-size: .78rem; color: var(--text-muted); margin-top: .2rem;
            text-transform: uppercase; letter-spacing: 1px;
        }

        .auth-card {
            background: var(--card-bg);
            border: 2px solid var(--accent);
            border-radius: 4px;
            padding: 2.25rem 2rem;
        }
        .auth-card h5 { font-size: 1.05rem; font-weight: 700; margin-bottom: .2rem; }
        .auth-sub { font-size: .8rem; color: var(--text-muted); margin-bottom: 1.75rem; }

        .form-label {
            font-size: .72rem; font-weight: 700; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: .7px; margin-bottom: .35rem;
            display: block;
        }
        .form-control {
            background: var(--body-bg) !important;
            border: 2px solid color-mix(in srgb, var(--accent) 40%, transparent) !important;
            color: var(--text-primary) !important;
            border-radius: 0 !important;
            padding: .65rem .9rem;
            font-size: .9rem;
            width: 100%;
            transition: border-color .15s !important;
        }
        .form-control:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 0 3px var(--accent-light) !important;
            outline: none !important;
        }
        .form-control::placeholder { color: var(--text-muted); opacity: .55; }
        .form-control.is-invalid { border-color: #ef4444 !important; }

        .form-check-input {
            background-color: var(--body-bg);
            border: 2px solid color-mix(in srgb, var(--accent) 40%, transparent);
            border-radius: 0 !important; width: 1rem; height: 1rem; cursor: pointer;
        }
        .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }
        .form-check-label { font-size: .82rem; color: var(--text-muted); cursor: pointer; }

        .btn-auth {
            width: 100%; padding: .72rem; margin-top: .25rem;
            background: var(--accent); border: none; border-radius: 0;
            color: #212227; font-weight: 800; font-size: .85rem;
            text-transform: uppercase; letter-spacing: .8px;
            cursor: pointer; transition: background .15s;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
        }
        .btn-auth:hover { background: var(--accent-hover); }

        .auth-links { display: flex; justify-content: space-between; margin-top: 1.25rem; font-size: .8rem; }
        .auth-links a { color: var(--text-muted); text-decoration: none; }
        .auth-links a:hover { color: var(--accent); }
        .auth-links .main-link { color: var(--accent); font-weight: 600; }

        .auth-error {
            background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3);
            border-radius: 3px; color: #ef4444; font-size: .82rem;
            padding: .65rem .9rem; margin-bottom: 1.25rem;
            display: flex; align-items: flex-start; gap: .5rem;
        }
        .auth-success {
            background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.3);
            border-radius: 3px; color: #22c55e; font-size: .82rem;
            padding: .65rem .9rem; margin-bottom: 1.25rem;
            display: flex; align-items: center; gap: .5rem;
        }

        .theme-btn {
            position: fixed; top: 1rem; right: 1rem;
            background: var(--card-bg);
            border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);
            border-radius: 50%; width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-muted); font-size: .9rem;
            transition: color .15s, border-color .15s;
        }
        .theme-btn:hover { color: var(--accent); border-color: var(--accent); }
    </style>
</head>
<body>

<button class="theme-btn" id="theme-toggle" title="Changer le theme">
    <i class="bi bi-sun-fill" id="theme-icon"></i>
</button>

<div class="auth-wrap">

    <div class="auth-brand">
        <a href="{{ url('/') }}" class="brand-name">{{ setting('app_name','NovaCreneaux') }}</a>
        <div class="brand-sub">{{ setting('app_description', 'Reservation de creneaux CDI') }}</div>
    </div>

    <div class="auth-card">
        <h5>Connexion</h5>
        <div class="auth-sub">Entrez vos identifiants pour acceder a votre espace.</div>

        @if(session('status'))
        <div class="auth-success">
            <i class="bi bi-check-circle-fill"></i> {{ session('status') }}
        </div>
        @endif

        @if($errors->any())
        <div class="auth-error">
            <i class="bi bi-exclamation-circle-fill flex-shrink-0" style="margin-top:.1rem;"></i>
            <span>{{ $errors->first() }}</span>
        </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Adresse email</label>
                <input id="email" type="email" name="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}"
                       required autofocus autocomplete="username"
                       placeholder="prenom@rascol.net">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Mot de passe</label>
                <input id="password" type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="current-password"
                       placeholder="••••••••">
            </div>

            <div class="form-check mb-4">
                <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                <label for="remember_me" class="form-check-label">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn-auth">
                <i class="bi bi-box-arrow-in-right"></i> Se connecter
            </button>
        </form>

        <div class="auth-links">
            @if(Route::has('password.request'))
                <a href="{{ route('password.request') }}">Mot de passe oublie ?</a>
            @else
                <span></span>
            @endif
            @if(setting('registration_open','0') === '1')
                <a href="{{ route('register') }}" class="main-link">Creer un compte &rarr;</a>
            @endif
        </div>
    </div>

</div>

<script src="{{ asset('assets/vendor/admin.js') }}" defer></script>
<script>
(function () {
    var root = document.getElementById('html-root');
    var icon = document.getElementById('theme-icon');
    function apply() {
        var t = root.getAttribute('data-bs-theme') || 'dark';
        icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
    apply();
    document.getElementById('theme-toggle').addEventListener('click', function () {
        var cur = root.getAttribute('data-bs-theme') || 'dark';
        var next = cur === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        localStorage.setItem('nc-theme', next);
        apply();
    });
})();
</script>
</body>
</html>
