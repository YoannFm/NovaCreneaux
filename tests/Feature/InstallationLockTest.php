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
}
