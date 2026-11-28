<?php

namespace Tests\Feature\Plugins;

use App\Models\ActivityLog;
use App\Plugins\Catalog;
use App\Plugins\PluginManager;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsPlugins;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Catalogue signe, installation et mise a jour depuis une archive signee, administration. */
class PluginCatalogTest extends TestCase
{
    use BuildsPlugins, CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlugins();
        Cache::forget('plugin_catalog');
    }

    protected function tearDown(): void
    {
        $this->tearDownPlugins();
        parent::tearDown();
    }

    private function install(): ?string
    {
        $this->actingAs($this->admin())->post(route('admin.plugins.install', 'hello'));

        return session('error');
    }

    private function otherSecret(): string
    {
        return base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));
    }

    public function test_the_catalog_lists_the_signed_entries_with_download_addresses_under_the_release(): void
    {
        $this->fakeCatalog($this->buildPluginArchive());

        $catalog = app(Catalog::class)->get();

        $this->assertNull($catalog['error']);
        $entry = $catalog['plugins']['hello'];
        $this->assertSame('https://github.com/Owner/Plugins/releases/download/hello-1.0.0/hello-1.0.0.zip', $entry['archive_url']);
        $this->assertSame($entry['archive_url'] . '.sig', $entry['signature_url']);
        $this->assertSame(['Voir la page Bonjour'], $entry['permissions']);
    }

    public function test_an_index_with_a_bad_signature_is_refused_whatever_it_says(): void
    {
        $this->fakeCatalog($this->buildPluginArchive(), indexSecret: $this->otherSecret());

        $catalog = app(Catalog::class)->get();

        $this->assertSame([], $catalog['plugins']);
        $this->assertStringContainsString('Catalogue refusé', $catalog['error']);
    }

    public function test_the_catalog_reports_a_missing_repository_a_missing_key_and_ignores_bad_entries(): void
    {
        Http::fake(['raw.githubusercontent.com/*' => Http::response('', 404)]);
        $this->assertStringContainsString("pas publié", app(Catalog::class)->get(true)['error']);

        config(['nova.plugins.public_key' => '']);
        $this->assertStringContainsString("pas configurée", app(Catalog::class)->get(true)['error']);

        config(['nova.plugins.public_key' => base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(substr(base64_decode($this->pluginsSecret), 0, 32))))]);
        $archive = $this->buildPluginArchive();
        $this->fakeCatalog($archive, entryOverride: ['archive' => '../evil.zip']);
        $this->assertSame([], app(Catalog::class)->get(true)['plugins'], 'une archive hors du dossier de la release est ignoree');
        $this->fakeCatalog($archive, entryOverride: ['id' => 'Bad Id']);
        $this->assertSame([], app(Catalog::class)->get(true)['plugins']);
    }

    public function test_installing_from_the_catalog_verifies_the_signature_and_marks_the_plugin_as_signed(): void
    {
        $this->fakeCatalog($this->buildPluginArchive());

        $this->assertNull($this->install());

        $plugin = app(PluginManager::class)->find('hello');
        $this->assertSame('1.0.0', $plugin->version());
        $this->assertSame('1.0.0', $plugin->signature['version']);
        $this->assertSame(64, strlen($plugin->signature['sha256']));
        $this->assertFalse($plugin->enabled, "l'installation n'active rien");
        $this->assertNull(app(PluginManager::class)->unusable($plugin));
        $this->assertTrue(ActivityLog::where('action', 'Extension installée')->exists());
        $this->assertDirectoryDoesNotExist(storage_path('app/plugins-tmp') . '/' . 'x');
    }

    public function test_an_archive_signed_with_another_key_installs_nothing(): void
    {
        $this->fakeCatalog($this->buildPluginArchive(secret: $this->otherSecret()));

        $this->assertStringContainsString('signature', (string) $this->install());

        $this->assertNull(app(PluginManager::class)->find('hello'));
    }

    public function test_unsafe_archives_are_refused_before_anything_is_written(): void
    {
        $cases = [
            'path traversal' => ['../evil.php' => '<?php'],
            'absolute path' => ['/etc/evil.php' => '<?php'],
            'forged signature marker' => ['.nova-signed' => '{}'],
            'odd characters' => ["src/a b;rm.php" => '<?php'],
        ];
        foreach ($cases as $case => $extra) {
            Cache::forget('plugin_catalog');
            $this->fakeCatalog($this->buildPluginArchive(extra: $extra));

            $error = $this->install();

            $this->assertStringContainsString('Archive refusée', (string) $error, $case);
            $this->assertNull(app(PluginManager::class)->find('hello'), $case);
        }
    }

    public function test_an_archive_that_is_not_the_announced_plugin_is_refused(): void
    {
        $this->fakeCatalog($this->buildPluginArchive('1.0.0', edit: fn (array $m) => ['version' => '2.0.0'] + $m));
        $this->assertStringContainsString('pas l\'extension annoncée', (string) $this->install());

        Cache::forget('plugin_catalog');
        $this->fakeCatalog($this->buildPluginArchive('1.0.0', edit: fn (array $m) => ['providers' => ['App\\X']] + $m));
        $this->assertStringContainsString('plugin.json invalide', (string) $this->install());

        Cache::forget('plugin_catalog');
        $this->fakeCatalog($this->buildPluginArchive('1.0.0', edit: fn (array $m) => ['requires_nova' => '9.0.0'] + $m));
        $this->assertStringContainsString('9.0.0', (string) $this->install());
        $this->assertNull(app(PluginManager::class)->find('hello'));
    }

    public function test_a_truncated_download_is_refused(): void
    {
        $archive = $this->buildPluginArchive();
        $this->fakeCatalog(['size' => $archive['size'] + 10] + $archive);

        $this->assertStringContainsString('incomplète', (string) $this->install());
        $this->assertNull(app(PluginManager::class)->find('hello'));
    }

    public function test_installing_is_refused_without_the_signing_key(): void
    {
        $this->fakeCatalog($this->buildPluginArchive());
        $manager = app(PluginManager::class);
        $installer = app(\App\Plugins\PluginInstaller::class);
        $entry = app(Catalog::class)->get()['plugins']['hello'];

        config(['nova.plugins.public_key' => '']);

        $this->expectException(\App\Plugins\PluginException::class);
        $installer->install($entry);
        $this->assertNull($manager->find('hello'));
    }

    public function test_updating_keeps_the_previous_version_aside_and_migrates_an_enabled_plugin(): void
    {
        $this->fakeCatalog($this->buildPluginArchive('1.0.0'));
        $this->install();
        $this->actingAs($this->admin());
        app(PluginManager::class)->enable('hello');
        Cache::forget('plugin_catalog');
        $this->fakeCatalog($this->buildPluginArchive('1.1.0'), '1.1.0');

        $this->get(route('admin.plugins.index'))->assertSee('Mise à jour 1.1.0');
        $this->assertNull($this->install());

        $plugin = app(PluginManager::class)->find('hello');
        $this->assertSame('1.1.0', $plugin->version());
        $this->assertTrue($plugin->enabled, "une mise a jour garde l'etat actif");
        $this->assertCount(1, glob(storage_path('app/plugins-backup/hello-1.0.0-*')));
        $this->assertTrue(ActivityLog::where('action', 'Extension mise à jour')->exists());
    }

    public function test_the_admin_page_manages_the_whole_lifecycle_and_only_for_administrators(): void
    {
        $this->fakeCatalog($this->buildPluginArchive());
        $everything = $this->staff(array_keys(Permission::all()));

        foreach ([$this->student(), $everything] as $user) {
            $this->actingAs($user)->get(route('admin.plugins.index'))->assertRedirect();
            $this->post(route('admin.plugins.install', 'hello'))->assertRedirect();
            $this->post(route('admin.plugins.enable', 'hello'))->assertRedirect();
        }
        $this->assertNull(app(PluginManager::class)->find('hello'));

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.plugins.index'))->assertOk()->assertSee('Bonjour')->assertSee('Installer');
        $this->post(route('admin.plugins.install', 'hello'))->assertSessionHas('success');
        $this->get(route('admin.plugins.index'))->assertSee('Inactive');
        $this->post(route('admin.plugins.enable', 'hello'))->assertSessionHas('success');
        $this->delete(route('admin.plugins.destroy', 'hello'))->assertSessionHas('error');
        $this->post(route('admin.plugins.disable', 'hello'))->assertSessionHas('success');
        $this->delete(route('admin.plugins.destroy', 'hello'))->assertSessionHas('success');
        $this->post(route('admin.plugins.install', 'inconnu'))->assertSessionHas('error');
        $this->post(route('admin.plugins.refresh'))->assertSessionHas('success');
        $this->assertNull(app(PluginManager::class)->find('hello'));
    }

    public function test_the_page_explains_a_catalog_error_and_a_missing_key(): void
    {
        config(['nova.plugins.public_key' => '']);

        $this->actingAs($this->admin())->get(route('admin.plugins.index'))->assertOk()
            ->assertSee('clé de signature des extensions n\'est pas configurée', false)->assertSee('Catalogue indisponible');
    }

    public function test_route_ids_that_are_not_plugin_ids_are_not_routed(): void
    {
        $this->actingAs($this->admin())->post('/admin/plugins/..%2Fx/enable')->assertNotFound();
        $this->post('/admin/plugins/Hello/enable')->assertNotFound();
    }
}
