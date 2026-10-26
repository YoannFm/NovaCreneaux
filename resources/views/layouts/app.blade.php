<!DOCTYPE html>
<html lang="fr" id="html-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($pageTitle) ? $pageTitle . ' - ' . setting('app_name','NovaCreneaux') : setting('app_name','NovaCreneaux') }}</title>

    <script>
    (function () {
        // localStorage en priorité - si absent, fallback sur la préférence DB ou système
        var stored = localStorage.getItem('nc-theme');
        var pref   = stored || '{{ auth()->check() && auth()->user()->theme_mode ? auth()->user()->theme_mode : 'system' }}';
        var mode   = pref === 'system'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : pref;
        document.documentElement.setAttribute('data-bs-theme', mode);
        document.documentElement.setAttribute('data-theme-mode', pref);
    })();
    </script>

    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/admin.css') }}" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --accent:        {{ auth()->check() && auth()->user()->accent_color ? auth()->user()->accent_color : (setting('theme_accent') ?: '#efb703') }};
            --accent-hover:  color-mix(in srgb, var(--accent) 85%, black);
            --accent-light:  color-mix(in srgb, var(--accent) 15%, transparent);
            --accent-text:   var(--accent);
            --body-bg:       {{ setting('theme_body_bg')       ?: '#111113' }};
            --card-bg:       {{ setting('theme_card_bg')       ?: '#212227' }};
            --card-border:   {{ setting('theme_card_bg')       ?: '#2e2e34' }};
            --header-bg:     {{ setting('theme_header_bg')     ?: '#1a1b1f' }};
            --text-primary:  {{ setting('theme_text_color')    ?: '#e2e8f0' }};
            --text-muted:    #9ca3af;
        }
        [data-bs-theme="light"] {
            --accent-text:   color-mix(in srgb, var(--accent) 55%, black);
            --body-bg:       {{ setting('theme_light_body_bg')    ?: '#f4f5f7' }};
            --card-bg:       {{ setting('theme_light_card_bg')    ?: '#ffffff' }};
            --card-border:   {{ setting('theme_light_card_bg')    ?: '#e9ecef' }};
            --header-bg:     {{ setting('theme_light_header_bg')  ?: '#ffffff' }};
            --text-primary:  {{ setting('theme_light_text_color') ?: '#1a1a2e' }};
            --text-muted:    #6c757d;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--body-bg);
            color: var(--text-primary);
        }
        main { flex: 1; }

        /* ── Header ── */
        #main-header {
            background: var(--header-bg);
            border-bottom: 1px solid rgba(255,255,255,.07);
            position: sticky;
            top: 0;
            z-index: 100;
            height: 56px;
        }
        [data-bs-theme="light"] #main-header { border-bottom-color: rgba(0,0,0,.08); }

        #main-header .navbar {
            height: 56px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            padding: 0 1rem;
        }

        #main-header .navbar-brand {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--accent-text) !important;
            text-decoration: none;
            letter-spacing: .02em;
            flex-shrink: 0;
        }

        /* Nav links desktop */
        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: .35rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 0 .9rem;
            height: 56px;
            border-top: 2px solid transparent;
            border-bottom: 2px solid transparent;
            transition: color .2s, border-color .2s;
            white-space: nowrap;
        }
        .nav-link-custom:hover { color: var(--text-primary); border-top-color: color-mix(in srgb, var(--accent) 40%, transparent); }
        .nav-link-custom.active { color: var(--accent-text); border-top-color: var(--accent); }
        .nav-link-custom i { font-size: .85rem; }

        /* Separateur vertical */
        .nav-sep { width: 1px; height: 22px; background: rgba(255,255,255,.12); margin: 0 .25rem; }
        [data-bs-theme="light"] .nav-sep { background: rgba(0,0,0,.1); }

        /* Avatar */
        .user-avatar {
            width: 30px; height: 30px; border-radius: 50%;
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .75rem;
            color: #212227;
            flex-shrink: 0;
        }

        /* Dropdown */
        .dropdown-menu-custom {
            background: var(--card-bg);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 4px;
            padding: .25rem 0;
            min-width: 190px;
        }
        [data-bs-theme="light"] .dropdown-menu-custom { border-color: rgba(0,0,0,.1); }
        .dropdown-menu-custom .dropdown-item {
            color: var(--text-muted);
            font-size: .8rem;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: .5rem 1rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .dropdown-menu-custom .dropdown-item:hover { background: var(--accent-light); color: var(--text-primary); }
        .dropdown-menu-custom .dropdown-item.text-danger { color: #ef4444 !important; }
        .dropdown-menu-custom .dropdown-item.text-danger:hover { color: #ef4444 !important; }
        .dropdown-menu-custom .dropdown-divider { border-color: rgba(255,255,255,.08); margin: .2rem 0; }
        [data-bs-theme="light"] .dropdown-menu-custom .dropdown-divider { border-color: rgba(0,0,0,.08); }
        .dropdown-menu-custom .dropdown-header { font-size: .75rem; color: var(--text-muted); padding: .4rem 1rem .2rem; opacity: .7; }

        /* Cards */
        .card { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: rgba(255,255,255,.06) !important; }
        [data-bs-theme="light"] .card { border-color: rgba(0,0,0,.08) !important; }
        .card-header { background: transparent !important; border-bottom-color: rgba(255,255,255,.08) !important; color: var(--text-primary) !important; }
        [data-bs-theme="light"] .card-header { border-bottom-color: rgba(0,0,0,.08) !important; }

        /* Boutons accent */
        .btn-primary { background-color: var(--accent) !important; border-color: var(--accent) !important; color: #212227 !important; }
        .btn-primary:hover, .btn-primary:focus { background-color: var(--accent-hover) !important; border-color: var(--accent-hover) !important; }
        .btn-outline-primary { color: var(--accent-text) !important; border-color: var(--accent) !important; }
        .btn-outline-primary:hover { background-color: var(--accent) !important; color: #212227 !important; }
        .btn-check:checked + label { border-color: var(--accent) !important; color: var(--accent-text) !important; }

        /* ── Menu mobile plein ecran ── */
        #mobile-menu {
            position: fixed; inset: 0;
            background: var(--header-bg);
            z-index: 1000;
            display: flex; flex-direction: column;
            padding: 1.5rem;
            overflow-y: auto;
            transform: translateX(-100%);
            transition: transform .3s cubic-bezier(.4,0,.2,1), visibility .3s;
            visibility: hidden;
            pointer-events: none;
        }
        #mobile-menu.open { transform: translateX(0); visibility: visible; pointer-events: all; }
        #mobile-menu-close { background: none; border: none; color: var(--accent-text); font-size: 1.75rem; align-self: flex-end; cursor: pointer; padding: 0; line-height: 1; }
        #mobile-menu .mobile-nav-link {
            display: flex; align-items: center; gap: .75rem;
            color: var(--text-primary); text-decoration: none;
            font-size: 1rem; font-weight: 700;
            letter-spacing: .5px; text-transform: uppercase;
            padding: 1rem 0;
        }
        #mobile-menu .mobile-nav-link.active { color: var(--accent-text); border-left: 3px solid var(--accent); margin-left: -1.5rem; padding-left: calc(1.5rem - 3px); }
        #mobile-menu .mobile-nav-link i { font-size: 1rem; width: 22px; text-align: center; }
        #mobile-menu hr { border-color: rgba(255,255,255,.1); margin: .75rem 0; }
        [data-bs-theme="light"] #mobile-menu hr { border-color: rgba(0,0,0,.1); }
        #mobile-menu .mobile-user-link {
            display: flex; align-items: center; gap: .75rem;
            color: var(--text-muted); text-decoration: none;
            font-size: .875rem; font-weight: 600;
            letter-spacing: .5px; text-transform: uppercase;
            padding: .6rem 0;
            background: none; border: none; width: 100%; text-align: left; cursor: pointer;
        }
        #mobile-menu .mobile-user-link:hover { color: var(--text-primary); }
        #mobile-menu .mobile-user-link.danger { color: #ef4444; }

        /* Navbar toggler hamburger */
        .hamburger-btn {
            background: none; border: none; cursor: pointer;
            display: flex; flex-direction: column; gap: 5px; padding: .4rem .6rem;
        }
        .hamburger-btn .hb { display: block; width: 22px; height: 2px; background: var(--accent); border-radius: 1px; transition: transform .3s, opacity .3s; transform-origin: center; }

        /* Footer */
        #main-footer {
            border-top: 1px solid rgba(255,255,255,.06);
            padding: 1.25rem 1rem;
            text-align: center;
            font-size: .78rem;
            color: var(--text-muted);
            background: var(--header-bg);
        }
        [data-bs-theme="light"] #main-footer { border-top-color: rgba(0,0,0,.08); }

        @media (max-width: 991.98px) {
            .nav-sep { display: none; }
        }
    </style>
    @stack('header-styles')
</head>
<body>

{{-- ── Menu mobile ── --}}
<div id="mobile-menu" role="dialog" aria-modal="true">
    <button id="mobile-menu-close" aria-label="Fermer">&#x2715;</button>

    @auth
    <nav style="margin-top:1.5rem;">
        @foreach(\App\Models\NavItem::where('is_active', true)->orderBy('position')->get() as $navItem)
            @if(!$navItem->admin_only || auth()->user()->isAdmin())
            @php
                $resolved = $navItem->resolvedUrl();
                $isActive = match($navItem->type) {
                    'home'            => request()->routeIs('home'),
                    'reservations'    => request()->routeIs('reservations.index'),
                    'my_reservations' => request()->routeIs('reservations.my'),
                    'profile'         => request()->routeIs('profile.edit'),
                    default           => url()->current() === $resolved,
                };
            @endphp
            <a href="{{ $resolved }}"
               class="mobile-nav-link {{ $isActive ? 'active' : '' }}"
               target="{{ $navItem->target }}"
               @if($navItem->target === '_blank') rel="noopener noreferrer" @endif>
                <i class="bi {{ $navItem->icon }}"></i> {{ $navItem->label }}
            </a>
            @endif
        @endforeach
    </nav>
    <hr>
    <div style="display:flex;flex-direction:column;">
        <div style="display:flex;align-items:center;gap:.75rem;padding:.75rem 0;">
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <span style="font-weight:700;color:var(--text-primary);font-size:.95rem;text-transform:uppercase;letter-spacing:.5px;">{{ auth()->user()->name }}</span>
        </div>
        <a href="{{ route('profile.edit') }}" class="mobile-user-link"><i class="bi bi-person-circle"></i> Mon profil</a>
        @if(auth()->user()->isAdmin())
        <a href="{{ route('admin.dashboard') }}" class="mobile-user-link" style="color:var(--accent-text);"><i class="bi bi-shield-check"></i> Panel administrateur</a>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mobile-user-link danger"><i class="bi bi-box-arrow-right"></i> Deconnexion</button>
        </form>
    </div>
    @else
    <nav style="margin-top:1.5rem;">
        <a href="{{ route('login') }}" class="mobile-nav-link"><i class="bi bi-box-arrow-in-right"></i> Connexion</a>
    </nav>
    @endauth
</div>

{{-- ── Header ── --}}
<header id="main-header">
    <nav class="navbar navbar-expand-lg px-3 py-0 h-100">

        <a href="{{ route('home') }}" class="navbar-brand me-3">
            @if(setting('logo_path'))
                <img src="{{ asset(setting('logo_path')) }}" alt="" style="height:22px;width:auto;object-fit:contain;margin-right:.4rem;vertical-align:middle;">
            @endif
            {{ setting('app_name','NovaCreneaux') }}
        </a>

        {{-- Hamburger mobile --}}
        <button class="hamburger-btn d-lg-none ms-auto" id="mobile-menu-open" aria-label="Ouvrir le menu">
            <span class="hb"></span><span class="hb"></span><span class="hb"></span>
        </button>

        {{-- Nav desktop --}}
        <div class="d-none d-lg-flex align-items-stretch flex-grow-1">

            @auth
            <div class="d-flex align-items-stretch">
                @foreach(\App\Models\NavItem::where('is_active', true)->orderBy('position')->get() as $navItem)
                    @if(!$navItem->admin_only || auth()->user()->isAdmin())
                    @php
                        $resolved = $navItem->resolvedUrl();
                        $isActive = match($navItem->type) {
                            'home'            => request()->routeIs('home'),
                            'reservations'    => request()->routeIs('reservations.index'),
                            'my_reservations' => request()->routeIs('reservations.my'),
                            'profile'         => request()->routeIs('profile.edit'),
                            default           => url()->current() === $resolved,
                        };
                    @endphp
                    <a href="{{ $resolved }}"
                       class="nav-link-custom {{ $isActive ? 'active' : '' }}"
                       target="{{ $navItem->target }}"
                       @if($navItem->target === '_blank') rel="noopener noreferrer" @endif>
                        <i class="bi {{ $navItem->icon }}"></i> {{ $navItem->label }}
                    </a>
                    @endif
                @endforeach
            </div>
            @endauth

            <div class="ms-auto d-flex align-items-center gap-1">
                @auth
                {{-- Toggle theme --}}
                <button id="theme-toggle"
                        style="background:none;border:none;color:var(--text-muted);font-size:1.05rem;cursor:pointer;padding:0 .6rem;height:56px;"
                        title="Changer le theme">
                    <i class="bi bi-sun-fill" id="theme-icon"></i>
                </button>

                {{-- Dropdown utilisateur --}}
                <div class="dropdown">
                    <button class="d-flex align-items-center gap-2 px-2"
                            style="background:none;border:none;cursor:pointer;height:56px;"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                        <span class="d-none d-xl-inline" style="color:var(--text-primary);font-size:.85rem;font-weight:600;">{{ auth()->user()->name }}</span>
                        <i class="bi bi-chevron-down" style="color:var(--text-muted);font-size:.7rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom">
                        <li class="dropdown-header">{{ auth()->user()->email }}</li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="bi bi-person"></i> Mon profil
                        </a></li>
                        @if(auth()->user()->isAdmin())
                        <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}" style="color:var(--accent-text) !important;">
                            <i class="bi bi-shield-check"></i> Panel administrateur
                        </a></li>
                        @endif
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right"></i> Deconnexion
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
                @else
                <a class="nav-link-custom" href="{{ route('login') }}">
                    <i class="bi bi-box-arrow-in-right"></i> Connexion
                </a>
                @endauth
            </div>
        </div>

    </nav>
