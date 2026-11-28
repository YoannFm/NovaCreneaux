<?php

namespace Tests\Feature\Plugins;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Plugins\Manifest;
use App\Plugins\PluginException;
use App\Plugins\PluginManager;
use App\Support\AdminNavigation;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsPlugins;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Chargement, activation, permissions et menu des extensions. */
class PluginSystemTest extends TestCase
{
    use BuildsPlugins, CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlugins();
    }

    protected function tearDown(): void
    {
        $this->tearDownPlugins();
        parent::tearDown();
    }

    private function activate(): PluginManager
    {
        $this->installFixture();
        $this->app->make(PluginManager::class)->enable('hello');

        return $this->reloadPlugins();
    }

    private function fixtureManifest(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/plugins/hello/plugin.json')), true);
    }

    public function test_the_example_manifest_is_valid_and_bad_ones_are_refused_with_a_reason(): void
    {
        $this->assertSame([], Manifest::parse(json_encode($this->fixtureManifest()))['problems']);

        $bad = [
            'id Hello' => ['id' => 'Hello'],
            'id too short' => ['id' => 'a'],
            'api missing' => ['api' => '1'],
            'version' => ['version' => '1.0'],
            'namespace outside NovaPlugin' => ['autoload' => ['App\\Hello\\' => 'src/']],
            'path traversal' => ['autoload' => ['NovaPlugin\\Hello\\' => '../src/']],
            'provider outside the declared namespaces' => ['providers' => ['App\\Providers\\AppServiceProvider']],
            'menu without a declared permission' => ['menu' => [['label' => 'X', 'icon' => 'bi-x', 'route' => 'admin.plugin.hello.index', 'permission' => 'inconnue']]],
            'menu route of another plugin' => ['menu' => [['label' => 'X', 'icon' => 'bi-x', 'route' => 'admin.users.index', 'permission' => 'voir']]],
            'bad permission key' => ['permissions' => ['Voir Tout' => 'x']],
            'unsafe url' => ['url' => 'javascript:alert(1)'],
        ];
        foreach ($bad as $case => $change) {
            $parsed = Manifest::parse(json_encode($change + $this->fixtureManifest()));
            $this->assertNull($parsed['manifest'], $case);
            $this->assertNotSame([], $parsed['problems'], $case);
        }
        $this->assertNull(Manifest::parse('pas du json')['manifest']);
    }

    public function test_a_plugin_not_installed_from_a_signed_archive_is_refused(): void
    {
        $this->installFixture(signed: false);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessage('archive signée');
        try {
            $this->app->make(PluginManager::class)->enable('hello');
        } finally {
            $this->assertFalse($this->app->make(PluginManager::class)->find('hello')->enabled);
        }
    }

    public function test_unsigned_plugins_are_accepted_only_in_development_mode(): void
    {
        $this->installFixture(signed: false);
        config(['nova.plugins.allow_unsigned' => true]);

        $this->app->make(PluginManager::class)->enable('hello');

        $this->assertTrue($this->app->make(PluginManager::class)->find('hello')->enabled);
    }

    public function test_the_plugin_requirements_are_checked(): void
    {
        $this->installFixture(edit: fn (array $m) => ['api' => 2] + $m);
        $this->assertStringContainsString("API 2", (string) $this->app->make(PluginManager::class)->unusable($this->app->make(PluginManager::class)->find('hello')));

        $this->installFixture('hello', edit: fn (array $m) => ['requires_nova' => '9.0.0'] + $m);
        $this->assertStringContainsString('NovaCreneaux 9.0.0', (string) $this->app->make(PluginManager::class)->unusable($this->app->make(PluginManager::class)->find('hello')));
    }

    public function test_enabling_runs_its_migrations_after_a_database_backup_and_logs_it(): void
    {
        $this->installFixture();

        $this->actingAs($this->admin());
        $this->app->make(PluginManager::class)->enable('hello');

        $this->assertTrue(Schema::hasTable('hello_notes'));
        $this->assertCount(1, glob($this->pluginsWork . '/database-backups/nova-*-update-*.sql.gz'));
        $this->assertTrue(ActivityLog::where('action', 'Extension activée')->exists());
    }

    public function test_an_enabled_plugin_adds_its_routes_menu_entry_and_permission(): void
    {
        $this->activate();
        $user = $this->staff(['hello.voir']);

        $this->assertArrayHasKey('hello.voir', Permission::all());
        $this->actingAs($user)->get(route('admin.plugin.hello.index'))->assertOk()->assertSee("Bonjour depuis l'extension", false);
        $this->assertContains('admin.plugin.hello.index', array_column(app(AdminNavigation::class)->links($user), 'route'));
        $this->get(route('admin.dashboard'))->assertSee('Extensions')->assertSee('admin/p/hello', false);
    }

    public function test_the_plugin_routes_stay_closed_to_everyone_without_its_permission(): void
    {
        $this->activate();

        $this->get(route('admin.plugin.hello.index'))->assertRedirect(route('login'));
        $this->actingAs($this->student())->get(route('admin.plugin.hello.index'))->assertRedirect();
        $this->actingAs($this->staff([Permission::USERS], 'autre'))->get(route('admin.plugin.hello.index'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->admin())->get(route('admin.plugin.hello.index'))->assertOk();
    }

    public function test_the_role_form_offers_the_plugin_permissions_in_their_own_group_and_saves_them(): void
    {
        $this->activate();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.roles.create'))->assertOk()->assertSee('Extension : Bonjour')->assertSee('Voir la page Bonjour');

        $this->post(route('admin.roles.store'), [
            'name' => 'stats', 'label' => 'Stats', 'color' => '#3b7ddd', 'permissions' => ['hello.voir'],
            'max_reservations_day' => 2, 'max_reservations_week' => 5, 'cancel_deadline_minutes' => 0, 'advance_days' => 30,
        ])->assertSessionHasNoErrors();
        $this->assertSame(['hello.voir'], Role::where('name', 'stats')->value('permissions'));
        $this->assertTrue(Role::where('name', 'stats')->first()->grantsAdminAccess());
    }

    public function test_a_disabled_plugin_grants_nothing_and_its_permission_is_remembered(): void
    {
        $this->activate();
        $user = $this->staff(['hello.voir'], 'extension');
        $this->assertTrue($user->hasAdminAccess());

        $this->app->make(PluginManager::class)->disable('hello');
        $this->reloadPlugins();

        $this->assertArrayNotHasKey('hello.voir', Permission::all());
        $this->assertFalse($user->fresh()->hasPermission('hello.voir'));
        $this->assertFalse($user->fresh()->hasAdminAccess(), 'un role qui n\'a que cette permission n\'entre plus dans l\'administration');
        $this->assertSame(['hello.voir'], $user->role->fresh()->permissions, 'la permission reste enregistree');

        $this->app->make(PluginManager::class)->enable('hello');
        $this->reloadPlugins();
        $this->assertTrue($user->fresh()->hasPermission('hello.voir'));
    }

    /** Extension sans page dont le fournisseur est le code donne. Un id et un espace de noms propres a chaque test : une classe ne se recharge pas. */
    private function installPluginWithProvider(string $id, string $namespace, string $providerBody): void
    {
        $target = $this->installFixture($id, edit: fn (array $m) => [
            'id' => $id, 'permissions' => [], 'menu' => [],
            'autoload' => ["NovaPlugin\\{$namespace}\\" => 'src/'], 'providers' => ["NovaPlugin\\{$namespace}\\Provider"],
        ] + $m);
        \Illuminate\Support\Facades\File::deleteDirectory("{$target}/src");
        mkdir("{$target}/src");
        file_put_contents("{$target}/src/Provider.php", "<?php\nnamespace NovaPlugin\\{$namespace};\n{$providerBody}\n");
    }

    public function test_a_plugin_that_crashes_is_disabled_and_the_site_keeps_working(): void
    {
        $this->installPluginWithProvider('crashy', 'Crashy', 'class Provider extends \\App\\Plugins\\PluginProvider { public function register(): void { throw new \\RuntimeException("boum"); } }');
        $this->app->make(PluginManager::class)->enable('crashy');

        $plugin = $this->reloadPlugins()->find('crashy');

        $this->assertFalse($plugin->enabled);
        $this->assertSame('boum', $plugin->failure);
        $this->assertTrue(ActivityLog::where('action', 'Extension désactivée après une erreur')->exists());
        $this->actingAs($this->admin())->get(route('admin.plugins.index'))->assertOk()->assertSee('Désactivée après une erreur : boum');
        $this->assertSame([], $this->reloadPlugins()->menu(), 'le plugin ne se recharge pas au demarrage suivant');
    }

    public function test_a_provider_must_extend_the_plugin_provider(): void
    {
        $this->installPluginWithProvider('wrong', 'Wrong', 'class Provider extends \\Illuminate\\Support\\ServiceProvider {}');
        $this->app->make(PluginManager::class)->enable('wrong');

        $plugin = $this->reloadPlugins()->find('wrong');

        $this->assertStringContainsString('doit étendre', (string) $plugin->failure);
    }

    public function test_deleting_requires_the_plugin_to_be_disabled_and_keeps_its_tables(): void
    {
        $this->activate();
        $manager = $this->app->make(PluginManager::class);

        try {
            $manager->delete('hello');
            $this->fail('suppression d\'une extension active');
        } catch (PluginException) {
            $this->assertDirectoryExists("{$this->pluginsDir}/hello");
        }

        $manager->disable('hello');
        $manager->delete('hello');

        $this->assertDirectoryDoesNotExist("{$this->pluginsDir}/hello");
        $this->assertTrue(Schema::hasTable('hello_notes'));
        $this->assertNull($manager->find('hello'));
    }

    public function test_a_plugin_folder_that_is_a_symlink_is_refused(): void
    {
        $real = $this->pluginsWork . '/elsewhere';
        \Illuminate\Support\Facades\File::copyDirectory(base_path('tests/Fixtures/plugins/hello'), $real);
        symlink($real, "{$this->pluginsDir}/hello");
        config(['nova.plugins.allow_unsigned' => true]);

        $plugin = $this->app->make(PluginManager::class)->find('hello');

        $this->assertNull($plugin->manifest);
        $this->assertStringContainsString('lien symbolique', implode(' ', $plugin->problems));
        $this->expectException(PluginException::class);
        $this->app->make(PluginManager::class)->enable('hello');
    }

    public function test_the_state_survives_a_missing_plugins_folder_and_a_broken_state_file(): void
    {
        \Illuminate\Support\Facades\File::deleteDirectory($this->pluginsDir);
        file_put_contents(config('nova.plugins.state'), '{pas du json');

        $manager = $this->reloadPlugins();

        $this->assertSame([], $manager->all());
        $this->assertSame([], $manager->permissions());
    }

    public function test_the_command_lists_enables_and_disables(): void
    {
        $this->installFixture();

        $this->artisan('plugin:manage', ['action' => 'list'])->expectsOutputToContain('Bonjour')->assertSuccessful();
        $this->artisan('plugin:manage', ['action' => 'enable', 'id' => 'hello'])->assertSuccessful();
        $this->assertTrue($this->app->make(PluginManager::class)->find('hello')->enabled);
        $this->artisan('plugin:manage', ['action' => 'disable', 'id' => 'hello'])->assertSuccessful();
        $this->artisan('plugin:manage', ['action' => 'enable', 'id' => 'inconnu'])->assertFailed();
        $this->artisan('plugin:manage', ['action' => 'oups'])->assertFailed();
    }
}
