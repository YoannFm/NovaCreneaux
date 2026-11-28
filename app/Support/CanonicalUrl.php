<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Les liens generes par le site (reinitialisation de mot de passe, verification d'e-mail, notifications) reposent sur
 * l'adresse configuree du site, jamais sur l'en-tete Host envoye par le visiteur : sans cela, une requete avec un faux
 * Host ferait envoyer par le site un e-mail dont le lien pointe vers un site pirate. Le cookie de session est reserve
 * a HTTPS quand l'adresse du site est en https.
 */
final class CanonicalUrl
{
    /** Adresses d'exemple ou de developpement : on ne force rien tant que le site n'est pas configure. */
    private const UNCONFIGURED_HOSTS = ['localhost', '127.0.0.1', '::1', 'votre-domaine.fr'];

    /** @return bool vrai si l'adresse du site a ete imposee */
    public static function apply(?string $appUrl, bool $production): bool
    {
        $host = $appUrl ? parse_url($appUrl, PHP_URL_HOST) : null;
        if (! $production || ! $host || in_array($host, self::UNCONFIGURED_HOSTS, true)) {
            return false;
        }

        URL::forceRootUrl($appUrl);
        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
            if (config('session.secure') === null) {
                config(['session.secure' => true]);
            }
        }

        return true;
    }
}
