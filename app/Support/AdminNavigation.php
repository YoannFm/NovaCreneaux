<?php

namespace App\Support;

use App\Models\User;
use App\Plugins\Manifest;
use App\Plugins\PluginManager;
use App\Services\Update\UpdateManager;

/**
 * Menu de l'administration : une seule description, filtree selon les droits de l'utilisateur.
 * Pour ajouter une entree : l'ajouter a definition() avec la permission de sa route (voir routes/web.php).
 * `permission` : null = tout le personnel, ADMIN_ONLY = administrateurs, sinon une cle de App\Support\Permission.
 */
class AdminNavigation
{
    public const ADMIN_ONLY = 'admin';

    public function __construct(private UpdateManager $updates, private PluginManager $plugins) {}

    /** @return list<array{header: ?string, items: list<array>}> sections non vides, entrees resolues (url, active, badge) */
    public function for(User $user): array
    {
        $sections = [];
        foreach ($this->definition() as $section) {
            $items = array_values(array_filter(array_map(fn (array $item) => $this->resolve($item, $user), $section['items'])));
            if ($items !== []) {
                $sections[] = ['header' => $section['header'], 'items' => $items];
            }
        }

        return $sections;
    }

    /** Toutes les entrees visibles (sous-menus aplatis), pour les tests et les controles de droits. */
    public function links(User $user): array
    {
        $links = [];
        foreach ($this->for($user) as $section) {
            foreach ($section['items'] as $item) {
                array_push($links, ...($item['children'] ?: [$item]));
            }
        }

        return $links;
    }

    private function definition(): array
    {
        return [
            ['header' => null, 'items' => [
                $this->link('Tableau de bord', 'bi-speedometer2', 'admin.dashboard', null),
                $this->link('Navigation', 'bi-list-ul', 'admin.nav.index', Permission::NAVIGATION, 'admin.nav.*'),
            ]],
            ['header' => 'Paramètres', 'items' => [
                ['label' => 'Paramètres', 'icon' => 'bi-gear', 'id' => 'settings', 'children' => [
                    $this->link('Général', null, 'admin.settings.general', self::ADMIN_ONLY),
                    $this->link('Accueil', null, 'admin.settings.home', self::ADMIN_ONLY),
                    $this->link('Apparence', null, 'admin.themes.index', Permission::APPEARANCE, 'admin.themes.*'),
                    $this->link('Authentification', null, 'admin.settings.auth', self::ADMIN_ONLY),
                    $this->link('Sécurité', null, 'admin.settings.security', self::ADMIN_ONLY),
                    $this->link('E-mail', null, 'admin.settings.mail', self::ADMIN_ONLY),
                    $this->link('Maintenance', null, 'admin.settings.maintenance', self::ADMIN_ONLY),
                    $this->link('Réservations', null, 'admin.settings.reservations', self::ADMIN_ONLY),
                ]],
            ]],
            ['header' => 'Gestion', 'items' => [
                $this->link('Pages', 'bi-file-earmark-text', 'admin.pages.index', Permission::PAGES, 'admin.pages.*'),
                $this->link('Ressources', 'bi-collection', 'admin.resources.index', Permission::RESOURCES, 'admin.resources.*', 'admin.slots.*'),
                $this->link('Réservations', 'bi-calendar-check', 'admin.reservations.index', Permission::RESERVATIONS, 'admin.reservations.*'),
            ]],
            ...$this->pluginSection(),
            ['header' => 'Utilisateurs', 'items' => [
                $this->link('Utilisateurs', 'bi-people', 'admin.users.index', Permission::USERS, 'admin.users.*'),
                $this->link('Rôles', 'bi-shield-check', 'admin.roles.index', self::ADMIN_ONLY, 'admin.roles.*'),
                $this->link('Sanctions', 'bi-exclamation-triangle', 'admin.sanctions.index', Permission::SANCTIONS, 'admin.sanctions.*'),
                $this->link('Notifications', 'bi-bell', 'admin.notifications.index', Permission::NOTIFICATIONS, 'admin.notifications.*'),
            ]],
            ['header' => 'Système', 'items' => [
                $this->link('Activité', 'bi-journal-text', 'admin.logs.index', Permission::LOGS, 'admin.logs.*'),
                $this->link('Intégrations API', 'bi-plug', 'admin.api-tokens.index', self::ADMIN_ONLY, 'admin.api-tokens.*'),
                $this->link('Webhooks', 'bi-broadcast', 'admin.webhooks.index', self::ADMIN_ONLY, 'admin.webhooks.*'),
                $this->link('Extensions', 'bi-puzzle', 'admin.plugins.index', self::ADMIN_ONLY, 'admin.plugins.*'),
                $this->link('Mise à jour', 'bi-cloud-download', 'admin.update.index', self::ADMIN_ONLY, 'admin.update.*') + [
                    'badge' => fn () => $this->updates->cachedHasUpdate() ? 'Une nouvelle version est disponible' : null,
                ],
            ]],
        ];
    }

    /** Entrees des extensions chargees : chacune porte la permission que son plugin.json a declaree. */
    private function pluginSection(): array
    {
        $items = array_map(fn (array $item) => $this->link(
            $item['label'], $item['icon'], $item['route'],
            $item['permission'] === Manifest::ADMIN_ONLY ? self::ADMIN_ONLY : "{$item['id']}.{$item['permission']}",
            "admin.plugin.{$item['id']}.*",
        ), $this->plugins->menu());

        return $items === [] ? [] : [['header' => 'Extensions', 'items' => $items]];
    }

    /** `$active` : motifs de routes qui marquent l'entree comme courante (par defaut, sa propre route). */
    private function link(string $label, ?string $icon, string $route, ?string $permission, string ...$active): array
    {
        return compact('label', 'icon', 'route', 'permission') + ['active' => $active ?: [$route]];
    }

    private function resolve(array $item, User $user): ?array
    {
        if (isset($item['children'])) {
            $children = array_values(array_filter(array_map(fn (array $child) => $this->resolve($child, $user), $item['children'])));
            if ($children === []) {
                return null;
            }

            return [
                'label' => $item['label'], 'icon' => $item['icon'], 'id' => $item['id'], 'url' => null, 'badge' => null,
                'children' => $children, 'active' => (bool) array_filter($children, fn (array $child) => $child['active']),
            ];
        }

        if (! $this->allows($user, $item['permission'])) {
            return null;
        }

        return [
            'label' => $item['label'], 'icon' => $item['icon'], 'permission' => $item['permission'], 'children' => [],
            'url' => route($item['route']), 'route' => $item['route'], 'id' => null,
            'active' => request()->routeIs(...$item['active']),
            'badge' => isset($item['badge']) ? $item['badge']() : null,
        ];
    }

    private function allows(User $user, ?string $permission): bool
    {
        return match ($permission) {
            null => true,
            self::ADMIN_ONLY => $user->isAdmin(),
            default => $user->hasPermission($permission),
        };
    }
}
