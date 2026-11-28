<?php

namespace App\Services\Webhook;

/**
 * Remplace les variables `{{ nom }}` d'un modele de corps. Le modele est une structure (tableaux et textes) et non un
 * texte a plat : une valeur est toujours placee dans une feuille de la structure puis encodee par l'appelant, elle ne
 * peut donc pas casser le JSON ni y injecter de champ. Une feuille qui ne contient qu'une variable garde le type de
 * celle-ci (entier pour une couleur). Aucun code n'est execute.
 */
class TemplateRenderer
{
    private const PATTERN = '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/';

    /** @param array<string, string|int> $variables */
    public static function render(mixed $node, array $variables): mixed
    {
        if (is_array($node)) {
            return array_map(fn ($child) => self::render($child, $variables), $node);
        }
        if (! is_string($node)) {
            return $node;
        }
        if (preg_match('/^\{\{\s*([a-z][a-z0-9_]*)\s*\}\}$/', $node, $match)) {
            return $variables[$match[1]] ?? '';
        }

        return preg_replace_callback(self::PATTERN, fn (array $match) => (string) ($variables[$match[1]] ?? ''), $node);
    }

    /** @return list<string> noms des variables utilisees dans le modele */
    public static function variablesIn(mixed $node): array
    {
        if (is_array($node)) {
            return array_values(array_unique(array_merge([], ...array_map(fn ($child) => self::variablesIn($child), array_values($node)))));
        }
        if (! is_string($node)) {
            return [];
        }
        preg_match_all(self::PATTERN, $node, $matches);

        return array_values(array_unique($matches[1]));
    }
}
