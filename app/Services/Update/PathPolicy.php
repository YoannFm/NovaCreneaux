<?php
namespace App\Services\Update;

/**
 * Liste blanche des fichiers qu'une mise a jour peut ecrire ou supprimer.
 * Jamais : .env, storage/, uploads, caches, bases SQLite. Tout chemin suspect (.., absolu, antislash) est refuse.
 */
final class PathPolicy {
    private const DIRECTORIES = ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'vendor'];
    private const ROOT_FILES = ['artisan', 'composer.json', 'composer.lock', '.env.example'];
    private const PROTECTED = [
        '#(^|/)\.git(/|$)#',
        '#^bootstrap/cache/#',
        '#^public/(uploads|storage)(/|$)#',
        '#^public/hot$#',
        '#^database/[^/]*\.sqlite#',
    ];

    /** Chemin relatif propre, ou null s'il est invalide. */
    public static function normalize(string $path): ?string {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path)) {
            return null;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path;
    }

    public static function allows(string $path): bool {
        $path = self::normalize($path);
        if ($path === null) {
            return false;
        }
        foreach (self::PROTECTED as $pattern) {
            if (preg_match($pattern, $path)) {
                return false;
            }
        }

        return in_array($path, self::ROOT_FILES, true)
            || (str_contains($path, '/') && in_array(explode('/', $path)[0], self::DIRECTORIES, true));
    }
}
