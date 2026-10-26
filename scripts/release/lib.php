<?php
/**
 * Outils de publication d'une version : construction de l'archive, signature, verification.
 * Utilises par .github/workflows/release.yml et par les tests. Ce dossier n'est pas livre dans l'archive.
 */

require_once __DIR__ . '/../../app/Services/Update/PathPolicy.php';

use App\Services\Update\PathPolicy;

/** Fichiers livres : exactement ceux que le site accepte d'installer (PathPolicy), rien d'autre. */
function release_files(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($root, '/')) + 1));
        if (PathPolicy::allows($relative) && !str_ends_with($relative, '.nc-new') && basename($relative) !== '.DS_Store') {
            $files[] = $relative;
        }
    }
    sort($files);

    return $files;
}

/** Version declaree dans config/nova.php. */
function release_declared_version(string $root): string
{
    $config = include "{$root}/config/nova.php";

    return $config['version'];
}

/** Version minimale de PHP, d'apres composer.json (ex. "^8.3" donne "8.3.0"). */
function release_min_php(string $root): string
{
    $composer = json_decode(file_get_contents("{$root}/composer.json"), true);
    preg_match('/(\d+)\.(\d+)(?:\.(\d+))?/', $composer['require']['php'], $m);

    return "{$m[1]}.{$m[2]}." . ($m[3] ?? '0');
}

/**
 * Construit novacreneaux-{version}.zip dans $outputDir.
 *
 * @param list<string> $deleted fichiers supprimes depuis la version precedente
 * @return array{path: string, files: int, bytes: int}
 */
function release_build(string $root, string $outputDir, string $version, array $deleted = []): array
{
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        throw new InvalidArgumentException("Version invalide : {$version}");
    }
    if (release_declared_version($root) !== $version) {
        throw new RuntimeException('config/nova.php declare la version ' . release_declared_version($root) . ", pas {$version} : corrigez la constante avant de publier.");
    }

    @mkdir($outputDir, 0755, true);
    $path = rtrim($outputDir, '/') . "/novacreneaux-{$version}.zip";
    @unlink($path);

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE) !== true) {
        throw new RuntimeException("Impossible de creer {$path}");
    }
    $files = release_files($root);
    foreach ($files as $relative) {
        $zip->addFile("{$root}/{$relative}", $relative);
    }
    $deleted = array_values(array_filter(array_map('trim', $deleted), fn ($p) => $p !== '' && PathPolicy::allows($p)));
    $zip->addFromString('release.json', json_encode([
        'name' => 'novacreneaux',
        'version' => $version,
        'php' => release_min_php($root),
        'built_at' => gmdate('c'),
        'deleted' => $deleted,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if (!$zip->close()) {
        throw new RuntimeException("Ecriture de l'archive impossible");
    }

    return ['path' => $path, 'files' => count($files), 'bytes' => filesize($path)];
}

/** Signature ed25519 detachee, ecrite dans {zip}.sig (base64). */
function release_sign(string $zipPath, string $secretKeyBase64): string
{
    $secretKey = base64_decode($secretKeyBase64, true);
    if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        throw new RuntimeException('Cle secrete de signature invalide (base64 de 64 octets attendu).');
    }
    $signature = base64_encode(sodium_crypto_sign_detached(file_get_contents($zipPath), $secretKey));
    file_put_contents($zipPath . '.sig', $signature . "\n");

    return $signature;
}

function release_verify(string $zipPath, string $publicKeyBase64): bool
{
    $publicKey = base64_decode($publicKeyBase64, true);
    $signature = base64_decode(trim((string) @file_get_contents($zipPath . '.sig')), true);

    return $publicKey !== false && $signature !== false
        && strlen($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        && strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES
        && sodium_crypto_sign_verify_detached($signature, file_get_contents($zipPath), $publicKey);
}
