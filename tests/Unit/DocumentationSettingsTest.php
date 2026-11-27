<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * La documentation PDF (docs/build) decrit chaque reglage du site et refuse de se construire si la liste n'est plus
 * a jour. Ce test fait le meme controle a chaque modification, sans attendre la construction de la documentation.
 */
class DocumentationSettingsTest extends TestCase
{
    public function test_every_setting_of_the_site_is_described_in_the_documentation(): void
    {
        // Le script lit la documentation de l'API avec PyYAML : environnement de docs/build s'il existe, sinon python3.
        // Les deux commandes utilisent le meme interprete avec les memes options (-I : rien du dossier courant).
        $build = dirname(__DIR__, 2) . '/docs/build';
        $python = is_executable("{$build}/.venv/bin/python") ? escapeshellarg("{$build}/.venv/bin/python") : 'python3';
        exec("{$python} -I -c 'import yaml' 2>&1", $ignored, $missing);
        if ($missing !== 0) {
            $this->markTestSkipped('python3 ou PyYAML absent : controle fait par la construction de la documentation.');
        }

        $dir = escapeshellarg($build);
        exec("cd {$dir} && {$python} -I -c 'import sys; sys.path.insert(0, \".\"); import sources; sources.check_settings()' 2>&1", $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));
    }
}
