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

    public function test_the_prerequisites_include_the_extensions_used_by_updates_signatures_and_html_cleaning(): void
    {
        $page = $this->get(route('install.prerequisites'))->assertOk();

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'zip', 'sodium', 'dom'] as $extension) {
            $page->assertSee("Extension {$extension}");
        }
        $page->assertSee('vérification de la signature des mises à jour');
    }

    public function test_the_site_name_and_timezone_chosen_in_the_installer_apply_even_if_read_earlier(): void
    {
        // Pendant l'assistant, chaque page lit deja ces reglages (AppServiceProvider) : le cache gardait le nom par defaut
        setting('app_name', 'NovaCreneaux');
        setting('timezone', '');
        setting('registration_open', '0');

        $this->post(route('install.site-config.store'), [
            'app_name' => 'Lycee Test', 'app_url' => 'https://reservation.exemple.fr', 'timezone' => 'Europe/London',
            'max_reservations_per_day' => 2, 'registration_open' => '1',
        ])->assertRedirect(route('install.admin'));

        $this->assertSame('Lycee Test', setting('app_name'));
        $this->assertSame('Europe/London', setting('timezone'));
        $this->assertSame('1', setting('registration_open'));
        $this->post(route('install.admin.store'), $this->admin());
        auth()->logout();
        $this->get(route('login'))->assertSee('<title>Connexion - Lycee Test</title>', false);
    }

    public function test_the_tests_never_use_the_lock_file_of_the_site(): void
    {
        $this->assertNotSame(storage_path('app/installed.lock'), app(InstallationState::class)->lockPath());
        $this->assertFalse(config('app.installed'), 'phpunit.xml force APP_INSTALLED=false, meme si le .env local dit true');
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
