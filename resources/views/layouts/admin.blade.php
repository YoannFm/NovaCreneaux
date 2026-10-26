<!DOCTYPE html>
<html lang="fr" id="admin-html-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($pageTitle) ? $pageTitle . ' - Admin' : 'Administration - ' . setting('app_name','NovaCreneaux') }}</title>

    <script>
    (function () {
        // Meme cle et meme preference par defaut que layouts/app : le theme suit l'utilisateur entre site et admin
        var pref = localStorage.getItem('nc-theme') || '{{ auth()->check() && auth()->user()->theme_mode ? auth()->user()->theme_mode : 'system' }}';
        var mode = pref === 'system'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : pref;
        document.documentElement.setAttribute('data-bs-theme', mode);
        document.documentElement.setAttribute('data-theme-mode', pref);
    })();
    </script>

    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/admin.css') }}" rel="stylesheet">
    @vite(['resources/css/app.css'])

    <style>
        :root {
            --accent:       {{ auth()->check() && auth()->user()->accent_color ? auth()->user()->accent_color : (setting('theme_accent') ?: '#efb703') }};
            --accent-text:  var(--accent);
            --body-bg:      {{ setting('theme_body_bg')       ?: '#111113' }};
            --card-bg:      {{ setting('theme_card_bg')       ?: '#212227' }};
            --header-bg:    {{ setting('theme_header_bg')     ?: '#1a1b1f' }};
            --text-primary: {{ setting('theme_text_color')    ?: '#e2e8f0' }};
        }
        [data-bs-theme="light"] {
            --accent-text:  color-mix(in srgb, var(--accent) 55%, black);
            --body-bg:      {{ setting('theme_light_body_bg')    ?: '#f4f5f7' }};
            --card-bg:      {{ setting('theme_light_card_bg')    ?: '#ffffff' }};
            --header-bg:    {{ setting('theme_light_header_bg')  ?: '#ffffff' }};
            --text-primary: {{ setting('theme_light_text_color') ?: '#1a1a2e' }};
        }
        .sidebar-brand small { display:block; font-size:.72rem; opacity:.65; font-weight:400; margin-top:.1rem; }
        .sidebar-toggle { background:none; border:none; cursor:pointer; padding:.25rem .5rem; }
        @media (max-width: 991.98px) {
            .sidebar {
                position:fixed !important; top:0; left:0; bottom:0;
                width:78vw !important; max-width:320px !important; min-width:unset !important;
                z-index:100; margin-left:-78vw !important;
                transition:margin-left .3s ease; overflow-y:auto;
            }
            .sidebar.collapsed { margin-left:0 !important; }
            .main { margin-left:0 !important; width:100% !important; min-width:0 !important; }
            .navbar-bg { position:sticky; top:0; z-index:99; width:100%; }
            .content { padding:1rem .75rem !important; }
            h1.h3 { font-size:1.1rem; }
        }
        .navbar-bg .nav-link,
        .navbar-bg .nav-link.dropdown-toggle {
            color: var(--text-primary) !important;
        }
        .navbar-bg .nav-link:hover {
            color: var(--accent-text) !important;
        }
    </style>
    @stack('header-styles')
