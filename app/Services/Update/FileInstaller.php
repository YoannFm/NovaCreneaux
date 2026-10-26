<?php
namespace App\Services\Update;
use ZipArchive;

/** Copie les fichiers d'une archive de release dans le site, avec sauvegarde des fichiers remplaces et retour arriere. */
class FileInstaller {
    private const MANIFEST = 'release.json';
    private const BACKUP_MANIFEST = 'backup.json';

    /** Lit release.json d'une archive. */
    public function manifest(string $zipPath): array {
        $zip = $this->open($zipPath);
        $json = $zip->getFromName(self::MANIFEST);
        $zip->close();

        $manifest = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($manifest) || !isset($manifest['version'], $manifest['php'])) {
            throw new UpdateException("L'archive ne contient pas de release.json valide.");
        }

        return $manifest;
    }

    /**
     * Installe l'archive. Tous les chemins sont verifies AVANT d'ecrire quoi que ce soit.
     * En cas d'echec en cours de route, les fichiers d'origine sont remis en place.
     *
     * @param list<string> $deleted fichiers supprimes par cette version
     * @param array{from?: string, to?: string} $meta versions concernees, rangees dans la sauvegarde
     * @return array{replaced: int, added: int, deleted: int}
     */
    public function install(string $zipPath, string $basePath, string $backupPath, array $deleted, array $meta = []): array {
        $zip = $this->open($zipPath);
        $entries = $this->validatedEntries($zip);
        $deleted = array_values(array_filter($deleted, fn ($path) => is_string($path) && PathPolicy::allows($path)));

        $existing = array_values(array_filter(array_merge(array_keys($entries), $deleted), fn ($path) => is_file("{$basePath}/{$path}")));
        $added = array_values(array_diff(array_keys($entries), $existing));
        $this->backup($basePath, $backupPath, $existing, $added, $meta);

        try {
            foreach ($entries as $path => $index) {
                $this->write($basePath, $path, $zip->getStream($zip->getNameIndex($index)));
            }
            foreach ($deleted as $path) {
                if (is_file("{$basePath}/{$path}")) {
                    unlink("{$basePath}/{$path}");
                }
            }
        } catch (\Throwable $e) {
            $zip->close();
            $this->restore($backupPath, $basePath);
            throw new UpdateException("L'installation a échoué, les fichiers d'origine ont été remis en place : " . $e->getMessage());
        }
        $zip->close();

        return ['replaced' => count($existing) - count(array_intersect($existing, $deleted)), 'added' => count($added), 'deleted' => count($deleted)];
    }

    /** Remet les fichiers d'une sauvegarde et supprime ceux que la mise a jour avait ajoutes. */
    public function restore(string $backupPath, string $basePath): array {
        $zip = $this->open($backupPath);
        $meta = json_decode((string) $zip->getFromName(self::BACKUP_MANIFEST), true);
        if (!is_array($meta)) {
            throw new UpdateException("La sauvegarde est illisible (backup.json manquant).");
        }

        foreach ($meta['added'] ?? [] as $path) {
            if (PathPolicy::allows($path) && is_file("{$basePath}/{$path}")) {
                unlink("{$basePath}/{$path}");
            }
        }

        $restored = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === self::BACKUP_MANIFEST || str_ends_with($name, '/')) {
                continue;
            }
            if (!PathPolicy::allows($name)) {
                throw new UpdateException("Sauvegarde refusée : chemin interdit « {$name} ».");
            }
            $this->write($basePath, $name, $zip->getStream($name));
            $restored++;
        }
        $zip->close();

        return ['restored' => $restored, 'removed' => count($meta['added'] ?? []), 'from' => $meta['from'] ?? null, 'to' => $meta['to'] ?? null];
    }

    /** @return array<string, int> chemin => index dans l'archive */
    private function validatedEntries(ZipArchive $zip): array {
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === self::MANIFEST || str_ends_with($name, '/')) {
                continue;
            }
            if (!PathPolicy::allows($name)) {
                throw new UpdateException("Archive refusée : chemin interdit « {$name} ».");
            }
            if ($this->isSymlink($zip, $i)) {
                throw new UpdateException("Archive refusée : lien symbolique « {$name} ».");
            }
            $entries[$name] = $i;
        }
        if ($entries === []) {
            throw new UpdateException("L'archive ne contient aucun fichier.");
        }

        return $entries;
    }

    private function isSymlink(ZipArchive $zip, int $index): bool {
        $zip->getExternalAttributesIndex($index, $opsys, $attributes);

        return $opsys === ZipArchive::OPSYS_UNIX && ((($attributes >> 16) & 0170000) === 0120000);
    }

    private function backup(string $basePath, string $backupPath, array $existing, array $added, array $meta): void {
        @mkdir(dirname($backupPath), 0755, true);
        $backup = new ZipArchive();
        if ($backup->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException('Impossible de créer la sauvegarde des fichiers.');
        }
        foreach ($existing as $path) {
            $backup->addFile("{$basePath}/{$path}", $path);
        }
        $backup->addFromString(self::BACKUP_MANIFEST, json_encode($meta + ['added' => $added, 'created_at' => now()->toIso8601String()]));
        if (!$backup->close()) {
            throw new UpdateException('Impossible d\'écrire la sauvegarde des fichiers (espace disque ?).');
        }
    }

    /** Ecriture atomique : fichier temporaire dans le meme dossier, puis renommage. */
    private function write(string $basePath, string $path, $stream): void {
        $target = "{$basePath}/{$path}";
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) {
            throw new UpdateException("Impossible de créer le dossier de {$path}.");
        }

        $temp = $target . '.nc-new';
        $out = fopen($temp, 'wb');
        if ($out === false || !is_resource($stream) || stream_copy_to_stream($stream, $out) === false) {
            @unlink($temp);
            throw new UpdateException("Impossible d'écrire {$path}.");
        }
        fclose($out);
        fclose($stream);

        if (!rename($temp, $target)) {
            @unlink($temp);
            throw new UpdateException("Impossible de remplacer {$path}.");
        }
    }

    private function open(string $path): ZipArchive {
        $zip = new ZipArchive();
        if (!is_file($path) || $zip->open($path) !== true) {
            throw new UpdateException("Impossible d'ouvrir l'archive (" . basename($path) . ').');
        }

        return $zip;
    }
}
