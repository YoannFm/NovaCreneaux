<?php

namespace Tests\Feature;

use App\Services\InstallationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstallationLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(app(InstallationState::class)->lockPath());
    }

    protected function tearDown(): void
    {
        @unlink(app(InstallationState::class)->lockPath());
        parent::tearDown();
    }

    public function test_a_fresh_site_is_not_installed(): void
    {
        $this->assertFalse(app(InstallationState::class)->isInstalled());
    }

    public function test_the_lock_file_keeps_the_site_installed_when_the_database_fails(): void
    {
        $state = app(InstallationState::class);
        file_put_contents($state->lockPath(), 'x');
        DB::statement('DROP TABLE installation');

        $this->assertTrue($state->isInstalled());
    }

    public function test_a_missing_installation_table_means_not_installed_when_there_is_no_lock(): void
    {
        DB::statement('DROP TABLE installation');

        $this->assertFalse(app(InstallationState::class)->isInstalled());
    }

    public function test_an_installed_database_creates_the_lock_for_next_outages(): void
    {
        $state = app(InstallationState::class);
        DB::table('installation')->insert(['completed' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertTrue($state->isInstalled());
        $this->assertFileExists($state->lockPath());
    }

    public function test_install_routes_are_closed_once_installed(): void
    {
        file_put_contents(app(InstallationState::class)->lockPath(), 'x');

        $this->get('/install')->assertRedirect('/');
        $this->post('/install/db-config', [
            'db_host' => 'attacker.example', 'db_port' => 3306, 'db_database' => 'x', 'db_username' => 'x',
        ])->assertRedirect('/');
    }

    public function test_an_existing_administrator_keeps_the_site_installed_when_the_lock_and_the_row_are_gone(): void
    {
        $state = app(InstallationState::class);
        \App\Models\User::factory()->create(['is_admin' => true]);

        $this->assertTrue($state->isInstalled());
        $this->assertFileExists($state->lockPath(), 'le verrou est recree');
    }

    public function test_pupils_alone_do_not_mean_installed(): void
    {
        \App\Models\User::factory()->count(3)->create(['is_admin' => false]);

        $this->assertFalse(app(InstallationState::class)->isInstalled());
    }

    public function test_the_environment_flag_keeps_the_site_installed_without_lock_and_without_asking_the_database(): void
    {
        config(['app.installed' => true]);
        DB::shouldReceive('table')->never();

        $this->assertTrue(app(InstallationState::class)->isInstalled());
    }

    public function test_a_deleted_lock_with_the_database_reachable_is_rebuilt_from_the_installation_row(): void
    {
        $state = app(InstallationState::class);
        DB::table('installation')->insert(['completed' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue($state->isInstalled());
        unlink($state->lockPath());

        $this->assertTrue($state->isInstalled());
        $this->assertFileExists($state->lockPath());
    }
}
