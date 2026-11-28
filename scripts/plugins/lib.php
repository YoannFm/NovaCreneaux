<?php
/**
 * Outils de publication des extensions : archive signee d'une extension, puis index signe du catalogue.
 * Ce dossier n'est pas livre dans l'archive du site.
 */

require_once __DIR__ . '/../release/lib.php';
require_once __DIR__ . '/../../app/Plugins/Manifest.php';

use App\Plugins\Manifest;

/**
 * Construit {id}-{version}.zip et sa signature dans $outputDir. Refuse une extension dont plugin.json est invalide,
 * un lien symbolique ou un fichier au nom que le site refuserait a l'installation.
 *
 * @return array{path: string, manifest: array, files: int}
 */
function plugin_pack(string $pluginDir, string $outputDir, string $secretKeyBase64): array
{
    $parsed = Manifest::parse((string) @file_get_contents(rtrim($pluginDir, '/') . '/plugin.json'));
    if ($parsed['manifest'] === null) {
        throw new RuntimeException('plugin.json invalide : ' . implode(' ', $parsed['problems']));
    }
    $manifest = $parsed['manifest'];

    @mkdir($outputDir, 0755, true);
    $path = rtrim($outputDir, '/') . "/{$manifest['id']}-{$manifest['version']}.zip";
    @unlink($path);
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE) !== true) {
        throw new RuntimeException("Impossible de creer {$path}");
    }

    $root = rtrim(realpath($pluginDir), '/');
    $count = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        if ($file->isLink()) {
            throw new RuntimeException("Lien symbolique refuse : {$relative}");
        }
        if (! $file->isFile() || str_starts_with(basename($relative), '.')) {
            continue; // .DS_Store, .gitkeep... ne sont pas livres
        }
        if (! preg_match('#^[A-Za-z0-9._/-]+$#', $relative)) {
            throw new RuntimeException("Nom de fichier refuse (caracteres autorises : lettres, chiffres, . _ - /) : {$relative}");
        }
        $zip->addFile($file->getPathname(), $relative);
        $count++;
    }
    if (! $zip->close()) {
        throw new RuntimeException("Ecriture de l'archive impossible");
    }
    release_sign($path, $secretKeyBase64);

    return ['path' => $path, 'manifest' => $manifest, 'files' => $count];
}

/**
 * Ecrit index.json et index.json.sig dans $outputDir a partir des archives de $archivesDir (la plus recente de
 * chaque extension). Le tag de release de chaque archive est « {id}-{version} ».
 *
 * @return array{path: string, plugins: int}
 */
function plugin_catalog(string $archivesDir, string $outputDir, string $secretKeyBase64): array
{
    $entries = [];
    foreach (glob(rtrim($archivesDir, '/') . '/*.zip') ?: [] as $archive) {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Archive illisible : ' . basename($archive));
        }
        $parsed = Manifest::parse((string) $zip->getFromName('plugin.json'));
        $zip->close();
        $manifest = $parsed['manifest'] ?? throw new RuntimeException(basename($archive) . ' : plugin.json invalide.');
        if (basename($archive) !== "{$manifest['id']}-{$manifest['version']}.zip") {
            throw new RuntimeException(basename($archive) . " ne porte pas le nom attendu ({$manifest['id']}-{$manifest['version']}.zip).");
        }
        if (! is_file($archive . '.sig') || ! release_verify($archive, base64_encode(sodium_crypto_sign_publickey_from_secretkey(base64_decode($secretKeyBase64))))) {
            throw new RuntimeException(basename($archive) . ' : signature absente ou invalide.');
        }

        $current = $entries[$manifest['id']] ?? null;
        if ($current === null || version_compare($manifest['version'], $current['version'], '>')) {
            $entries[$manifest['id']] = [
                'id' => $manifest['id'], 'name' => $manifest['name'], 'description' => $manifest['description'],
                'version' => $manifest['version'], 'author' => $manifest['authors'][0] ?? '', 'requires_nova' => $manifest['requires_nova'],
                'permissions' => array_values($manifest['permissions']), 'tag' => "{$manifest['id']}-{$manifest['version']}",
                'archive' => basename($archive), 'size' => filesize($archive),
            ];
        }
    }
    ksort($entries);

    @mkdir($outputDir, 0755, true);
    $index = rtrim($outputDir, '/') . '/index.json';
    file_put_contents($index, json_encode(['generated_at' => gmdate('c'), 'plugins' => array_values($entries)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    release_sign($index, $secretKeyBase64);

    return ['path' => $index, 'plugins' => count($entries)];
}
