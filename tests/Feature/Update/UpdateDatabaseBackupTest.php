<?php

namespace Tests\Feature\Update;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Backup\BackupException;
use App\Services\Backup\BackupManager;
use App\Services\Backup\DatabaseDumper;
use App\Services\Update\UpdateException;
use App\Services\Update\UpdateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\Support\BuildsReleases;
use Tests\TestCase;

/** La base est sauvegardee juste avant de toucher aux fichiers. */
class UpdateDatabaseBackupTest extends TestCase
{
    use BuildsReleases, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRelease();
        Cache::forget('update_check');
        [$zip, $signature] = $this->buildZip(['app/Old.php' => 'nouveau'], ['deleted' => []]);
        $this->fakeGithub($zip, $signature, '1.1.0');
    }

    protected function tearDown(): void
    {
        $this->tearDownRelease();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);
    }

    private function failingBackups(): BackupManager
    {
        return $this->databaseBackups(new class extends DatabaseDumper {
            public function dump(string $path, ?string $connection = null): void
            {
                throw new BackupException('disque plein');
            }
        });
    }

    public function test_installing_takes_a_database_backup_first_and_logs_it(): void
    {
        $this->actingAs($this->admin());
        $updates = $this->manager();
        $updates->download();

        $updates->install();

        $backups = $this->databaseBackups()->list();
        $this->assertCount(1, $backups);
        $this->assertSame(BackupManager::BEFORE_UPDATE, $backups[0]['trigger']);
        $this->assertSame('nouveau', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertTrue(ActivityLog::where('action', 'Sauvegarde de la base avant mise à jour')->where('context', $backups[0]['name'])->exists());
    }

    public function test_the_backup_can_be_skipped_explicitly(): void
    {
        $updates = $this->manager();
        $updates->download();

        $updates->install(false);

        $this->assertCount(0, $this->databaseBackups()->list());
        $this->assertSame('nouveau', file_get_contents("{$this->siteDir}/app/Old.php"));
    }

    public function test_a_failed_backup_stops_everything_and_leaves_the_site_untouched(): void
    {
        $updates = $this->manager($this->failingBackups());
        $updates->download();

        try {
            $updates->install();
            $this->fail('UpdateException attendue');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('La sauvegarde de la base a échoué (disque plein)', $e->getMessage());
        }

        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertNull($updates->pending());
        $this->assertSame('0', setting('maintenance_mode', '0'), 'la maintenance est retiree');
        $this->assertNull($updates->latestBackup(), 'aucune sauvegarde de fichiers : rien n\'a ete remplace');

        $updates->install(false);
        $this->assertSame('nouveau', file_get_contents("{$this->siteDir}/app/Old.php"));
    }

    public function test_the_admin_page_offers_the_checkbox_and_the_endpoint_honours_it(): void
    {
        $this->app->bind(UpdateManager::class, fn () => $this->manager());
        $this->actingAs($this->admin());
        $this->postJson(route('admin.update.download'))->assertOk();

        $this->get(route('admin.update.index'))->assertSee('id="backup-database"', false)->assertSee('Sauvegarder la base de données avant');
        $this->postJson(route('admin.update.install'), ['backup_database' => '0'])->assertOk();

        $this->assertCount(0, $this->databaseBackups()->list());
    }

    public function test_only_the_last_three_pre_update_backups_are_kept_and_other_kinds_are_left_alone(): void
    {
        $backups = $this->databaseBackups();
        $manual = $backups->create(BackupManager::MANUAL);
        $oldest = $backups->create(BackupManager::BEFORE_UPDATE);
        $oldestPath = $backups->path($oldest['name']);
        touch($oldestPath, time() - 100);
        File::move($oldestPath, dirname($oldestPath) . '/' . str_replace(now()->format('Ymd'), now()->subDays(3)->format('Ymd'), $oldest['name']));
        for ($i = 0; $i < 3; $i++) {
            $backups->create(BackupManager::BEFORE_UPDATE);
        }

        $this->assertSame(1, $backups->pruneBeforeUpdate());
        $kinds = array_count_values(array_column($backups->list(), 'trigger'));
        $this->assertSame(3, $kinds[BackupManager::BEFORE_UPDATE]);
        $this->assertSame(1, $kinds[BackupManager::MANUAL]);
        $this->assertNotNull($backups->path($manual['name']));
    }

    public function test_the_backup_list_labels_pre_update_backups(): void
    {
        $this->databaseBackups()->create(BackupManager::BEFORE_UPDATE);
        config(['nova.backups.directory' => $this->workDir . '/database-backups']);

        $this->actingAs($this->admin())->get(route('admin.update.index'))->assertSee('Avant mise à jour');
    }

    /** Les autres tests construisent le gestionnaire a la main : celui-ci verifie le cablage reel du conteneur. */
    public function test_the_real_container_wires_the_database_backups_into_the_update_manager(): void
    {
        $updates = $this->app->make(UpdateManager::class);

        $property = new \ReflectionProperty($updates, 'databaseBackups');
        $this->assertInstanceOf(BackupManager::class, $property->getValue($updates));
    }
}
