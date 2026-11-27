<?php

namespace App\Http\Controllers;

use App\Support\SiteLogo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Sert le logo du site depuis storage. L'adresse change avec le logo (?v=...) : le navigateur peut le garder un an. */
class SiteLogoController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = SiteLogo::path();
        abort_if($path === null, 404);

        return response()->file($path, ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
