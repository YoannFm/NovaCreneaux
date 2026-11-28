<?php

namespace Tests\Unit;

use App\Services\Update\PathPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PathPolicyTest extends TestCase
{
    #[DataProvider('allowed')]
    public function test_application_files_are_allowed(string $path): void
    {
        $this->assertTrue(PathPolicy::allows($path), $path);
    }

    public static function allowed(): array
    {
        return array_map(fn ($p) => [$p], [
            'app/Models/User.php', 'vendor/laravel/framework/src/Foo.php', 'config/nova.php', 'public/build/manifest.json',
            'public/index.php', 'resources/views/a.blade.php', 'routes/web.php', 'database/migrations/x.php', 'lang/fr/auth.php',
            'bootstrap/app.php', 'artisan', 'composer.json', 'composer.lock', '.env.example',
        ]);
    }

    #[DataProvider('refused')]
    public function test_dangerous_or_protected_paths_are_refused(string $path): void
    {
        $this->assertFalse(PathPolicy::allows($path), $path);
    }

    public static function refused(): array
    {
        return array_map(fn ($p) => [$p], [
            '../evil.php', 'app/../../evil.php', 'app/../.env', '/etc/passwd', 'C:/windows/x.php', 'app\\Models\\User.php', "app/x\0.php",
            '', 'app//double.php', 'app/./x.php', '.env', 'storage/logs/laravel.log', 'storage/app/data.txt',
            'public/uploads/logo.png', 'public/storage/file', 'public/hot', 'bootstrap/cache/config.php',
            'database/database.sqlite', 'vendor/foo/.git/config', '.git/config', 'tests/Feature/X.php', 'node_modules/a/b.js',
            'unknown/file.php', 'index.php',
        ]);
    }

    public function test_normalize_returns_the_clean_path_or_null(): void
    {
        $this->assertSame('app/Models/User.php', PathPolicy::normalize('app/Models/User.php'));
        $this->assertNull(PathPolicy::normalize('app/../x'));
    }
}
