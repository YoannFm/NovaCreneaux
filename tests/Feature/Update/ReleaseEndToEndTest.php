<?php

namespace Tests\Feature\Update;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsReleases;
use Tests\TestCase;

require_once __DIR__ . '/../../../scripts/release/lib.php';

/** Chaine complete : les scripts de publication fabriquent l'archive, le gestionnaire du site l'installe. */
class ReleaseEndToEndTest extends TestCase
{
    use BuildsReleases, RefreshDatabase;

    private string $sourceDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRelease();

        // Depot source de la version 1.1.0 : ce que le workflow GitHub construirait
        $this->sourceDir = $this->workDir . '/source';
        foreach (['app', 'config', 'tests', 'storage/logs'] as $dir) {
            mkdir("{$this->sourceDir}/{$dir}", 0755, true);
        }
        file_put_contents("{$this->sourceDir}/app/Old.php", 'version 1.1.0');
        file_put_contents("{$this->sourceDir}/app/New.php", 'nouveau fichier');
        file_put_contents("{$this->sourceDir}/config/nova.php", "<?php return ['version' => '1.1.0'];");
        file_put_contents("{$this->sourceDir}/composer.json", '{"require": {"php": "^8.3"}}');
        file_put_contents("{$this->sourceDir}/.env", 'APP_KEY=ne-jamais-livrer');
        file_put_contents("{$this->sourceDir}/tests/Secret.php", 'hors archive');
        file_put_contents("{$this->sourceDir}/storage/logs/laravel.log", 'hors archive');
    }

    protected function tearDown(): void
    {
        $this->tearDownRelease();
        parent::tearDown();
    }

    public function test_a_release_built_and_signed_by_the_scripts_installs_and_rolls_back(): void
    {
        $built = release_build($this->sourceDir, "{$this->workDir}/dist", '1.1.0', ['app/Removed.php']);
        $signature = release_sign($built['path'], $this->secretKey);
        $this->assertTrue(release_verify($built['path'], $this->publicKey));
        $this->fakeGithub($built['path'], $signature);

        $manager = $this->manager();
        $manager->download();
        $manager->install();
        $manager->finalize();

        $this->assertSame('version 1.1.0', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertSame('nouveau fichier', file_get_contents("{$this->siteDir}/app/New.php"));
        $this->assertFileDoesNotExist("{$this->siteDir}/app/Removed.php");
        $this->assertSame('APP_KEY=secret', file_get_contents("{$this->siteDir}/.env"));
        $this->assertFileDoesNotExist("{$this->siteDir}/tests/Secret.php");
        $this->assertSame('donnees', file_get_contents("{$this->siteDir}/storage/app/data.txt"));

        $manager->restore();

        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertFileDoesNotExist("{$this->siteDir}/app/New.php");
        $this->assertSame('sera supprime', file_get_contents("{$this->siteDir}/app/Removed.php"));
    }

    public function test_building_refuses_a_version_that_differs_from_the_code(): void
    {
        $this->expectException(\RuntimeException::class);

        release_build($this->sourceDir, "{$this->workDir}/dist", '2.0.0');
    }

    public function test_the_release_scripts_run_in_a_plain_php_process_with_the_real_config_file(): void
    {
        // Reproduit le workflow : build, sign et verify en PHP seul (sans Laravel), avec les vrais scripts,
        // le vrai PathPolicy et le vrai config/nova.php (qui utilise env() et storage_path()).
        $real = require base_path('config/nova.php');
        $project = "{$this->workDir}/project";
        foreach (['scripts/release', 'app/Services/Update', 'config'] as $dir) {
            mkdir("{$project}/{$dir}", 0755, true);
        }
        foreach (glob(base_path('scripts/release/*.php')) as $script) {
            copy($script, "{$project}/scripts/release/" . basename($script));
        }
        copy(base_path('app/Services/Update/PathPolicy.php'), "{$project}/app/Services/Update/PathPolicy.php");
        file_put_contents("{$project}/composer.json", '{"require": {"php": "^8.3"}}');
        file_put_contents("{$project}/config/nova.php", str_replace($real['update_public_key'], $this->publicKey, file_get_contents(base_path('config/nova.php'))));
        $zip = "dist/novacreneaux-{$real['version']}.zip";

        $build = $this->runInProject($project, ['scripts/release/build.php', "--version={$real['version']}", '--output=dist']);
        $unsigned = $this->runInProject($project, ['scripts/release/verify.php', $zip]);
        $sign = $this->runInProject($project, ['scripts/release/sign.php', $zip], ['UPDATE_SIGNING_KEY' => $this->secretKey]);
        $verify = $this->runInProject($project, ['scripts/release/verify.php', $zip]);

        $this->assertSame(0, $build->getExitCode(), $build->getErrorOutput() . $build->getOutput());
        $this->assertNotSame(0, $unsigned->getExitCode(), 'Une archive non signee ne doit pas etre validee.');
        $this->assertSame(0, $sign->getExitCode(), $sign->getErrorOutput() . $sign->getOutput());
        $this->assertSame(0, $verify->getExitCode(), $verify->getErrorOutput() . $verify->getOutput());
        $this->assertStringContainsString('Signature valide', $verify->getOutput());
    }

    private function runInProject(string $project, array $arguments, array $environment = []): Process
    {
        $process = new Process([PHP_BINARY, ...$arguments], $project, $environment);
        $process->run();

        return $process;
    }
}