</head>
<body>
<div class="wrapper">

    {{-- Overlay mobile --}}
    <div id="sidebar-overlay"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99;"
         onclick="document.querySelector('.js-sidebar').classList.remove('collapsed');this.style.display='none';">
    </div>

    {{-- Sidebar --}}
    <nav id="sidebar" class="sidebar js-sidebar">
        <div class="sidebar-content js-simplebar">

            <a class="sidebar-brand" href="{{ route('admin.dashboard') }}">
                <span style="font-weight:800;font-size:1.05rem;">{{ setting('app_name','NovaCreneaux') }}</span>
                <small>Administration</small>
            </a>

            <ul class="sidebar-nav">

                <li class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.dashboard') }}">
                        <i class="bi bi-speedometer2"></i> <span>Tableau de bord</span>
                    </a>
                </li>

                <li class="sidebar-item {{ request()->routeIs('admin.nav.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.nav.index') }}">
                        <i class="bi bi-list-ul"></i> <span>Navigation</span>
                    </a>
                </li>

                <li class="sidebar-header">Parametres</li>

                @php $onSettings = request()->routeIs('admin.settings.*') || request()->routeIs('admin.themes.*'); @endphp
                <li class="sidebar-item {{ $onSettings ? 'active' : '' }}">
                    <a class="sidebar-link {{ $onSettings ? '' : 'collapsed' }}"
                       href="#" data-bs-toggle="collapse" data-bs-target="#collapseSettings"
                       aria-expanded="{{ $onSettings ? 'true' : 'false' }}">
                        <i class="bi bi-gear"></i> <span>Parametres</span>
                    </a>
                    <ul id="collapseSettings"
                        class="sidebar-dropdown list-unstyled collapse {{ $onSettings ? 'show' : '' }}">
                        <li class="sidebar-item {{ request()->routeIs('admin.settings.general') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('admin.settings.general') }}">General</a>
                        </li>
                        <li class="sidebar-item {{ request()->routeIs('admin.settings.home') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('admin.settings.home') }}">Accueil</a>
                        </li>
                        <li class="sidebar-item {{ request()->routeIs('admin.themes.*') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('admin.themes.index') }}">Apparence</a>
                        </li>
                        <li class="sidebar-item {{ request()->routeIs('admin.settings.auth') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('admin.settings.auth') }}">Authentification</a>
                        </li>
                        <li class="sidebar-item {{ request()->routeIs('admin.settings.security') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('admin.settings.security') }}">Securite</a>
                        </li>
                        <li class="sidebar-item {{ request()->routeIs('admin.settings.mail') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('admin.settings.mail') }}">E-mail</a>
                        </li>
                        <li class="sidebar-item {{ request()->routeIs('admin.settings.maintenance') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('admin.settings.maintenance') }}">Maintenance</a>
                        </li>
                        <li class="sidebar-item {{ request()->routeIs('admin.settings.reservations') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('admin.settings.reservations') }}">Reservations</a>
                        </li>
                    </ul>
                </li>

                <li class="sidebar-header">Gestion</li>

                <li class="sidebar-item {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.pages.index') }}">
                        <i class="bi bi-file-earmark-text"></i> <span>Pages</span>
                    </a>
                </li>

                <li class="sidebar-item {{ request()->routeIs('admin.resources.*','admin.slots.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.resources.index') }}">
                        <i class="bi bi-collection"></i> <span>Ressources</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('admin.reservations.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.reservations.index') }}">
                        <i class="bi bi-calendar-check"></i> <span>Reservations</span>
                    </a>
                </li>

                <li class="sidebar-header">Utilisateurs</li>

                <li class="sidebar-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.users.index') }}">
                        <i class="bi bi-people"></i> <span>Utilisateurs</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.roles.index') }}">
                        <i class="bi bi-shield-check"></i> <span>Roles</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('admin.sanctions.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.sanctions.index') }}">
                        <i class="bi bi-exclamation-triangle"></i> <span>Sanctions</span>
                    </a>
                </li>

                <li class="sidebar-item {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.notifications.index') }}">
                        <i class="bi bi-bell"></i> <span>Notifications</span>
                    </a>
                </li>

                <li class="sidebar-header">Systeme</li>
                <li class="sidebar-item {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.logs.index') }}">
                        <i class="bi bi-journal-text"></i> <span>Activité</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('admin.api-tokens.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.api-tokens.index') }}">
                        <i class="bi bi-plug"></i> <span>Intégrations API</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('admin.update.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('admin.update.index') }}">
                        <i class="bi bi-cloud-download"></i> <span>Mise à jour</span>
                        @if(app(\App\Services\Update\UpdateManager::class)->cachedHasUpdate())
                            <span class="badge bg-warning text-dark ms-1" title="Une nouvelle version est disponible">!</span>
                        @endif
                    </a>
                </li>

            </ul>
        </div>
    </nav>

    {{-- Main --}}
    <div class="main">

        {{-- Topbar --}}
        <nav class="navbar navbar-expand navbar-bg">
            <a class="sidebar-toggle js-sidebar-toggle">
                <i class="hamburger align-self-center"></i>
            </a>

            <div class="navbar-collapse collapse">
                <div class="d-flex align-items-center gap-2 ms-2">
                    <a href="https://discord.gg/invite/HtjPAfqUXu"
                       class="btn btn-outline-primary btn-sm d-none d-sm-inline-flex align-items-center gap-1"
                       target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-question-circle"></i> <span class="d-none d-md-inline">Support</span>
                    </a>
                    <a href="https://wiki.novadev.ovh"
                       class="btn btn-outline-info btn-sm d-none d-sm-inline-flex align-items-center gap-1"
                       target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-book"></i> <span class="d-none d-md-inline">Documentation</span>
                    </a>
                </div>

                <ul class="navbar-nav navbar-align">
                    <li class="nav-item">
                        <a class="nav-icon" href="#" id="admin-theme-toggle" title="Changer le theme" role="button">
                            <i class="bi bi-moon-stars small"></i>
                        </a>
                    </li>

                    <div class="topbar-divider d-none d-sm-block"></div>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="me-1" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:.375rem;background:#2978FF;color:#fff;font-weight:600;font-size:.8rem;">{{ Auth::user()->initials() }}</span>
                            <span class="me-2 d-none d-lg-inline text-gray-600 small">{{ Auth::user()->name ?? '' }}</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow">
                            <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                <i class="bi bi-person-circle me-1"></i> Mon profil
                            </a>
                            <a class="dropdown-item" href="{{ url('/') }}">
                                <i class="bi bi-house me-1"></i> Retour au site
                            </a>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-1"></i> Deconnexion
                                </button>
                            </form>
                        </div>
                    </li>
                </ul>
            </div>
        </nav>

        {{-- Content --}}
        <main class="content">
            <div class="container-fluid p-0">

                <h1 class="h3 mb-3">{{ $pageTitle ?? 'Administration' }}</h1>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')

            </div>
        </main>

        <footer class="footer">
            <div class="container-fluid text-center text-muted" style="font-size:.78rem;">
                <div>
                    &copy; Copyright {{ date('Y') }} -
                    Tous droits réservés
                    <a href="https://wiki.novadev.ovh" target="_blank" rel="noopener"
                       style="color:var(--accent-text);text-decoration:none;font-weight:600;">
                        NovaCreneaux
                    </a>
                    -
                    <button onclick="document.getElementById('admin-licence-modal').style.display='flex'"
                            style="background:none;border:none;padding:0;color:var(--text-muted);font-size:inherit;cursor:pointer;text-decoration:underline;text-underline-offset:2px;">
                        Licence
                    </button>
                </div>
                <div style="margin-top:.15rem;">
                    Fait avec ❤️ par
                    <a href="https://github.com/YoannFm" target="_blank" rel="noopener"
                       style="color:var(--accent-text);text-decoration:none;font-weight:600;">YoannFM</a>
                </div>
            </div>
        </footer>

        {{-- Modal confirmation suppression --}}
        <div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title" id="confirmDeleteLabel">Confirmer la suppression</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">Êtes-vous sûr de vouloir supprimer cet élément ? Cette action est irréversible.</div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Annuler
                        </button>
                        <form id="confirmDeleteForm" method="POST">
                            @method('DELETE')
                            @csrf
                            <button class="btn btn-danger" type="submit">
                                <i class="bi bi-exclamation-triangle"></i> Supprimer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal licence admin --}}
        <div id="admin-licence-modal"
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
                    <button onclick="document.getElementById('admin-licence-modal').style.display='none'"
                            style="background:none;border:none;font-size:1.2rem;cursor:pointer;
                                   color:var(--text-muted);line-height:1;">&times;</button>
                </div>
                <div style="padding:1.4rem;overflow-y:auto;font-family:monospace;font-size:.78rem;
                            line-height:1.7;color:var(--text-primary);white-space:pre-wrap;">@if(file_exists(base_path('LICENSE'))){{ file_get_contents(base_path('LICENSE')) }}@endif</div>
            </div>
        </div>

    </div>
