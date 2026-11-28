<?php

namespace Tests\Support;

use App\Plugins\PluginManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ZipArchive;

/** Extensions de test : dossier temporaire, cle de signature de test, archives et catalogue simules. */
trait BuildsPlugins
{
    protected string $pluginsDir;
    protected string $pluginsWork;
    protected string $pluginsSecret;

    protected function setUpPlugins(): void
    {
        $this->pluginsWork = sys_get_temp_dir() . '/nc-plugins-' . bin2hex(random_bytes(4));
        $this->pluginsDir = $this->pluginsWork . '/plugins';
        mkdir($this->pluginsDir, 0755, true);

        $pair = sodium_crypto_sign_keypair();
        $this->pluginsSecret = base64_encode(sodium_crypto_sign_secretkey($pair));
        config([
            'nova.version' => '1.6.0',
            'nova.plugins.path' => $this->pluginsDir,
            'nova.plugins.state' => $this->pluginsWork . '/plugins.json',
            'nova.plugins.public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'nova.plugins.allow_unsigned' => false,
            'nova.plugins.catalog_repo' => 'Owner/Plugins',
            'nova.backups.directory' => $this->pluginsWork . '/database-backups',
        ]);
        $this->reloadPlugins();
    }

    protected function tearDownPlugins(): void
    {
        File::deleteDirectory($this->pluginsWork);
        File::deleteDirectory(storage_path('app/plugins-tmp'));
        File::deleteDirectory(storage_path('app/plugins-backup'));
    }

    /** Nouveau gestionnaire qui recharge les extensions actives, comme au demarrage du site. */
    protected function reloadPlugins(): PluginManager
    {
        $this->app->forgetInstance(PluginManager::class);
        $this->app->singleton(PluginManager::class);
        $manager = $this->app->make(PluginManager::class);
        $manager->load($this->app);

        return $manager;
    }

    /** Copie l'extension d'exemple dans plugins/. $signed : marqueur d'installation depuis une archive signee. */
    protected function installFixture(string $id = 'hello', bool $signed = true, ?callable $edit = null): string
    {
        $target = "{$this->pluginsDir}/{$id}";
        File::copyDirectory(base_path('tests/Fixtures/plugins/hello'), $target);
        if ($edit) {
            $manifest = json_decode(file_get_contents("{$target}/plugin.json"), true);
            file_put_contents("{$target}/plugin.json", json_encode($edit($manifest)));
        }
        if ($signed) {
            file_put_contents("{$target}/" . PluginManager::SIGNATURE_FILE, json_encode(['version' => '1.0.0']));
        }

        return $target;
    }

    /** Archive signee d'une extension. @param array<string, string> $extra fichiers ajoutes (chemin => contenu) */
    protected function buildPluginArchive(string $version = '1.0.0', array $extra = [], ?string $secret = null, ?callable $edit = null): array
    {
        $zipPath = $this->pluginsWork . "/hello-{$version}.zip";
        @unlink($zipPath);
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $source = base_path('tests/Fixtures/plugins/hello');
        $manifest = json_decode(file_get_contents("{$source}/plugin.json"), true);
        $manifest['version'] = $version;
        $manifest = $edit ? $edit($manifest) : $manifest;
        foreach (File::allFiles($source) as $file) {
            if ($file->getRelativePathname() !== 'plugin.json') {
                $zip->addFile($file->getPathname(), $file->getRelativePathname());
            }
        }
        $zip->addFromString('plugin.json', json_encode($manifest));
        foreach ($extra as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $signature = base64_encode(sodium_crypto_sign_detached(file_get_contents($zipPath), base64_decode($secret ?? $this->pluginsSecret)));

        return ['path' => $zipPath, 'signature' => $signature, 'size' => filesize($zipPath)];
    }

    /** Simule le depot du catalogue : index signe, archive et signature de la version donnee. */
    protected function fakeCatalog(array $archive, string $version = '1.0.0', array $entryOverride = [], ?string $indexSecret = null): array
    {
        $entry = $entryOverride + [
            'id' => 'hello', 'name' => 'Bonjour', 'description' => 'Exemple', 'version' => $version, 'author' => 'Nova',
            'permissions' => ['Voir la page Bonjour'], 'tag' => "hello-{$version}", 'archive' => "hello-{$version}.zip", 'size' => $archive['size'],
        ];
        $index = json_encode(['plugins' => [$entry]]);
        $indexSignature = base64_encode(sodium_crypto_sign_detached($index, base64_decode($indexSecret ?? $this->pluginsSecret)));
        $release = "https://github.com/Owner/Plugins/releases/download/hello-{$version}/hello-{$version}.zip";

        // Un nouveau catalogue remplace le precedent : avec Http::fake seul, le premier faux enregistre gagnerait
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'raw.githubusercontent.com/Owner/Plugins/main/index.json' => Http::response($index),
            'raw.githubusercontent.com/Owner/Plugins/main/index.json.sig' => Http::response($indexSignature),
            $release => Http::response(file_get_contents($archive['path'])),
            "{$release}.sig" => Http::response($archive['signature']),
        ]);

        return $entry;
    }
}