</header>

@if(setting('maintenance_mode','0') === '1')
<div class="alert alert-warning text-center mb-0 rounded-0 border-0 py-2" style="font-size:.875rem;">
    <i class="bi bi-cone-striped"></i> {{ setting('maintenance_message','Le site est en maintenance.') }}
</div>
@endif

<main class="py-4">
    <div class="container" style="max-width:1200px;">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible">
                <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible">
                <i class="bi bi-exclamation-circle me-1"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @yield('content')
    </div>
</main>

<footer id="main-footer">
    <div>
        &copy; Copyright {{ date('Y') }} -
        Tous droits réservés
        <a href="https://wiki.novadev.ovh"
           target="_blank" rel="noopener"
           style="color:var(--accent-text);text-decoration:none;font-weight:600;">
            NovaCreneaux
        </a>
        -
        <button onclick="document.getElementById('licence-modal').style.display='flex'"
                style="background:none;border:none;padding:0;color:var(--text-muted);font-size:inherit;cursor:pointer;text-decoration:underline;text-underline-offset:2px;">
            Licence
        </button>
    </div>
    <div style="margin-top:.2rem;">
        Fait avec ❤️ par
        <a href="https://github.com/YoannFm" target="_blank" rel="noopener"
           style="color:var(--accent-text);text-decoration:none;font-weight:600;">YoannFM</a>
    </div>