</div>

<script src="{{ asset('assets/vendor/admin.js') }}" defer></script>
<script>
(function () {
    var root = document.getElementById('admin-html-root');
    var pref = root?.getAttribute('data-theme-mode') || 'system';
    function getMode() {
        if (pref === 'system') return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        return pref;
    }
    function applyTheme() {
        var t = getMode();
        root?.setAttribute('data-bs-theme', t);
        var icon = document.querySelector('#admin-theme-toggle i');
        if (icon) icon.className = t === 'dark' ? 'bi bi-sun small' : 'bi bi-moon-stars small';
    }
    applyTheme();
    document.addEventListener('DOMContentLoaded', function () {
        applyTheme();
        var btn = document.getElementById('admin-theme-toggle');
        if (btn) {
            btn.addEventListener('click', function () {
                var cur = root?.getAttribute('data-bs-theme') || 'light';
                pref = cur === 'dark' ? 'light' : 'dark';
                root?.setAttribute('data-theme-mode', pref);
                localStorage.setItem('nc-theme', pref);
                applyTheme();
            });
        }
        var sidebar = document.querySelector('.js-sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        if (sidebar && overlay) {
            new MutationObserver(function () {
                if (window.innerWidth < 992)
                    overlay.style.display = sidebar.classList.contains('collapsed') ? 'block' : 'none';
            }).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        }
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if (root?.getAttribute('data-theme-mode') === 'system') applyTheme();
        });
    });
})();
</script>
@stack('footer-scripts')
</body>
</html>
