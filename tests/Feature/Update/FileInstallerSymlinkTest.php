<?php

namespace Tests\Feature\Update;

use App\Services\Update\FileInstaller;
use App\Services\Update\UpdateException;
use Illuminate\Support\Facades\File;
use Tests\Support\BuildsReleases;
use Tests\TestCase;

/** Un dossier ou un fichier du serveur remplace par un lien symbolique ne doit jamais faire ecrire hors du site. */
class FileInstallerSymlinkTest extends TestCase
{
    use BuildsReleases;

    private string $outside;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRelease();
        $this->outside = $this->workDir . '/outside';
        mkdir($this->outside, 0755, true);
        file_put_contents("{$this->outside}/victim.txt", 'intact');
    }

    protected function tearDown(): void
    {
        $this->tearDownRelease();
        parent::tearDown();
    }

    private function backupPath(): string
    {
        return "{$this->storageDir}/backups/test.zip";
    }

    private function install(array $files, array $deleted = []): array
    {
        [$zip] = $this->buildZip($files);

        return (new FileInstaller)->install($zip, $this->siteDir, $this->backupPath(), $deleted);
    }

    private function assertOutsideUntouched(): void
    {
        $this->assertSame(['victim.txt'], array_values(array_diff(scandir($this->outside), ['.', '..'])));
        $this->assertSame('intact', file_get_contents("{$this->outside}/victim.txt"));
    }

    private function refuses(callable $action): void
    {
        try {
            $action();
            $this->fail('UpdateException attendue');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('lien symbolique', $e->getMessage());
        }
    }

    public function test_a_directory_replaced_by_a_symlink_is_refused_before_anything_is_written(): void
    {
        File::deleteDirectory("{$this->siteDir}/config");
        symlink($this->outside, "{$this->siteDir}/config");

        $this->refuses(fn () => $this->install(['app/Old.php' => 'nouveau', 'config/evil.php' => '<?php // x']));

        $this->assertOutsideUntouched();
        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"), 'rien n\'est ecrit, meme hors du lien');
        $this->assertFileDoesNotExist($this->backupPath());
    }

    public function test_a_nested_symlinked_directory_is_refused(): void
    {
        mkdir("{$this->siteDir}/app/Http", 0755, true);
        symlink($this->outside, "{$this->siteDir}/app/Http/Controllers");

        $this->refuses(fn () => $this->install(['app/Http/Controllers/Evil.php' => 'x']));

        $this->assertOutsideUntouched();
    }

    public function test_a_symlinked_file_is_refused_and_its_target_is_not_read_into_the_backup_or_overwritten(): void
    {
        unlink("{$this->siteDir}/app/Old.php");
        symlink("{$this->outside}/victim.txt", "{$this->siteDir}/app/Old.php");

        $this->refuses(fn () => $this->install(['app/Old.php' => 'ecrase']));

        $this->assertSame('intact', file_get_contents("{$this->outside}/victim.txt"));
        $this->assertTrue(is_link("{$this->siteDir}/app/Old.php"));
    }

    public function test_deleting_through_a_symlinked_directory_is_refused(): void
    {
        File::deleteDirectory("{$this->siteDir}/config");
        symlink($this->outside, "{$this->siteDir}/config");

        $this->refuses(fn () => $this->install(['app/Old.php' => 'nouveau'], ['config/victim.txt']));

        $this->assertOutsideUntouched();
    }

    public function test_restoring_through_a_symlink_is_refused(): void
    {
        $this->install(['app/Old.php' => 'nouveau', 'config/new.php' => 'x']);
        File::deleteDirectory("{$this->siteDir}/config");
        symlink($this->outside, "{$this->siteDir}/config");
        file_put_contents("{$this->outside}/new.php", 'hors du site');

        $this->refuses(fn () => (new FileInstaller)->restore($this->backupPath(), $this->siteDir));

        $this->assertSame('hors du site', file_get_contents("{$this->outside}/new.php"), 'la restauration ne supprime rien hors du site');
    }

    public function test_the_base_directory_itself_may_be_a_symlink_and_missing_directories_are_created(): void
    {
        $link = $this->workDir . '/current';
        symlink($this->siteDir, $link);
        [$zip] = $this->buildZip(['app/Old.php' => 'nouveau', 'app/Deep/Er/New.php' => 'cree']);

        (new FileInstaller)->install($zip, $link, $this->backupPath(), []);

        $this->assertSame('nouveau', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertSame('cree', file_get_contents("{$this->siteDir}/app/Deep/Er/New.php"));
    }

    public function test_a_clean_install_is_unaffected(): void
    {
        $counts = $this->install(['app/Old.php' => 'nouveau', 'app/New.php' => 'ajoute']);

        $this->assertSame(['replaced' => 1, 'added' => 1, 'deleted' => 0], $counts);
    }
}
