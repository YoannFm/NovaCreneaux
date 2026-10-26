<?php

namespace App\Plugins;

/**
 * plugin.json d'une extension : lecture et verification. Sans dependance a Laravel : les scripts de publication
 * (scripts/plugins) l'utilisent aussi pour refuser une archive mal formee avant de la signer.
 *
 * {
 *   "id": "stats", "name": "Statistiques", "description": "...", "version": "1.0.0", "api": 1, "requires_nova": "1.6.0",
 *   "authors": ["..."], "url": "https://...",
 *   "autoload": {"NovaPlugin\Stats\": "src/"}, "providers": ["NovaPlugin\Stats\StatsProvider"],
 *   "permissions": {"voir": "Voir les statistiques"},
 *   "menu": [{"label": "Statistiques", "icon": "bi-graph-up", "route": "admin.plugin.stats.index", "permission": "voir"}]
 * }
 */
final class Manifest
{
    public const FILE = 'plugin.json';
    public const ID_PATTERN = '/^[a-z][a-z0-9-]{1,38}[a-z0-9]$/';
    /** Les classes d'une extension vivent sous ce prefixe : elle ne peut pas se faire passer pour le code du site. */
    public const NAMESPACE_ROOT = 'NovaPlugin\\';
    /** Valeur de `permission` d'une entree de menu reservee aux administrateurs. */
    public const ADMIN_ONLY = 'admin';

    /** @return array{manifest: ?array, problems: list<string>} le manifeste n'est rendu que s'il est valide */
    public static function parse(string $json): array
    {
        $data = strlen($json) <= 65536 ? json_decode($json, true) : null;
        if (! is_array($data)) {
            return ['manifest' => null, 'problems' => ['plugin.json est absent, trop gros ou ne contient pas du JSON valide.']];
        }

        $problems = self::problems($data);

        return ['manifest' => $problems === [] ? self::normalized($data) : null, 'problems' => $problems];
    }

    /** @return list<string> */
    private static function problems(array $data): array
    {
        $problems = [];
        $id = $data['id'] ?? null;
        if (! is_string($id) || ! preg_match(self::ID_PATTERN, $id)) {
            $problems[] = "« id » invalide : lettres minuscules, chiffres et tirets, de 3 à 40 caractères, commençant par une lettre.";
        }
        foreach (['name' => 80, 'description' => 300] as $key => $max) {
            if (! is_string($data[$key] ?? null) || trim($data[$key]) === '' || mb_strlen($data[$key]) > $max) {
                $problems[] = "« {$key} » manquant ou trop long ({$max} caractères au plus).";
            }
        }
        if (! is_string($data['version'] ?? null) || ! preg_match('/^\d+\.\d+\.\d+$/', $data['version'])) {
            $problems[] = '« version » doit être de la forme 1.2.3.';
        }
        if (! is_int($data['api'] ?? null)) {
            $problems[] = "« api » doit être le numéro entier de l'API des extensions visée.";
        }
        if (isset($data['requires_nova']) && (! is_string($data['requires_nova']) || ! preg_match('/^\d+\.\d+\.\d+$/', $data['requires_nova']))) {
            $problems[] = '« requires_nova » doit être de la forme 1.6.0.';
        }
        if (isset($data['url']) && (! is_string($data['url']) || ! str_starts_with($data['url'], 'https://'))) {
            $problems[] = '« url » doit commencer par https://.';
        }
        if (isset($data['authors']) && (! is_array($data['authors']) || array_filter($data['authors'], fn ($a) => ! is_string($a) || mb_strlen($a) > 100))) {
            $problems[] = '« authors » doit être une liste de noms.';
        }

        $namespaces = self::autoloadProblems($data['autoload'] ?? [], $problems);
        self::providerProblems($data['providers'] ?? [], $namespaces, $problems);
        $permissions = self::permissionProblems($data['permissions'] ?? [], $problems);
        self::menuProblems($data['menu'] ?? [], is_string($id) ? $id : '', $permissions, $problems);

        return $problems;
    }

