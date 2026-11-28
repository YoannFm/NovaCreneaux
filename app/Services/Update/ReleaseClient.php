<?php
namespace App\Services\Update;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Lit la derniere release publiee sur GitHub. Ne transmet rien d'autre que la requete elle-meme (aucune information sur le site). */
class ReleaseClient {
    private const CACHE_KEY = 'update_check';

    /**
     * @return array{release: ?array, error: ?string, checked_at: string}
     * release : version, tag, changelog, published_at, zip {name, url, size}, signature {name, url}
     */
    public function check(bool $force = false): array {
        if ($force) {
            Cache::forget(self::CACHE_KEY);
        }

        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->fetch();
        // Un echec est memorise moins longtemps qu'un succes
        Cache::put(self::CACHE_KEY, $result, $result['error'] ? now()->addMinutes(5) : now()->addMinutes(15));

        return $result;
    }

    /** Lecture du cache seule, sans requete reseau (menu et tableau de bord). */
    public function cached(): ?array {
        return Cache::get(self::CACHE_KEY);
    }

    private function fetch(): array {
        $repo = config('nova.repo');
        $result = ['release' => null, 'error' => null, 'checked_at' => now()->toIso8601String()];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'NovaCreneaux-updater',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])->connectTimeout(3)->timeout(8)->get("https://api.github.com/repos/{$repo}/releases/latest");
        } catch (\Throwable $e) {
            return ['error' => 'Impossible de joindre GitHub : ' . $e->getMessage()] + $result;
        }

        if ($response->status() === 404) {
            return $result; // aucune release publiee : rien a installer
        }
        if (!$response->successful()) {
            return ['error' => 'GitHub a répondu avec le code ' . $response->status() . '.'] + $result;
        }

        try {
            $result['release'] = $this->parse($response->json() ?? [], $repo);
        } catch (UpdateException $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function parse(array $data, string $repo): ?array {
        if (!preg_match('/^v?(\d+\.\d+\.\d+)$/', (string) ($data['tag_name'] ?? ''), $match)) {
            return null; // etiquette qui n'est pas un numero de version
        }
        [$tag, $version] = [$data['tag_name'], $match[1]];
        $assets = collect($data['assets'] ?? []);
        $prefix = "https://github.com/{$repo}/releases/download/{$tag}/";

        $find = function (string $name) use ($assets, $prefix) {
            $asset = $assets->firstWhere('name', $name);
            // L'adresse doit rester dans les fichiers de cette release : une adresse ailleurs est ignoree
            return $asset && str_starts_with((string) ($asset['browser_download_url'] ?? ''), $prefix) ? $asset : null;
        };
        $zip = $find("novacreneaux-{$version}.zip");
        $signature = $find("novacreneaux-{$version}.zip.sig");
        if (!$zip || !$signature) {
            throw new UpdateException("La release {$tag} ne contient pas l'archive signée attendue (novacreneaux-{$version}.zip et .zip.sig).");
        }

        return [
            'version' => $version,
            'tag' => $tag,
            'changelog' => mb_substr((string) ($data['body'] ?? ''), 0, 20000),
            'published_at' => $data['published_at'] ?? null,
            'zip' => ['name' => $zip['name'], 'url' => $zip['browser_download_url'], 'size' => (int) ($zip['size'] ?? 0)],
            'signature' => ['name' => $signature['name'], 'url' => $signature['browser_download_url']],
        ];
    }
}
