<?php

namespace App\Support;

use Illuminate\Support\Str;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;

/** Markdown (syntaxe GitHub, ajoutee par Str::markdown) venant d'une source externe (notes de version GitHub) : le HTML saisi est echappe, les liens dangereux supprimes. */
class SafeMarkdown
{
    public static function toHtml(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'external_link' => [
                'internal_hosts' => parse_url(config('app.url'), PHP_URL_HOST) ?: '',
                'open_in_new_window' => true,
                'html_class' => '',
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ], [new ExternalLinkExtension()]);
    }
}