    /** @return list<string> prefixes d'espace de noms declares */
    private static function autoloadProblems(mixed $autoload, array &$problems): array
    {
        if (! is_array($autoload)) {
            $problems[] = '« autoload » doit associer un espace de noms à un dossier.';

            return [];
        }
        foreach ($autoload as $namespace => $dir) {
            $valid = is_string($namespace) && preg_match('/^' . preg_quote(self::NAMESPACE_ROOT, '/') . '[A-Za-z0-9_]+(\\\\[A-Za-z0-9_]+)*\\\\$/', $namespace)
                && is_string($dir) && preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*/$#', $dir);
            if (! $valid) {
                $problems[] = "« autoload » : l'espace de noms doit commencer par " . self::NAMESPACE_ROOT . ' et se terminer par \\, le dossier par /.';
            }
        }

        return array_keys($autoload);
    }

    private static function providerProblems(mixed $providers, array $namespaces, array &$problems): void
    {
        if (! is_array($providers) || ! array_is_list($providers)) {
            $problems[] = '« providers » doit être une liste de classes.';

            return;
        }
        foreach ($providers as $class) {
            $inside = is_string($class) && array_filter($namespaces, fn ($ns) => str_starts_with($class, $ns));
            if (! $inside) {
                $problems[] = 'Une classe de « providers » ne fait pas partie des espaces de noms déclarés dans « autoload ».';
            }
        }
    }

    /** @return list<string> cles de permission declarees */
    private static function permissionProblems(mixed $permissions, array &$problems): array
    {
        if (! is_array($permissions)) {
            $problems[] = '« permissions » doit associer une clé à un libellé.';

            return [];
        }
        foreach ($permissions as $key => $label) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9-]{0,29}$/', $key) || ! is_string($label) || trim($label) === '' || mb_strlen($label) > 100) {
                $problems[] = "Permission « {$key} » invalide (clé en minuscules, libellé de 100 caractères au plus).";
            }
        }

        return array_map('strval', array_keys($permissions));
    }

    private static function menuProblems(mixed $menu, string $id, array $permissions, array &$problems): void
    {
        if (! is_array($menu) || ! array_is_list($menu)) {
            $problems[] = '« menu » doit être une liste.';

            return;
        }
        foreach ($menu as $item) {
            $valid = is_array($item)
                && is_string($item['label'] ?? null) && $item['label'] !== '' && mb_strlen($item['label']) <= 40
                && is_string($item['icon'] ?? null) && preg_match('/^bi-[a-z0-9-]+$/', $item['icon'])
                && is_string($item['route'] ?? null) && str_starts_with($item['route'], "admin.plugin.{$id}.") && preg_match('/^[a-z0-9._-]+$/', $item['route'])
                // Une entree sans droit serait visible de tout le personnel : elle doit nommer sa permission
                && is_string($item['permission'] ?? null) && ($item['permission'] === self::ADMIN_ONLY || in_array($item['permission'], $permissions, true));
            if (! $valid) {
                $problems[] = "Entrée de « menu » invalide : libellé, icône (bi-...), route « admin.plugin.{$id}.... » et permission déclarée (ou « admin ») sont obligatoires.";
            }
        }
    }

    private static function normalized(array $data): array
    {
        return [
            'id' => $data['id'], 'name' => $data['name'], 'description' => $data['description'], 'version' => $data['version'],
            'api' => $data['api'], 'requires_nova' => $data['requires_nova'] ?? null, 'url' => $data['url'] ?? null,
            'authors' => array_values($data['authors'] ?? []), 'autoload' => $data['autoload'] ?? [],
            'providers' => $data['providers'] ?? [], 'permissions' => $data['permissions'] ?? [], 'menu' => $data['menu'] ?? [],
        ];
    }
}
