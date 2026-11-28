<?php

namespace Tests\Feature\Plugins;

use App\Plugins\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsPlugins;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

require_once __DIR__ . '/../../../scripts/plugins/lib.php';

/** Scripts de publication : l'archive et l'index qu'ils produisent sont acceptes par le site, rien d'autre ne l'est. */
class PluginPublishingTest extends TestCase
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

    public function test_the_published_archive_and_index_are_installable_from_the_catalog(): void
    {
        $dist = $this->pluginsWork . '/dist';
        $pack = plugin_pack(base_path('tests/Fixtures/plugins/hello'), $dist, $this->pluginsSecret);
        $catalog = plugin_catalog($dist, $this->pluginsWork . '/catalog', $this->pluginsSecret);

        $this->assertSame(1, $catalog['plugins']);
        $this->assertSame('hello-1.0.0.zip', basename($pack['path']));
        Http::fake([
            'raw.githubusercontent.com/Owner/Plugins/main/index.json' => Http::response(file_get_contents($catalog['path'])),
            'raw.githubusercontent.com/Owner/Plugins/main/index.json.sig' => Http::response(file_get_contents($catalog['path'] . '.sig')),
            'github.com/Owner/Plugins/releases/download/hello-1.0.0/hello-1.0.0.zip' => Http::response(file_get_contents($pack['path'])),
            'github.com/Owner/Plugins/releases/download/hello-1.0.0/hello-1.0.0.zip.sig' => Http::response(file_get_contents($pack['path'] . '.sig')),
        ]);

        $this->actingAs($this->admin())->post(route('admin.plugins.install', 'hello'))->assertSessionHas('success');

        $this->assertNotNull(app(\App\Plugins\PluginManager::class)->find('hello')->signature);
    }

    public function test_the_catalog_keeps_only_the_latest_version_of_each_plugin(): void
    {
        $dist = $this->pluginsWork . '/dist';
        foreach (['1.0.0', '1.2.0', '1.1.0'] as $version) {
            $copy = $this->pluginsWork . "/copy-{$version}";
            File::copyDirectory(base_path('tests/Fixtures/plugins/hello'), $copy);
            file_put_contents("{$copy}/plugin.json", json_encode(['version' => $version] + json_decode(file_get_contents("{$copy}/plugin.json"), true)));
            plugin_pack($copy, $dist, $this->pluginsSecret);
        }

        plugin_catalog($dist, $this->pluginsWork . '/catalog', $this->pluginsSecret);

        $index = json_decode(file_get_contents($this->pluginsWork . '/catalog/index.json'), true);
        $this->assertSame(['1.2.0'], array_column($index['plugins'], 'version'));
        $this->assertSame('hello-1.2.0', $index['plugins'][0]['tag']);
    }

    public function test_packing_refuses_an_invalid_manifest_a_symlink_and_odd_file_names(): void
    {
        $bad = $this->pluginsWork . '/bad';
        File::copyDirectory(base_path('tests/Fixtures/plugins/hello'), $bad);
        file_put_contents("{$bad}/plugin.json", '{"id": "Bad Id"}');
        $this->assertThrows(fn () => plugin_pack($bad, $this->pluginsWork . '/o1', $this->pluginsSecret), \RuntimeException::class);

        $link = $this->pluginsWork . '/link';
        File::copyDirectory(base_path('tests/Fixtures/plugins/hello'), $link);
        symlink('/etc/passwd', "{$link}/src/passwd");
        $this->assertThrows(fn () => plugin_pack($link, $this->pluginsWork . '/o2', $this->pluginsSecret), \RuntimeException::class);

        $odd = $this->pluginsWork . '/odd';
        File::copyDirectory(base_path('tests/Fixtures/plugins/hello'), $odd);
        file_put_contents("{$odd}/src/a b.php", '<?php');
        $this->assertThrows(fn () => plugin_pack($odd, $this->pluginsWork . '/o3', $this->pluginsSecret), \RuntimeException::class);
    }

    public function test_the_catalog_refuses_an_archive_whose_signature_does_not_match(): void
    {
        $dist = $this->pluginsWork . '/dist';
        $pack = plugin_pack(base_path('tests/Fixtures/plugins/hello'), $dist, $this->pluginsSecret);
        file_put_contents($pack['path'] . '.sig', base64_encode(str_repeat('x', SODIUM_CRYPTO_SIGN_BYTES)));

        $this->assertThrows(fn () => plugin_catalog($dist, $this->pluginsWork . '/catalog', $this->pluginsSecret), \RuntimeException::class);
    }
}
