<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Attribut `style` : l'editeur de texte s'en sert pour l'alignement et les couleurs. Seules quelques proprietes de mise
 * en forme du texte sont gardees, avec des valeurs simples. Tout le reste (position, url(), expression, @import...) est retire.
 */
final class SafeStyleAttributeSanitizer implements AttributeSanitizerInterface
{
    private const PROPERTIES = [
        'color', 'background-color', 'text-align', 'font-weight', 'font-style', 'font-size', 'font-family',
        'text-decoration', 'line-height', 'margin-left', 'padding-left', 'text-indent', 'width', 'height',
        'vertical-align', 'border', 'border-collapse', 'border-color', 'border-style', 'border-width',
    ];

    /** Lettres, chiffres, espaces, #, %, point, virgule, tiret, guillemets simples et parentheses (rgb(...)) : pas de url(, pas d'antislash. */
    private const SAFE_VALUE = '/^[a-zA-Z0-9#%.,\s\'()\-]+$/';

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $kept = [];
        foreach (explode(';', $value) as $declaration) {
            [$property, $setting] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $setting = trim($setting);

            if (in_array($property, self::PROPERTIES, true) && $setting !== '' && strlen($setting) <= 100
                && preg_match(self::SAFE_VALUE, $setting) && ! preg_match('/url\s*\(|expression|javascript|var\s*\(|calc\s*\(|image/i', $setting)) {
                $kept[] = "{$property}: {$setting}";
            }
        }

        return $kept === [] ? null : implode('; ', $kept);
    }
}
