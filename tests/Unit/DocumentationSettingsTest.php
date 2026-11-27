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
        // Le script lit la documentation de l'API avec PyYAML
        exec('python3 -c "import yaml" 2>&1', $ignored, $missing);
        if ($missing !== 0) {
            $this->markTestSkipped('python3 ou PyYAML absent : controle fait par la construction de la documentation.');
        }

        $dir = escapeshellarg(dirname(__DIR__, 2) . '/docs/build');
        exec("cd {$dir} && python3 -I -c 'import sys; sys.path.insert(0, \".\"); import sources; sources.check_settings()' 2>&1", $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));
    }
}
