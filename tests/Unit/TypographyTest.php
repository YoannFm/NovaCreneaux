<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Coherence typographique : certains caracteres de ponctuation sont a uniformiser dans le code du projet. */
class TypographyTest extends TestCase
{
    /** Dossiers ecrits pour le projet (les bibliotheques de public/vendor et vendor ne sont pas concernees). */
    private const DIRECTORIES = ['app', 'config', 'database', 'docs', 'lang', 'public/assets', 'resources', 'routes', 'scripts', 'tests'];

    private const CHARACTERS = ["\u{2014}", "\u{2013}"];

    public function test_project_files_use_uniform_punctuation(): void
    {
        $root = dirname(__DIR__, 2);
        $found = [];
        foreach (self::DIRECTORIES as $directory) {
            if (!is_dir("{$root}/{$directory}")) {
                continue;
            }
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$directory}", RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (!preg_match('/\.(php|js|css|json|md|yml|yaml|html|py|sh|txt)$/', $file->getFilename())) {
                    continue;
                }
                $content = file_get_contents($file->getPathname());
                foreach (self::CHARACTERS as $character) {
                    if (str_contains($content, $character)) {
                        $found[] = substr($file->getPathname(), strlen($root) + 1);
                        break;
                    }
                }
            }
        }

        $this->assertSame([], $found, 'Ponctuation a uniformiser');
    }
}
