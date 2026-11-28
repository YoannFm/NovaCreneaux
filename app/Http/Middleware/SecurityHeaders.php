<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tetes de securite de toutes les reponses web : pas d'affichage du site dans le cadre d'un autre site (clickjacking),
 * pas de devinette du type des fichiers, pas de fuite de l'adresse complete vers les autres sites, HTTPS impose aux navigateurs.
 * La politique de contenu se limite aux directives qui ne peuvent pas casser les pages (le site utilise des scripts et styles en ligne).
 */
class SecurityHeaders
{
    private const CONTENT_POLICY = "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Content-Security-Policy' => self::CONTENT_POLICY,
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=15552000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
