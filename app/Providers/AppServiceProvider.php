<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use App\Rules\TrustedProxyList;
use App\Support\CanonicalUrl;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // API : filet par IP avant l'authentification, puis quota par jeton
        RateLimiter::for('api-ip', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(30)->by('w' . ($request->user()?->currentAccessToken()?->id ?: $request->ip())));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->currentAccessToken()?->id ?: $request->ip()));

        Event::listen([Login::class, Logout::class, Registered::class],
            \App\Listeners\LogAuthListener::class);

        try {
            // Fuseau horaire configuré dans Paramètres > Général
            if ($tz = setting('timezone', '')) {
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
