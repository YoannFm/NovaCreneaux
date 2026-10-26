<?php

namespace App\Plugins;

use App\Services\ActivityLogger;
use App\Services\Update\PathPolicy;
use App\Services\Update\SignatureVerifier;
use App\Services\Update\UpdateException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use ZipArchive;

/**
 * Installe ou met a jour une extension du catalogue : telechargement, verification de la signature, controle de
 * l'archive (chemins, liens, taille), extraction dans un dossier temporaire, puis remplacement du dossier. L'ancienne
 * version est gardee dans storage/app/plugins-backup. Rien n'est ecrit dans plugins/ avant que tout soit verifie.
 */
class PluginInstaller
{
    private const MAX_ARCHIVE_BYTES = 20 * 1024 * 1024;
    private const MAX_FILES = 3000;
    private const MAX_UNPACKED_BYTES = 60 * 1024 * 1024;
    private const KEPT_BACKUPS = 2;

    public function __construct(
        private Catalog $catalog,
        private SignatureVerifier $signature,
        private PluginManager $plugins,
        private Filesystem $files,
    ) {}

    /** @param array $entry entree du catalogue (voir Catalog::get) */
    public function install(array $entry): Plugin
    {
        if (! $this->catalog->signingConfigured()) {
            throw new PluginException("La clé de signature des extensions n'est pas configurée : installation refusée.");
        }
        if ($entry['size'] > self::MAX_ARCHIVE_BYTES) {
            throw new PluginException("Taille d'archive refusée.");
        }

        $work = $this->workDir() . '/' . bin2hex(random_bytes(6));
        $zipPath = "{$work}/{$entry['id']}.zip";
        $unpacked = "{$work}/unpacked";
        @mkdir($unpacked, 0700, true);

        try {
            $this->download($entry, $zipPath);
            $manifest = $this->extract($zipPath, $unpacked, $entry);
            file_put_contents("{$unpacked}/" . PluginManager::SIGNATURE_FILE, json_encode([
                'version' => $manifest['version'], 'sha256' => hash_file('sha256', $zipPath), 'installed_at' => now()->toIso8601String(),
            ]));

            $previous = $this->plugins->find($entry['id']);
            $this->replace($entry['id'], $unpacked, $previous);
        } finally {
            $this->files->deleteDirectory($work);
        }

        $plugin = $this->plugins->find($entry['id']);
        if ($plugin->enabled) {
            $this->plugins->migrate($plugin);
            $this->plugins->clearCaches();
        }
        ActivityLogger::log($previous ? 'Extension mise à jour' : 'Extension installée', "{$plugin->name()} {$plugin->version()}");

        return $plugin;
    }

    private function download(array $entry, string $zipPath): void
    {
        try {
            Http::connectTimeout(5)->timeout(120)->withOptions(['sink' => $zipPath])->get($entry['archive_url'])->throw();
            $signature = Http::connectTimeout(5)->timeout(20)->get($entry['signature_url'])->throw()->body();
        } catch (\Throwable $e) {
            throw new PluginException('Téléchargement impossible : ' . $e->getMessage());
        }
        if (filesize($zipPath) !== $entry['size']) {
            throw new PluginException("L'archive téléchargée est incomplète.");
        }
        try {
            $this->signature->verify($zipPath, mb_substr($signature, 0, 1024), config('nova.plugins.public_key'));
        } catch (UpdateException $e) {
            throw new PluginException($e->getMessage());
        }
    }

    /** Controle tous les chemins avant d'ecrire quoi que ce soit, puis extrait. @return array le manifeste valide */
    private function extract(string $zipPath, string $into, array $entry): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new PluginException("Impossible d'ouvrir l'archive.");
        }
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_FILES) {
            throw new PluginException("Archive refusée : nombre de fichiers invalide.");
        }

        $total = 0;
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];
            $isDirectory = str_ends_with($name, '/');
            $clean = PathPolicy::normalize($isDirectory ? rtrim($name, '/') : $name);
            if ($clean === null || ! preg_match('#^[A-Za-z0-9._/-]+$#', $clean) || $clean === PluginManager::SIGNATURE_FILE) {
                throw new PluginException("Archive refusée : chemin interdit « {$name} ».");
            }
            $zip->getExternalAttributesIndex($i, $opsys, $attributes);
            if ($opsys === ZipArchive::OPSYS_UNIX && ((($attributes >> 16) & 0170000) === 0120000)) {
                throw new PluginException("Archive refusée : lien symbolique « {$name} ».");
            }
            $total += $stat['size'];
            if ($total > self::MAX_UNPACKED_BYTES) {
                throw new PluginException('Archive refusée : contenu trop volumineux.');
            }
            if (! $isDirectory) {
                $names[$clean] = $i;
            }
        }

        $parsed = Manifest::parse((string) $zip->getFromName(Manifest::FILE));
        $manifest = $parsed['manifest'];
        if ($manifest === null) {
            throw new PluginException('plugin.json invalide : ' . implode(' ', $parsed['problems']));
        }
        if ($manifest['id'] !== $entry['id'] || $manifest['version'] !== $entry['version']) {
            throw new PluginException("L'archive ne contient pas l'extension annoncée par le catalogue ({$entry['id']} {$entry['version']}).");
        }

        foreach ($names as $name => $index) {
            $target = "{$into}/{$name}";
            @mkdir(dirname($target), 0755, true);
            $out = fopen($target, 'wb');
            $in = $zip->getStream($zip->getNameIndex($index));
            if ($out === false || ! is_resource($in) || stream_copy_to_stream($in, $out) === false) {
                throw new PluginException("Impossible d'extraire {$name}.");
            }
            fclose($in);
            fclose($out);
        }
        $zip->close();

        if ($reason = $this->plugins->incompatibility(new Plugin($entry['id'], $into, $manifest, [], null))) {
            throw new PluginException($reason);
        }

        return $manifest;
    }

    /** Remplace le dossier de l'extension, apres avoir garde l'ancienne version. */
    private function replace(string $id, string $unpacked, ?Plugin $previous): void
    {
        $target = $this->plugins->path($id);
        @mkdir($this->plugins->path(), 0755, true);
        if (is_link($target) || is_link($this->plugins->path())) {
            throw new PluginException('Installation refusée : le dossier des extensions passe par un lien symbolique.');
        }

        if ($previous !== null) {
            $backup = $this->backupDir() . "/{$id}-" . ($previous->version() ?? 'inconnue') . '-' . now()->format('Ymd-His');
            @mkdir($this->backupDir(), 0700, true);
            if (! rename($target, $backup)) {
                throw new PluginException("Impossible de mettre de côté l'ancienne version.");
            }
        }
        if (! rename($unpacked, $target)) {
            if (isset($backup)) {
                rename($backup, $target);
            }
            throw new PluginException("Impossible d'installer l'extension.");
        }
        $this->pruneBackups($id);
    }

    private function pruneBackups(string $id): void
    {
        $old = glob($this->backupDir() . "/{$id}-*", GLOB_ONLYDIR) ?: [];
        rsort($old);
        foreach (array_slice($old, self::KEPT_BACKUPS) as $dir) {
            $this->files->deleteDirectory($dir);
        }
    }

    private function workDir(): string
    {
        return storage_path('app/plugins-tmp');
    }

    private function backupDir(): string
    {
        return storage_path('app/plugins-backup');
    }
}
