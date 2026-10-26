<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Une classe importee mais absente ne se voit qu'a l'execution, souvent apres l'action (page creee puis erreur 500).
 * Ce test verifie que chaque `use App\...` de l'application designe un fichier qui existe.
 */
class AppImportsTest extends TestCase
{
    public function test_every_imported_app_class_exists(): void
    {
        $root = dirname(__DIR__, 2) . '/app';
        $missing = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all('/^use\s+(App\\\\[A-Za-z0-9_\\\\]+?)(?:\s+as\s+\w+)?;/m', file_get_contents($file->getPathname()), $imports);
            foreach ($imports[1] as $class) {
                $path = $root . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
                if (! is_file($path)) {
                    $missing[] = $class . ' (dans ' . substr($file->getPathname(), strlen($root) + 1) . ')';
                }
            }
        }

        $this->assertSame([], $missing, "Classes importées introuvables :\n" . implode("\n", $missing));
    }
}
