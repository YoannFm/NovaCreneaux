{{--
    Theme clair ou sombre, pose avant l'affichage (pas de flash). Ordre : choix du visiteur (localStorage
    "nc-theme", partage entre site, connexion et administration), puis preference du compte, puis
    Parametres > General > Theme par defaut. "system" suit le reglage de l'appareil.
--}}
@php
    $themeFallback = auth()->user()?->theme_mode ?: \App\Support\Customization::get('default_theme');
@endphp
<script>
(function () {
    var pref = localStorage.getItem('nc-theme') || @js($themeFallback);
    var mode = pref === 'system'
        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : pref;
    document.documentElement.setAttribute('data-bs-theme', mode);
    document.documentElement.setAttribute('data-theme-mode', pref);
})();
</script>
