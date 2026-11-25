<?php

namespace Tests\Feature\Update;

use App\Models\ActivityLog;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Services\Update\UpdateException;
use App\Services\Update\UpdateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsReleases;
use Tests\TestCase;

/** Migrations verifiees a la finalisation, et retour arriere avec ou sans la base. */
class UpdateRollbackTest extends TestCase
{
    use BuildsReleases, RefreshDatabase;

    private UpdateManager $updates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRelease();
        Cache::forget('update_check');
        [$zip, $signature] = $this->buildZip(['app/Old.php' => 'nouveau'], ['deleted' => []]);
        $this->fakeGithub($zip, $signature, '1.1.0');
        $this->actingAs(User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]));
        $this->updates = $this->manager();
        $this->updates->download();
    }

    protected function tearDown(): void
    {
        $this->tearDownRelease();
        parent::tearDown();
    }

    private function maintenance(): string
    {
        Cache::forget('setting_maintenance_mode');

        return (string) setting('maintenance_mode', '0');
    }

    /** La prochaine commande "migrate" echoue une fois, puis passe. */
    private function failMigrationsOnce(): void
    {
        Artisan::shouldReceive('call')->with('migrate', ['--force' => true])->andReturn(1, 0);
        Artisan::shouldReceive('call')->with('optimize:clear')->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('SQLSTATE[42S01]: table déjà existante', '');
    }

    public function test_a_failed_migration_keeps_the_site_in_maintenance_with_the_error_and_can_be_retried(): void
    {
        $this->updates->install();
        $this->failMigrationsOnce();

        try {
            $this->updates->finalize();
            $this->fail('UpdateException attendue');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('table déjà existante', $e->getMessage());
            $this->assertStringContainsString('Le site reste en maintenance', $e->getMessage());
        }

        $this->assertSame('1', $this->maintenance());
        $this->assertStringContainsString('table déjà existante', $this->updates->pending()['error']);
        $this->assertTrue(ActivityLog::where('action', 'Mise à jour : échec des migrations')->exists());

        $this->updates->finalize();

        $this->assertNull($this->updates->pending());
        $this->assertSame('0', $this->maintenance());
    }

    public function test_the_page_shows_the_error_with_retry_and_rollback(): void
    {
        $this->updates->install();
        $this->failMigrationsOnce();
        $this->app->instance(UpdateManager::class, $this->updates);
        $this->postJson(route('admin.update.finalize'))->assertStatus(422);

        $this->get(route('admin.update.index'))->assertOk()
            ->assertSee("n'a pas abouti", false)->assertSee('table déjà existante')
            ->assertSee('Réessayer')->assertSee('Revenir à la version précédente')
            ->assertSee('id="restore-database"', false);
    }

    public function test_rollback_without_the_database_keeps_data_entered_since_the_update(): void
    {
        $this->updates->install();
        $this->updates->finalize();
        Resource::create(['name' => 'Ajoutée après la mise à jour', 'is_active' => true]);

        $result = $this->updates->restore();

        $this->assertFalse($result['database_restored']);
        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertTrue(Resource::where('name', 'Ajoutée après la mise à jour')->exists());
        $this->assertTrue(ActivityLog::where('action', 'Mise à jour annulée')->where('context', 'like', '%fichiers seulement%')->exists());
    }

    public function test_rollback_with_the_database_brings_back_the_state_before_the_update(): void
    {
        Resource::create(['name' => "Salle d'avant ; avec -- des pièges\net \\ une ligne", 'is_active' => true]);
        $this->updates->install();
        $this->updates->finalize();
        Resource::create(['name' => 'Ajoutée après la mise à jour', 'is_active' => true]);
        Schema::create('table_de_la_nouvelle_version', fn ($table) => $table->id());

        $result = $this->updates->restore(true);

        $this->assertTrue($result['database_restored']);
        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertSame(["Salle d'avant ; avec -- des pièges\net \\ une ligne"], Resource::pluck('name')->all());
        $this->assertFalse(Schema::hasTable('table_de_la_nouvelle_version'), 'une table creee par la mise a jour est retiree');
        $this->assertSame('0', $this->maintenance());
        $this->assertNull($this->updates->rollbackDatabaseBackup(), 'la mise a jour annulee est oubliee');
        $this->assertTrue(ActivityLog::where('action', 'Mise à jour annulée')->where('context', 'like', '%fichiers et base de données%')->exists());
    }

    public function test_rollback_with_the_database_is_refused_when_no_backup_was_taken(): void
    {
        $this->updates->install(false);
        $this->updates->finalize();

        try {
            $this->updates->restore(true);
            $this->fail('UpdateException attendue');
        } catch (UpdateException $e) {
            $this->assertStringContainsString("rien n'a été modifié", $e->getMessage());
        }

        $this->assertSame('nouveau', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertNotNull($this->updates->latestBackup());
    }

    public function test_the_endpoint_passes_the_database_choice(): void
    {
        $this->updates->install();
        $this->updates->finalize();
        Resource::create(['name' => 'Ajoutée après la mise à jour', 'is_active' => true]);
        $this->app->instance(UpdateManager::class, $this->updates);

        $this->postJson(route('admin.update.restore'), ['restore_database' => '1'])->assertOk()
            ->assertJsonPath('message', 'Fichiers et base de données de la version précédente restaurés.');

        $this->assertFalse(Resource::where('name', 'Ajoutée après la mise à jour')->exists());
    }
}
