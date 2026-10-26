<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\InstallationState;
use App\Support\EnvFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** L'assistant se verrouille des la creation de l'administrateur, et ne permet jamais d'en creer un second. */
class InstallFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();
        // Le middleware considere toujours le site installe en environnement de test : on teste ici les controles du controleur
        $this->withoutMiddleware(\App\Http\Middleware\CheckInstallation::class);
        $this->envPath = sys_get_temp_dir() . '/nc-env-' . bin2hex(random_bytes(4));
        file_put_contents($this->envPath, "APP_NAME=NovaCreneaux\nAPP_INSTALLED=false\n");
        $this->app->bind(EnvFile::class, fn () => new EnvFile($this->envPath));
        @unlink(app(InstallationState::class)->lockPath());
    }

    protected function tearDown(): void
    {
        @unlink($this->envPath);
        @unlink(app(InstallationState::class)->lockPath());
        parent::tearDown();
    }

    private function admin(string $email = 'admin@exemple.fr'): array
    {
        return ['name' => 'Admin', 'email' => $email, 'password' => 'MotDePasse-Solide-1', 'password_confirmation' => 'MotDePasse-Solide-1'];
    }

    public function test_creating_the_administrator_locks_the_installation_everywhere(): void
    {
        $this->post(route('install.admin.store'), $this->admin())->assertRedirect(route('login'))->assertSessionHas('success');

        $admin = User::where('email', 'admin@exemple.fr')->sole();
        $this->assertTrue($admin->is_admin);
        $this->assertSame(Role::where('name', 'admin')->value('id'), $admin->role_id);
        $this->assertTrue(DB::table('installation')->where('completed', true)->exists());
        $this->assertFileExists(app(InstallationState::class)->lockPath());
        $this->assertStringContainsString('APP_INSTALLED="true"', file_get_contents($this->envPath));
    }

    public function test_a_second_administrator_cannot_be_created_through_the_installer(): void
    {
        $this->post(route('install.admin.store'), $this->admin());

        $this->post(route('install.admin.store'), $this->admin('pirate@exemple.fr'))->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['email' => 'pirate@exemple.fr']);
        $this->assertSame(1, User::where('is_admin', true)->count());
    }

    public function test_an_interrupted_installation_with_an_administrator_and_no_marker_is_closed(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->get(route('install.admin'))->assertRedirect('/');
        $this->post(route('install.admin.store'), $this->admin('pirate@exemple.fr'))->assertRedirect('/');
        $this->post(route('install.db-config.store'), ['db_host' => 'pirate.example', 'db_port' => 3306, 'db_database' => 'x', 'db_username' => 'x'])->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['email' => 'pirate@exemple.fr']);
        $this->assertStringNotContainsString('pirate.example', file_get_contents($this->envPath));
    }

    public function test_the_old_final_step_cannot_lock_a_site_that_has_no_administrator(): void
    {
        $this->get(route('install.complete'))->assertRedirect(route('install.admin'));

        $this->assertFalse(DB::table('installation')->where('completed', true)->exists());
        $this->assertFileDoesNotExist(app(InstallationState::class)->lockPath());
    }

    public function test_the_old_final_step_is_harmless_once_installed(): void
    {
        $this->post(route('install.admin.store'), $this->admin());

        $this->get(route('install.complete'))->assertRedirect(route('login'));
    }

    public function test_the_env_file_helper_escapes_values_and_adds_missing_keys(): void
    {
        (new EnvFile($this->envPath))->set(['APP_NAME' => 'Mon "CDI" $x', 'NOUVEAU' => 'oui']);

        $env = file_get_contents($this->envPath);
        $this->assertStringContainsString('APP_NAME="Mon \"CDI\" \$x"', $env);
        $this->assertStringContainsString('NOUVEAU="oui"', $env);
        $this->assertSame(1, substr_count($env, 'APP_NAME='));
    }
}
