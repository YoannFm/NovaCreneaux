<?php

namespace Tests\Feature\Update;

use App\Services\Update\FileInstaller;
use App\Services\Update\UpdateException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsReleases;
use Tests\TestCase;
use ZipArchive;

class FileInstallerTest extends TestCase
{
    use BuildsReleases;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRelease();
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

    public function test_files_are_replaced_added_and_removed_with_a_backup(): void
    {
        [$zip] = $this->buildZip(['app/Old.php' => 'nouveau', 'app/New.php' => 'ajoute', 'config/extra.php' => 'cfg']);

        $counts = (new FileInstaller)->install($zip, $this->siteDir, $this->backupPath(), ['app/Removed.php']);

        $this->assertSame('nouveau', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertSame('ajoute', file_get_contents("{$this->siteDir}/app/New.php"));
        $this->assertFileDoesNotExist("{$this->siteDir}/app/Removed.php");
        $this->assertSame(['replaced' => 1, 'added' => 2, 'deleted' => 1], $counts);

        $backup = new ZipArchive();
        $backup->open($this->backupPath());
        $this->assertSame('ancien', $backup->getFromName('app/Old.php'));
        $this->assertSame('sera supprime', $backup->getFromName('app/Removed.php'));
        $this->assertFalse($backup->getFromName('app/New.php'));
        $this->assertEqualsCanonicalizing(['app/New.php', 'config/extra.php'], json_decode($backup->getFromName('backup.json'), true)['added']);
    }

    public function test_protected_files_are_never_touched(): void
    {
        [$zip] = $this->buildZip(['app/Old.php' => 'nouveau']);

        (new FileInstaller)->install($zip, $this->siteDir, $this->backupPath(), ['.env', 'storage/app/data.txt', '../outside.txt']);

        $this->assertSame('APP_KEY=secret', file_get_contents("{$this->siteDir}/.env"));
        $this->assertSame('donnees', file_get_contents("{$this->siteDir}/storage/app/data.txt"));
    }

    #[DataProvider('forbiddenEntries')]
    public function test_an_archive_with_a_forbidden_path_is_refused_before_anything_is_written(string $entry): void
    {
        [$zip] = $this->buildZip(['app/Old.php' => 'nouveau', $entry => 'piege']);

        try {
            (new FileInstaller)->install($zip, $this->siteDir, $this->backupPath(), []);
            $this->fail('Archive acceptee');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('chemin interdit', $e->getMessage());
        }

        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"), 'Rien ne doit etre ecrit');
        $this->assertSame('APP_KEY=secret', file_get_contents("{$this->siteDir}/.env"));
        $this->assertFileDoesNotExist($this->workDir . '/evil.php');
    }

    public static function forbiddenEntries(): array
    {
        return array_map(fn ($p) => [$p], ['../evil.php', 'app/../../evil.php', '.env', 'storage/app/x.txt', '/etc/cron.d/evil', 'public/uploads/shell.php', 'unknown/file.php']);
    }

    public function test_a_symbolic_link_entry_is_refused(): void
    {
        [$zip] = $this->buildZip(['app/Old.php' => 'nouveau']);
        $archive = new ZipArchive();
        $archive->open($zip);
        $archive->addFromString('app/link.php', '/etc/passwd');
        $archive->setExternalAttributesName('app/link.php', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $archive->close();

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('lien symbolique');
        (new FileInstaller)->install($zip, $this->siteDir, $this->backupPath(), []);
    }

    public function test_a_failure_midway_restores_the_original_files(): void
    {
        // "app/Old.php" est un fichier : creer "app/Old.php/Sub.php" echoue apres l'ecriture de app/A.php
        [$zip] = $this->buildZip(['app/A.php' => 'a', 'app/Old.php/Sub.php' => 'impossible']);

        try {
            (new FileInstaller)->install($zip, $this->siteDir, $this->backupPath(), ['app/Removed.php']);
            $this->fail('Installation acceptee');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('remis en place', $e->getMessage());
        }

        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertSame('sera supprime', file_get_contents("{$this->siteDir}/app/Removed.php"));
        $this->assertFileDoesNotExist("{$this->siteDir}/app/A.php");
    }

    public function test_restore_puts_the_previous_files_back(): void
    {
        [$zip] = $this->buildZip(['app/Old.php' => 'nouveau', 'app/New.php' => 'ajoute']);
        $installer = new FileInstaller;
        $installer->install($zip, $this->siteDir, $this->backupPath(), ['app/Removed.php']);

        $result = $installer->restore($this->backupPath(), $this->siteDir);

        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertSame('sera supprime', file_get_contents("{$this->siteDir}/app/Removed.php"));
        $this->assertFileDoesNotExist("{$this->siteDir}/app/New.php");
        $this->assertSame(2, $result['restored']);
    }

    public function test_a_tampered_backup_cannot_write_outside_the_site(): void
    {
        $path = $this->backupPath();
        @mkdir(dirname($path), 0755, true);
        $backup = new ZipArchive();
        $backup->open($path, ZipArchive::CREATE);
        $backup->addFromString('../evil.php', 'piege');
        $backup->addFromString('backup.json', json_encode(['added' => ['.env']]));
        $backup->close();

        try {
            (new FileInstaller)->restore($path, $this->siteDir);
            $this->fail('Sauvegarde acceptee');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('chemin interdit', $e->getMessage());
        }
        $this->assertSame('APP_KEY=secret', file_get_contents("{$this->siteDir}/.env"));
        $this->assertFileDoesNotExist($this->workDir . '/evil.php');
    }

    public function test_the_manifest_is_read_and_validated(): void
    {
        [$zip] = $this->buildZip(['app/A.php' => 'a'], ['version' => '2.0.0']);
        $this->assertSame('2.0.0', (new FileInstaller)->manifest($zip)['version']);

        $archive = new ZipArchive();
        $archive->open($zip);
        $archive->deleteName('release.json');
        $archive->close();
        $this->expectException(UpdateException::class);
        (new FileInstaller)->manifest($zip);
    }

    public function test_an_empty_archive_is_refused(): void
    {
        [$zip] = $this->buildZip([]);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('aucun fichier');
        (new FileInstaller)->install($zip, $this->siteDir, $this->backupPath(), []);
    }
}
