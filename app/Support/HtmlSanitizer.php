<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Nettoie le HTML ecrit dans l'administration (pages, message d'accueil, conditions d'inscription) avant de l'afficher
 * aux visiteurs : liste blanche de balises et d'attributs, liens http, https, mailto et tel seulement, aucun script,
 * gestionnaire d'evenement, iframe, formulaire ni style dangereux. Appele a l'enregistrement ET a l'affichage :
 * un contenu enregistre avant cette protection est nettoye lui aussi.
 */
final class HtmlSanitizer
{
    private static ?SymfonySanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): SymfonySanitizer
    {
        return self::$sanitizer ??= new SymfonySanitizer(
            (new HtmlSanitizerConfig())
                ->allowSafeElements()
                // id et name permettraient de se faire passer pour un element du site (DOM clobbering)
                ->dropAttribute('id', '*')
                ->dropAttribute('name', '*')
                ->allowAttribute('class', '*')
                ->allowAttribute('style', '*')
                ->allowAttribute('target', 'a')
                ->allowAttribute('colspan', ['td', 'th'])
                ->allowAttribute('rowspan', ['td', 'th'])
                ->allowAttribute('width', 'img')
                ->allowAttribute('height', 'img')
                ->allowAttribute('loading', 'img')
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowRelativeLinks()
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeMedias()
                ->withAttributeSanitizer(new SafeStyleAttributeSanitizer())
                ->withMaxInputLength(500_000)
        );
    }
}
