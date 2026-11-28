<?php

namespace App\Plugins;

use App\Services\ActivityLogger;
use App\Services\Backup\BackupException;
use App\Services\Backup\BackupManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

/**
 * Extensions : decouverte dans `plugins/`, etat (actives, en echec), chargement, activation et desactivation.
 *
 * - Une extension ne s'active que si elle a ete installee depuis une archive signee (marqueur .nova-signed), sauf
 *   `nova.plugins.allow_unsigned` (developpement).
 * - L'etat est un fichier (`storage/app/plugins.json`) : il se lit avant la base de donnees, donc un plugin qui
 *   casse la base peut toujours etre desactive.
 * - Une extension qui plante au chargement est desactivee et le site continue.
 * - Ses permissions et son menu n'existent que tant qu'elle est chargee.
 */
class PluginManager
{
    /** Numero de l'API des extensions : a incrementer si une version casse leurs points d'accroche. */
    public const API_VERSION = 1;
    public const SIGNATURE_FILE = '.nova-signed';

    /** @var array<string, Plugin> */
    private array $loaded = [];
    /** @var array<string, array{label: string, plugin: string}> */
    private array $permissions = [];
    /** @var list<array> */
    private array $menu = [];

    public function __construct(private Filesystem $files, private BackupManager $backups) {}

    public function path(string $sub = ''): string
    {
        return rtrim((string) config('nova.plugins.path'), '/') . ($sub === '' ? '' : "/{$sub}");
    }

    /** @return array<string, Plugin> toutes les extensions presentes, actives ou non, par id */
    public function all(): array
    {
        $state = $this->state();
        $plugins = [];
        foreach (is_dir($this->path()) ? $this->files->directories($this->path()) : [] as $dir) {
            $id = basename($dir);
            $plugins[$id] = $this->discover($id, in_array($id, $state['enabled'], true), $state['failed'][$id] ?? null);
        }
        ksort($plugins);

        return $plugins;
    }

    public function find(string $id): ?Plugin
    {
        return $this->all()[$id] ?? null;
    }

    /** Charge les extensions actives : appele une fois au demarrage, avant le demarrage des fournisseurs. */
    public function load(Application $app): void
    {
        foreach ($this->state()['enabled'] as $id) {
            try {
                $plugin = $this->discover($id, true, null);
                if ($reason = $this->unusable($plugin)) {
                    throw new PluginException($reason);
                }
                $this->boot($app, $plugin);
            } catch (\Throwable $e) {
                // Un plugin ne doit jamais empecher le site de demarrer
                report($e);
                $this->recordFailure($id, $e->getMessage());
            }
        }
    }

    /** @return array<string, string> cle complete (`id.cle`) => libelle, pour les extensions chargees */
    public function permissions(): array
    {
        return array_map(fn (array $permission) => $permission['label'], $this->permissions);
    }

    /** @return array<string, array{label: string, permissions: array<string, string>}> permissions par extension chargee */
    public function permissionGroups(): array
    {
        $groups = [];
        foreach ($this->permissions as $key => $permission) {
            $groups[$permission['plugin']]['label'] = $this->loaded[$permission['plugin']]->name();
            $groups[$permission['plugin']]['permissions'][$key] = $permission['label'];
        }

        return $groups;
    }

    /** @return list<array{label: string, icon: string, route: string, permission: string, id: string}> */
    public function menu(): array
    {
        return $this->menu;
    }

    /** Raison pour laquelle l'extension ne peut pas etre chargee ou activee, ou null. */
    public function unusable(?Plugin $plugin): ?string
    {
        if ($plugin === null) {
            return "Cette extension n'est pas installée.";
        }
        if ($plugin->manifest === null) {
            return 'plugin.json invalide : ' . implode(' ', $plugin->problems);
        }
        if ($reason = $this->incompatibility($plugin)) {
            return $reason;
        }
        if ($plugin->signature === null && ! config('nova.plugins.allow_unsigned')) {
            return "Cette extension n'a pas été installée depuis une archive signée : elle est refusée.";
        }

        return null;
    }

    /** Verifie la version de l'API des extensions et celle de NovaCreneaux demandee par un manifeste valide. */
    public function incompatibility(Plugin $plugin): ?string
    {
        if ($plugin->manifest['api'] !== self::API_VERSION) {
            return "Cette extension vise l'API {$plugin->manifest['api']} des extensions ; ce site utilise l'API " . self::API_VERSION . '.';
        }
        $required = $plugin->manifest['requires_nova'];
        if ($required !== null && version_compare((string) config('nova.version'), $required, '<')) {
            return "Cette extension demande NovaCreneaux {$required} ou plus.";
        }

        return null;
    }

    public function enable(string $id): void
    {
        $plugin = $this->find($id);
        if ($reason = $this->unusable($plugin)) {
            throw new PluginException($reason);
        }
        if ($plugin->enabled) {
            return;
        }

        $this->migrate($plugin);
        $this->writeState(fn (array $state) => [
            'enabled' => array_values(array_unique([...$state['enabled'], $id])),
            'failed' => array_diff_key($state['failed'], [$id => true]),
        ]);
        $this->clearCaches();
        ActivityLogger::log('Extension activée', "{$plugin->name()} {$plugin->version()}");
    }

