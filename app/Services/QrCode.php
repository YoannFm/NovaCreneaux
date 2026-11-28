<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR code dessine sur le serveur (SVG), sans service externe : le contenu peut etre secret
 * (cle de double authentification) et ne doit jamais quitter le site.
 */
class QrCode
{
    /** Image SVG a inserer telle quelle dans la page : uniquement des formes, aucun texte venant du contenu. */
    public function svg(string $content, int $size = 200): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString($content);

        // Sans l'en-tete XML, l'image peut etre inseree directement dans le HTML
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
