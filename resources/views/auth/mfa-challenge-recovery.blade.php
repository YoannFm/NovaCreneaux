<!DOCTYPE html>
<html lang="fr" id="html-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Code de récupération - {{ setting('app_name','NovaCreneaux') }}</title>
    <script>(function(){var t=localStorage.getItem('nc-theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/admin.css') }}" rel="stylesheet">
    <style>
        :root {
            --accent:       {{ setting('theme_accent', '#efb703') }};
            --accent-hover: color-mix(in srgb, var(--accent) 82%, black);
            --accent-light: color-mix(in srgb, var(--accent) 15%, transparent);
            --body-bg:      {{ setting('theme_body_bg', '#111113') }};
            --card-bg:      {{ setting('theme_card_bg', '#212227') }};
            --text-primary: {{ setting('theme_text_color', '#e2e8f0') }};
            --text-muted:   #9ca3af;
        }
        [data-bs-theme="light"] {
            --body-bg:      {{ setting('theme_light_body_bg', '#f4f5f7') }};
            --card-bg:      {{ setting('theme_light_card_bg', '#ffffff') }};
            --text-primary: {{ setting('theme_light_text_color', '#1a1a2e') }};
            --text-muted:   #6c757d;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { min-height:100vh; background:var(--body-bg); color:var(--text-primary);
               display:flex; align-items:center; justify-content:center; padding:1rem;
               font-family:system-ui,-apple-system,sans-serif; -webkit-font-smoothing:antialiased; }
        .auth-wrap { width:100%; max-width:420px; }
        .auth-brand { text-align:center; margin-bottom:2rem; }
        .auth-brand .brand-name { font-size:2rem; font-weight:800; color:var(--accent); text-decoration:none; letter-spacing:-.5px; }
        .auth-brand .brand-sub  { font-size:.78rem; color:var(--text-muted); margin-top:.2rem; text-transform:uppercase; letter-spacing:1px; }
        .auth-card { background:var(--card-bg); border:2px solid var(--accent); border-radius:4px; padding:2.25rem 2rem; }
        .auth-card h5 { font-size:1.05rem; font-weight:700; margin-bottom:.2rem; }
        .auth-sub { font-size:.8rem; color:var(--text-muted); margin-bottom:1.75rem; }
        .form-label { font-size:.72rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.7px; margin-bottom:.35rem; display:block; }
        .form-control { background:var(--body-bg) !important; border:2px solid color-mix(in srgb,var(--accent) 40%,transparent) !important;
                        color:var(--text-primary) !important; border-radius:0 !important; padding:.65rem .9rem; font-size:.9rem; width:100%; }
        .form-control:focus { border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-light) !important; outline:none !important; }
        .form-control.is-invalid { border-color:#ef4444 !important; }
        .btn-auth { width:100%; padding:.72rem; background:var(--accent); border:none; border-radius:0;
                    color:#212227; font-weight:800; font-size:.85rem; text-transform:uppercase; letter-spacing:.8px;
                    cursor:pointer; transition:background .15s; display:flex; align-items:center; justify-content:center; gap:.5rem; }
        .btn-auth:hover { background:var(--accent-hover); }
        .auth-error { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); border-radius:3px; color:#ef4444; font-size:.82rem;
                      padding:.65rem .9rem; margin-bottom:1.25rem; display:flex; align-items:flex-start; gap:.5rem; }
        .auth-links { display:flex; justify-content:center; margin-top:1.25rem; font-size:.8rem; gap:1.5rem; }
        .auth-links a { color:var(--text-muted); text-decoration:none; }
        .auth-links a:hover { color:var(--accent); }
        .mfa-icon { width:56px; height:56px; border-radius:50%; background:color-mix(in srgb,#f59e0b 15%,transparent);
                    display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:#f59e0b; margin:0 auto 1.5rem; }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-brand">
        <a href="{{ url('/') }}" class="brand-name">{{ setting('app_name','NovaCreneaux') }}</a>
        <div class="brand-sub">{{ setting('app_description', 'Réservation de créneaux CDI') }}</div>
    </div>

    <div class="auth-card">
        <div class="mfa-icon"><i class="bi bi-key-fill"></i></div>
        <h5 class="text-center">Code de récupération</h5>
        <div class="auth-sub text-center">
            Entrez l'un de vos codes de récupération à usage unique.<br>
            Ce code sera invalidé après utilisation.
        </div>

        @if($errors->any())
        <div class="auth-error">
            <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
            <span>{{ $errors->first() }}</span>
        </div>
        @endif

        <form method="POST" action="{{ route('mfa.challenge.recovery') }}">
            @csrf
            <div class="mb-4">
                <label for="code" class="form-label">Code de récupération</label>
                <input id="code" type="text" name="code"
                       class="form-control font-monospace @error('code') is-invalid @enderror"
                       placeholder="xxxxx-xxxxx"
                       autocomplete="off" autofocus maxlength="20"
                       style="letter-spacing:.1em; text-align:center;">
            </div>
            <button type="submit" class="btn-auth">
                <i class="bi bi-unlock"></i> Utiliser ce code
            </button>
        </form>

        <div class="auth-links">
            <a href="{{ route('mfa.challenge.form') }}"><i class="bi bi-arrow-left me-1"></i>Retour</a>
            <a href="{{ route('login') }}">Se reconnecter</a>
        </div>
    </div>
</div>
</body>
</html>
