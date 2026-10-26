<?php

namespace App\Services\Webhook;

use Illuminate\Container\Attributes\Singleton;

/**
 * Catalogue des prereglages de webhook : un fichier JSON par service dans `resources/webhook-presets/`. Ajouter un
 * service, c'est ajouter un fichier, sans code. `problems()` dit ce qui ne va pas dans un fichier ; un test l'applique
 * a chacun.
 *
 * Fichier : id (= nom du fichier), name, category, description, help, hosts (domaines autorises, `*.exemple.com`
 * accepte), url_template, fields[{key, label, help, secret, required, type: text|url}], method, content_type
 * (json|form|text), headers, body (structure avec {{ variables }}, ou "@payload" pour la charge utile standard), escape.
 */
#[Singleton]
class PresetCatalog
{
    public const METHODS = ['POST', 'PUT'];
    public const CONTENT_TYPES = ['json', 'form', 'text'];
    /** Identifiants reserves aux deux autres choix du formulaire. */
    private const RESERVED_IDS = ['json', 'custom'];

    /** @var array<string, array>|null */
    private ?array $presets = null;

    public function __construct(private ?string $directory = null) {}

    /** @return array<string, array> */
    public function all(): array
    {
        if ($this->presets === null) {
            $this->presets = [];
            foreach (glob(($this->directory ?? resource_path('webhook-presets')) . '/*.json') ?: [] as $file) {
                $preset = json_decode((string) file_get_contents($file), true);
                if (is_array($preset) && isset($preset['id'])) {
                    $this->presets[$preset['id']] = $preset;
                }
            }
            uasort($this->presets, fn (array $a, array $b) => [$a['category'] ?? '', $a['name'] ?? ''] <=> [$b['category'] ?? '', $b['name'] ?? '']);
        }

        return $this->presets;
    }

    public function find(?string $id): ?array
    {
        return $id === null ? null : ($this->all()[$id] ?? null);
    }

    /** @return array<string, array<string, array>> categorie => id => prereglage */
    public function byCategory(): array
    {
        $grouped = [];
        foreach ($this->all() as $id => $preset) {
            $grouped[$preset['category']][$id] = $preset;
        }

        return $grouped;
    }

    /** @return list<string> */
    public function problems(array $preset, string $expectedId): array
    {
        $problems = [];
        foreach (['id', 'name', 'category', 'description'] as $key) {
            if (! isset($preset[$key]) || ! is_string($preset[$key]) || $preset[$key] === '') {
                $problems[] = "« {$key} » manquant.";
            }
        }
        if (($preset['id'] ?? null) !== $expectedId || ! preg_match('/^[a-z][a-z0-9_]*$/', $expectedId) || in_array($expectedId, self::RESERVED_IDS, true)) {
            $problems[] = "L'identifiant doit être le nom du fichier, en minuscules (sans « json » ni « custom »).";
        }
        if (strlen($expectedId) > 40) {
            $problems[] = "L'identifiant dépasse 40 caractères.";
        }
        if (! in_array($preset['method'] ?? null, self::METHODS, true)) {
            $problems[] = 'Méthode invalide.';
        }
        if (! in_array($preset['content_type'] ?? null, self::CONTENT_TYPES, true)) {
            $problems[] = 'Type de contenu invalide.';
        }
        if (! array_key_exists('body', $preset) || ! (is_array($preset['body']) || $preset['body'] === '@payload' || is_string($preset['body']))) {
            $problems[] = 'Corps manquant.';
        }

        $fieldKeys = [];
        foreach ($preset['fields'] ?? [] as $field) {
            $key = $field['key'] ?? '';
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $key) || isset(EventMessage::VARIABLES[$key]) || $key === 'url') {
                $problems[] = "Champ « {$key} » : nom invalide ou déjà pris par une variable.";
            }
            if (($field['label'] ?? '') === '') {
                $problems[] = "Champ « {$key} » : libellé manquant.";
            }
            if (! in_array($field['type'] ?? 'text', ['text', 'url'], true)) {
                $problems[] = "Champ « {$key} » : type invalide.";
            }
            $fieldKeys[] = $key;
        }

        $used = array_merge(
            TemplateRenderer::variablesIn($preset['body'] ?? null),
            TemplateRenderer::variablesIn($preset['headers'] ?? []),
            TemplateRenderer::variablesIn($preset['url_template'] ?? ''),
        );
        foreach (array_diff($used, array_keys(EventMessage::VARIABLES), $fieldKeys) as $unknown) {
            $problems[] = "Variable inconnue : {{ {$unknown} }}.";
        }
        foreach ($preset['hosts'] ?? [] as $host) {
            if (! preg_match('/^(\*\.)?[a-z0-9.-]+\.[a-z]{2,}$/', $host)) {
                $problems[] = "Domaine invalide : {$host}.";
            }
        }
        if (isset($preset['url_template']) && ! preg_match('/^(https:\/\/|\{\{)/', $preset['url_template'])) {
            $problems[] = "L'adresse du modèle doit commencer par https:// ou par un champ de type adresse.";
        }

        return $problems;
    }

    /** Vrai si le domaine de l'adresse est dans la liste du prereglage (liste absente : tout domaine). */
    public static function allowsHost(array $preset, string $url): bool
    {
        $allowed = $preset['hosts'] ?? [];
        if ($allowed === []) {
            return true;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ($allowed as $pattern) {
            $suffix = str_starts_with($pattern, '*.') ? substr($pattern, 1) : null;
            if ($host === $pattern || ($suffix !== null && str_ends_with($host, $suffix))) {
                return true;
            }
        }

        return false;
    }
}