</footer>

{{-- Modal licence --}}
<div id="licence-modal"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.65);
            align-items:center;justify-content:center;padding:1rem;"
     onclick="if(event.target===this)this.style.display='none'">
    <div style="background:var(--card-bg);border:1px solid rgba(255,255,255,.1);border-radius:10px;
                max-width:580px;width:100%;max-height:85vh;display:flex;flex-direction:column;
                box-shadow:0 20px 60px rgba(0,0,0,.5);">
        <div style="padding:1.1rem 1.4rem;border-bottom:1px solid rgba(255,255,255,.07);
                    display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <span style="font-weight:700;font-size:1rem;color:var(--text-primary);">
                <i class="bi bi-file-earmark-text me-2" style="color:var(--accent-text);"></i>Licence
            </span>
            <button onclick="document.getElementById('licence-modal').style.display='none'"
                    style="background:none;border:none;font-size:1.2rem;cursor:pointer;
                           color:var(--text-muted);line-height:1;">&times;</button>
        </div>
        <div style="padding:1.4rem;overflow-y:auto;font-family:monospace;font-size:.78rem;
                    line-height:1.7;color:var(--text-primary);white-space:pre-wrap;">NovaCreneaux - Licence Propriétaire
Copyright (c) {{ date('Y') }} LE BORGNE Yoann. Tous droits réservés.

