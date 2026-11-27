<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use App\Rules\TrustedProxyList;
use App\Support\CanonicalUrl;
use App\Support\Customization;
use App\Support\RequestText;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    /**
     * Pages ouvertes aux visiteurs (connexion par l'etablissement, inscription, mot de passe oublie). Une classe entiere
     * arrive avec la meme adresse IP (celle du lycee) : une limite par IP seule bloquait le 7e ou le 11e eleve. On limite
     * donc chaque personne (session ou adresse e-mail saisie) et on garde un plafond large par IP contre les abus.
     */
    private function limitGuestForms(): void
    {
        $person = fn (string $name, Request $request) => "{$name}|" . ($request->hasSession() ? $request->session()->getId() : $request->ip());
        $email = fn (string $name, Request $request) => "{$name}|" . mb_strtolower(RequestText::get($request, 'email'));
        $ip = fn (string $name, Request $request) => "{$name}-ip|" . $request->ip();

        // Connexion par l'etablissement : un aller (redirection) et un retour par personne
        RateLimiter::for('sso', fn (Request $request) => [
            Limit::perMinute(10)->by($person('sso', $request)),
            Limit::perMinute(300)->by($ip('sso', $request)),
        ]);
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(3)->by($email('register', $request)),
            Limit::perMinute(100)->by($ip('register', $request)),
        ]);
        // Un e-mail part a l'adresse saisie : peu d'envois par adresse
        RateLimiter::for('password-reset-link', fn (Request $request) => [
            Limit::perMinute(3)->by($email('password-reset-link', $request)),
            Limit::perMinute(100)->by($ip('password-reset-link', $request)),
        ]);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // API : filet par IP avant l'authentification, puis quota par jeton
        RateLimiter::for('api-ip', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(30)->by('w' . ($request->user()?->currentAccessToken()?->id ?: $request->ip())));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->currentAccessToken()?->id ?: $request->ip()));

        $this->limitGuestForms();

        // App\Listeners\LogAuthListener (connexion, deconnexion, inscription) est decouvert par Laravel :
        // le declarer ici aussi l'executait deux fois (chaque connexion etait inscrite deux fois au journal).

        // Longueur minimale des mots de passe (Parametres > Authentification), lue au moment de la validation
        Password::defaults(fn () => Password::min(Customization::passwordMinLength()));

        try {
            // Nom du site (Parametres > General) : application de double authentification, e-mails, etc.
            if ($name = setting('app_name', '')) {
                config(['app.name' => $name]);
            }

            // Fuseau horaire configuré dans Paramètres > Général. Un fuseau inconnu (valeur ecrite a la main) est ignore :
            // applique, il faisait echouer chaque page, administration comprise, et sautait les reglages suivants
            $tz = (string) setting('timezone', '');
            if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
                config(['app.timezone' => $tz]);
                date_default_timezone_set($tz);
            }

            // Proxys de confiance (Parametres > Securite) : IP reelle des visiteurs derriere un proxy
            if ($proxies = TrustedProxyList::parse((string) setting('trusted_proxies', ''))) {
                TrustProxies::at($proxies === ['*'] ? '*' : $proxies);
            }
            // URL de base pour les liens dans les emails et redirections
            if ($url = setting('site_url', '')) {
                config(['app.url' => $url]);
            }
            CanonicalUrl::apply(config('app.url'), $this->app->environment('production') && ! $this->app->runningInConsole());

            $driver = setting('mail_driver', 'log');
            if ($driver === 'smtp') {
                config([
                    'mail.default'                 => 'smtp',
                    'mail.mailers.smtp.host'       => setting('mail_smtp_host', '127.0.0.1'),
                    'mail.mailers.smtp.port'       => (int) setting('mail_smtp_port', 587),
                    'mail.mailers.smtp.encryption' => setting('mail_smtp_encryption', 'tls'),
                    'mail.mailers.smtp.username'   => setting('mail_smtp_username'),
                    'mail.mailers.smtp.password'   => setting('mail_smtp_password'),
                ]);
            } elseif ($driver === 'sendmail') {
                config(['mail.default' => 'sendmail']);
            } else {
                config(['mail.default' => 'log']);
            }
            config([
                'mail.from.address' => setting('mail_from_address', config('mail.from.address')),
                'mail.from.name'    => setting('app_name', config('app.name')),
            ]);
        } catch (\Throwable) {}
    }
}
