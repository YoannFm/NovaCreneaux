<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\BackupException;
use App\Services\Backup\BackupManager;
use App\Services\Backup\DatabaseDumper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupManagerTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/nc-backups-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function manager(?DatabaseDumper $dumper = null): BackupManager
    {
        return new BackupManager($dumper ?? new DatabaseDumper(), $this->dir);
    }

    private function createAt(string $moment, string $trigger, ?BackupManager $manager = null): array
    {
        Carbon::setTestNow($moment);

        return ($manager ?? $this->manager())->create($trigger);
    }

    public function test_a_backup_is_a_private_complete_file_named_after_its_moment_and_type(): void
    {
        $backup = $this->createAt('2026-10-26 03:00:05', BackupManager::AUTOMATIC);

        $this->assertMatchesRegularExpression('/^nova-20261026-030005-auto-[a-f0-9]{16}\.sql\.gz$/', $backup['name']);
        $this->assertSame(BackupManager::AUTOMATIC, $backup['trigger']);
        $this->assertSame('2026-10-26 03:00:05', $backup['created_at']->format('Y-m-d H:i:s'));
        $this->assertGreaterThan(0, $backup['size']);
        $this->assertTrue(DatabaseDumper::isComplete("{$this->dir}/{$backup['name']}"));
        $this->assertSame('0600', substr(sprintf('%o', fileperms("{$this->dir}/{$backup['name']}")), -4));
        $this->assertSame([], glob("{$this->dir}/*.partial"));
    }

    public function test_the_list_shows_the_most_recent_first_and_ignores_foreign_files(): void
    {
        $old = $this->createAt('2026-10-24 03:00:00', BackupManager::AUTOMATIC);
        $recent = $this->createAt('2026-10-26 10:00:00', BackupManager::MANUAL);
        file_put_contents("{$this->dir}/notes.txt", 'x');
        file_put_contents("{$this->dir}/nova-evil.sql.gz", 'x');

        $names = array_column($this->manager()->list(), 'name');

        $this->assertSame([$recent['name'], $old['name']], $names);
    }

    public function test_only_a_listed_backup_name_resolves_to_a_path(): void
    {
        $backup = $this->createAt('2026-10-26 03:00:00', BackupManager::MANUAL);
        $manager = $this->manager();

        $this->assertSame("{$this->dir}/{$backup['name']}", $manager->path($backup['name']));

        foreach (['../.env', '..%2F..%2Fetc%2Fpasswd', 'nova-20261026-030000-manual-aaaaaaaaaaaaaaaa.sql.gz', "{$backup['name']}/../x", 'notes.txt', ''] as $name) {
            try {
                $manager->path($name);
                $this->fail("Le nom '{$name}' ne doit pas etre accepte.");
            } catch (BackupException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_backup_can_be_deleted(): void
    {
        $backup = $this->createAt('2026-10-26 03:00:00', BackupManager::MANUAL);

        $this->manager()->delete($backup['name']);

        $this->assertSame([], $this->manager()->list());
    }

    public function test_pruning_keeps_the_most_recent_automatic_backups_and_never_touches_manual_ones(): void
    {
        $manager = $this->manager();
        $a1 = $this->createAt('2026-10-21 03:00:00', BackupManager::AUTOMATIC, $manager);
        $manual = $this->createAt('2026-10-22 09:00:00', BackupManager::MANUAL, $manager);
        $a2 = $this->createAt('2026-10-23 03:00:00', BackupManager::AUTOMATIC, $manager);
        $a3 = $this->createAt('2026-10-24 03:00:00', BackupManager::AUTOMATIC, $manager);

        $deleted = $manager->pruneAutomatic(2);

        $this->assertSame(1, $deleted);
        $this->assertEqualsCanonicalizing([$a2['name'], $a3['name'], $manual['name']], array_column($manager->list(), 'name'));
        $this->assertFileDoesNotExist("{$this->dir}/{$a1['name']}");
    }

    public function test_a_failing_dump_leaves_no_file_behind_and_reports_a_readable_error(): void
    {
        $failing = new class extends DatabaseDumper {
            public function dump(string $path, ?string $connection = null): void
            {
                file_put_contents($path, 'debut');
                throw new \RuntimeException('SQLSTATE secret : mot de passe de la base');
            }
        };

        try {
            $this->manager($failing)->create(BackupManager::MANUAL);
            $this->fail('Une BackupException etait attendue.');
        } catch (BackupException $e) {
            $this->assertStringNotContainsString('SQLSTATE', $e->getMessage());
            $this->assertStringContainsString('journal', $e->getMessage());
        }

        $this->assertSame([], glob("{$this->dir}/*"));
    }

    public function test_an_incomplete_dump_is_rejected_and_removed(): void
    {
        $truncated = new class extends DatabaseDumper {
            public function dump(string $path, ?string $connection = null): void
            {
                file_put_contents($path, gzencode("CREATE TABLE t (id int);\n"));
            }
        };

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('incomplet');

        try {
            $this->manager($truncated)->create(BackupManager::MANUAL);
        } finally {
            $this->assertSame([], glob("{$this->dir}/*"));
        }
    }

    public function test_two_backups_cannot_run_at_the_same_time(): void
    {
        $lock = Cache::lock('database-backup', 60);
        $lock->get();

        try {
            $this->manager()->create(BackupManager::MANUAL);
            $this->fail('Une BackupException etait attendue.');
        } catch (BackupException $e) {
            $this->assertStringContainsString('déjà en cours', $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function test_an_unknown_backup_type_is_a_programming_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager()->create('weekly');
    }
}