Ce logiciel, son code source et la documentation associée (le « Logiciel ») sont la
propriété exclusive de LE BORGNE Yoann. Toute reproduction, redistribution, modification
ou exploitation commerciale du Logiciel est strictement interdite sans autorisation écrite
préalable de l'auteur.

LIMITATION DE RESPONSABILITÉ

LE LOGICIEL EST FOURNI « EN L'ÉTAT », SANS GARANTIE D'AUCUNE SORTE, EXPRESSE OU
IMPLICITE. EN AUCUN CAS L'AUTEUR NE SAURAIT ÊTRE TENU RESPONSABLE DE TOUT DOMMAGE
DIRECT, INDIRECT, ACCESSOIRE OU CONSÉCUTIF RÉSULTANT DE L'UTILISATION OU DE
L'IMPOSSIBILITÉ D'UTILISER CE LOGICIEL.</div>
    </div>
</div>

<script src="{{ asset('assets/vendor/admin.js') }}" defer></script>
<script>
(function () {
    var root = document.getElementById('html-root');
    var pref = root?.getAttribute('data-theme-mode') || 'system';
    var icon = document.getElementById('theme-icon');

    function getMode() {
        return pref === 'system'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : pref;
    }
    function applyTheme() {
        var t = getMode();
        root?.setAttribute('data-bs-theme', t);
        if (icon) icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
    applyTheme();

    document.getElementById('theme-toggle')?.addEventListener('click', function () {
        pref = getMode() === 'dark' ? 'light' : 'dark';
        root?.setAttribute('data-theme-mode', pref);
        localStorage.setItem('nc-theme', pref);
        applyTheme();
    });

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if (root?.getAttribute('data-theme-mode') === 'system') applyTheme();
    });

    // Menu mobile
    var mobileMenu = document.getElementById('mobile-menu');
    document.getElementById('mobile-menu-open')?.addEventListener('click', function () {
        mobileMenu?.classList.add('open');
        document.body.style.overflow = 'hidden';
    });
    document.getElementById('mobile-menu-close')?.addEventListener('click', function () {
        mobileMenu?.classList.remove('open');
        document.body.style.overflow = '';
    });
})();
</script>
@stack('footer-scripts')
</body>
</html>