    public function disable(string $id): void
    {
        $plugin = $this->find($id);
        if ($plugin === null) {
            throw new PluginException("Cette extension n'est pas installée.");
        }
        $this->writeState(fn (array $state) => [
            'enabled' => array_values(array_diff($state['enabled'], [$id])),
            'failed' => array_diff_key($state['failed'], [$id => true]),
        ]);
        $this->clearCaches();
        ActivityLogger::log('Extension désactivée', $plugin->name());
    }

    /** Supprime les fichiers d'une extension desactivee. Ses tables restent : ses donnees ne sont pas perdues par erreur. */
    public function delete(string $id): void
    {
        $plugin = $this->find($id);
        if ($plugin === null) {
            throw new PluginException("Cette extension n'est pas installée.");
        }
        if ($plugin->enabled) {
            throw new PluginException("Désactivez l'extension avant de la supprimer.");
        }
        if (is_link($plugin->path)) {
            throw new PluginException('Le dossier de cette extension est un lien symbolique : suppression refusée.');
        }

        $this->files->deleteDirectory($plugin->path);
        $this->writeState(fn (array $state) => ['enabled' => $state['enabled'], 'failed' => array_diff_key($state['failed'], [$id => true])]);
        ActivityLogger::log('Extension supprimée', $plugin->name());
    }

    /** Lance les migrations de l'extension (apres une sauvegarde de la base, qui doit reussir). */
    public function migrate(Plugin $plugin): void
    {
        if (! $plugin->hasMigrations()) {
            return;
        }
        try {
            $this->backups->create(BackupManager::BEFORE_UPDATE);
        } catch (BackupException $e) {
            throw new PluginException("La sauvegarde de la base a échoué ({$e->getMessage()}) : rien n'a été modifié.");
        }
        Artisan::call('migrate', ['--path' => $plugin->path . '/database/migrations', '--realpath' => true, '--force' => true]);
    }

    public function clearCaches(): void
    {
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function boot(Application $app, Plugin $plugin): void
    {
        $manifest = $plugin->manifest;
        foreach ($manifest['autoload'] as $namespace => $dir) {
            PluginAutoloader::add($namespace, "{$plugin->path}/{$dir}");
        }
        foreach ($manifest['providers'] as $class) {
            $provider = new $class($app);
            if (! $provider instanceof PluginProvider) {
                throw new PluginException("{$class} doit étendre " . PluginProvider::class . '.');
            }
            $provider->bindPlugin($plugin);
            $app->register($provider);
        }
        if ($app->isBooted()) {
            // Plugin charge apres le demarrage (activation dans la meme requete, tests) : les noms de routes sont a reindexer
            $app['router']->getRoutes()->refreshNameLookups();
        }

        $this->loaded[$plugin->id] = $plugin;
        foreach ($manifest['permissions'] as $key => $label) {
            $this->permissions["{$plugin->id}.{$key}"] = ['label' => $label, 'plugin' => $plugin->id];
        }
        foreach ($manifest['menu'] as $item) {
            $this->menu[] = $item + ['id' => $plugin->id];
        }
    }

    private function discover(string $id, bool $enabled, ?string $failure): Plugin
    {
        $path = $this->path($id);
        $problems = [];
        $manifest = null;

        if (is_link($path)) {
            $problems[] = 'Le dossier est un lien symbolique.';
        } elseif (! preg_match(Manifest::ID_PATTERN, $id)) {
            $problems[] = "Le nom du dossier n'est pas un identifiant valide.";
        } else {
            $parsed = Manifest::parse((string) @file_get_contents("{$path}/" . Manifest::FILE));
            $manifest = $parsed['manifest'];
            $problems = $parsed['problems'];
            if ($manifest !== null && $manifest['id'] !== $id) {
                $manifest = null;
                $problems[] = "« id » ne correspond pas au nom du dossier ({$id}).";
            }
        }

        $signature = json_decode((string) @file_get_contents("{$path}/" . self::SIGNATURE_FILE), true);

        return new Plugin($id, $path, $manifest, $problems, is_array($signature) ? $signature : null, $enabled, $failure);
    }

    /** @return array{enabled: list<string>, failed: array<string, string>} */
    private function state(): array
    {
        $state = json_decode((string) @file_get_contents($this->stateFile()), true);

        return [
            'enabled' => array_values(array_filter($state['enabled'] ?? [], 'is_string')),
            'failed' => array_filter($state['failed'] ?? [], 'is_string'),
        ];
    }

    /** @param callable(array): array $change */
    private function writeState(callable $change): void
    {
        $file = $this->stateFile();
        @mkdir(dirname($file), 0755, true);
        $temp = $file . '.tmp';
        file_put_contents($temp, json_encode($change($this->state()), JSON_PRETTY_PRINT), LOCK_EX);
        rename($temp, $file);
    }

    private function recordFailure(string $id, string $message): void
    {
        $this->writeState(fn (array $state) => [
            'enabled' => array_values(array_diff($state['enabled'], [$id])),
            'failed' => [$id => mb_substr($message, 0, 300)] + $state['failed'],
        ]);
        // Le journal passe par la session : il attend la fin du demarrage de l'application
        app()->booted(fn () => ActivityLogger::log('Extension désactivée après une erreur', "{$id} : " . mb_substr($message, 0, 200)));
    }

    private function stateFile(): string
    {
        return (string) config('nova.plugins.state');
    }
}
