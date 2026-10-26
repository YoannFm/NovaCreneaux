<?php

namespace App\Plugins;

use App\Services\Update\SignatureVerifier;
use App\Services\Update\UpdateException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Catalogue des extensions : `index.json` et sa signature, publies dans un depot GitHub public. L'index est signe
 * avec la cle des extensions : une adresse ou un depot modifie ne peut ni ajouter ni remplacer une extension.
 * Rien n'est envoye sur le site : deux lectures de fichiers publics.
 */
class Catalog
{
    private const CACHE_KEY = 'plugin_catalog';
    private const MAX_INDEX_BYTES = 1048576;

    public function __construct(private SignatureVerifier $signature) {}

    public function signingConfigured(): bool
    {
        return $this->signature->isConfigured(config('nova.plugins.public_key'));
    }

    /** @return array{plugins: array<string, array>, error: ?string, checked_at: string} extensions par id */
    public function get(bool $force = false): array
    {
        if ($force) {
            Cache::forget(self::CACHE_KEY);
        }
        if (($cached = Cache::get(self::CACHE_KEY)) !== null) {
            return $cached;
        }

        $result = $this->fetch();
        Cache::put(self::CACHE_KEY, $result, $result['error'] ? now()->addMinutes(5) : now()->addMinutes(15));

        return $result;
    }

    private function fetch(): array
    {
        $result = ['plugins' => [], 'error' => null, 'checked_at' => now()->toIso8601String()];
        if (! $this->signingConfigured()) {
            return ['error' => "La clé de signature des extensions n'est pas configurée : le catalogue est désactivé."] + $result;
        }

        $base = 'https://raw.githubusercontent.com/' . config('nova.plugins.catalog_repo') . '/main/';
        try {
            $index = Http::connectTimeout(3)->timeout(8)->withHeaders(['User-Agent' => 'NovaCreneaux-plugins'])->get($base . 'index.json');
            if ($index->status() === 404) {
                return ['error' => "Le catalogue n'est pas publié."] + $result;
            }
            $signature = Http::connectTimeout(3)->timeout(8)->withHeaders(['User-Agent' => 'NovaCreneaux-plugins'])->get($base . 'index.json.sig')->throw()->body();
            $index->throw();
        } catch (\Throwable $e) {
            return ['error' => 'Impossible de lire le catalogue : ' . $e->getMessage()] + $result;
        }

        $content = $index->body();
        try {
            if (strlen($content) > self::MAX_INDEX_BYTES) {
                throw new UpdateException('Le catalogue est trop gros.');
            }
            $this->verify($content, $signature);
        } catch (UpdateException $e) {
            return ['error' => 'Catalogue refusé : ' . $e->getMessage()] + $result;
        }

        foreach ((json_decode($content, true)['plugins'] ?? []) as $entry) {
            if ($parsed = $this->parse($entry)) {
                $result['plugins'][$parsed['id']] = $parsed;
            }
        }
        ksort($result['plugins']);

        return $result;
    }

    /** Le verificateur de signature lit un fichier : l'index est ecrit un instant dans un fichier temporaire. */
    private function verify(string $content, string $signature): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ncidx');
        try {
            file_put_contents($file, $content);
            $this->signature->verify($file, $signature, config('nova.plugins.public_key'));
        } finally {
            @unlink($file);
        }
    }

    /** Une entree mal formee est ignoree : elle ne doit pas empecher d'afficher les autres. */
    private function parse(mixed $entry): ?array
    {
        $valid = is_array($entry)
            && is_string($entry['id'] ?? null) && preg_match(Manifest::ID_PATTERN, $entry['id'])
            && is_string($entry['name'] ?? null) && is_string($entry['description'] ?? null)
            && is_string($entry['version'] ?? null) && preg_match('/^\d+\.\d+\.\d+$/', $entry['version'])
            && is_string($entry['tag'] ?? null) && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $entry['tag'])
            && is_string($entry['archive'] ?? null) && preg_match('/^[A-Za-z0-9._-]{1,100}\.zip$/', $entry['archive'])
            && is_int($entry['size'] ?? null) && $entry['size'] > 0;
        if (! $valid) {
            return null;
        }

        $release = 'https://github.com/' . config('nova.plugins.catalog_repo') . "/releases/download/{$entry['tag']}/";

        return [
            'id' => $entry['id'], 'name' => mb_substr($entry['name'], 0, 80), 'description' => mb_substr($entry['description'], 0, 300),
            'version' => $entry['version'], 'author' => mb_substr((string) ($entry['author'] ?? ''), 0, 100),
            'requires_nova' => is_string($entry['requires_nova'] ?? null) ? $entry['requires_nova'] : null,
            'permissions' => array_values(array_filter((array) ($entry['permissions'] ?? []), 'is_string')),
            'size' => $entry['size'], 'archive_url' => $release . $entry['archive'], 'signature_url' => $release . $entry['archive'] . '.sig',
        ];
    }
}
