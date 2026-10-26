<?php

namespace Tests\Support;

use App\Services\Backup\BackupManager;
use App\Services\Backup\DatabaseDumper;
use App\Services\SettingService;
use App\Services\Update\FileInstaller;
use App\Services\Update\ReleaseClient;
use App\Services\Update\SignatureVerifier;
use App\Services\Update\UpdateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ZipArchive;

/** Fabrique de fausses releases : un site factice dans un dossier temporaire et des archives signees avec une cle de test. */
trait BuildsReleases
{
    protected string $workDir;
    protected string $siteDir;
    protected string $storageDir;
    protected string $publicKey;
    protected string $secretKey;

    protected function setUpRelease(): void
    {
        $this->workDir = sys_get_temp_dir() . '/nc-update-' . bin2hex(random_bytes(4));
        $this->siteDir = $this->workDir . '/site';
        $this->storageDir = $this->workDir . '/storage';
        foreach (['app', 'vendor', 'config', 'public', 'storage/app'] as $dir) {
            mkdir("{$this->siteDir}/{$dir}", 0755, true);
        }
        mkdir($this->storageDir, 0755, true);
        file_put_contents("{$this->siteDir}/app/Old.php", 'ancien');
        file_put_contents("{$this->siteDir}/app/Removed.php", 'sera supprime');
        file_put_contents("{$this->siteDir}/.env", 'APP_KEY=secret');
        file_put_contents("{$this->siteDir}/storage/app/data.txt", 'donnees');

        $pair = sodium_crypto_sign_keypair();
        $this->publicKey = base64_encode(sodium_crypto_sign_publickey($pair));
        $this->secretKey = base64_encode(sodium_crypto_sign_secretkey($pair));
        config(['nova.version' => '1.0.0', 'nova.repo' => 'Owner/Repo', 'nova.update_public_key' => $this->publicKey]);
    }

    protected function tearDownRelease(): void
    {
        File::deleteDirectory($this->workDir);
    }

    /** Gestionnaire de sauvegardes de la base qui ecrit dans le dossier temporaire du test, jamais dans storage. */
    protected function databaseBackups(?DatabaseDumper $dumper = null): BackupManager
    {
        return new BackupManager($dumper ?? $this->app->make(DatabaseDumper::class), $this->workDir . '/database-backups');
    }

    protected function manager(?BackupManager $databaseBackups = null): UpdateManager
    {
        return new UpdateManager(
            $this->app->make(ReleaseClient::class),
            $this->app->make(SignatureVerifier::class),
            $this->app->make(FileInstaller::class),
            $this->app->make(SettingService::class),
            $databaseBackups ?? $this->databaseBackups(),
            $this->siteDir,
            $this->storageDir,
        );
    }

    /**
     * Archive de test. $files : chemin => contenu ; $manifest : champs a ecraser dans release.json.
     * Renvoie [chemin du zip, signature base64].
     */
    protected function buildZip(array $files, array $manifest = [], ?string $secretKey = null): array
    {
        $path = $this->workDir . '/built-' . bin2hex(random_bytes(3)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->addFromString('release.json', json_encode($manifest + ['name' => 'novacreneaux', 'version' => '1.1.0', 'php' => '8.3.0', 'deleted' => []]));
        $zip->close();

        $signature = base64_encode(sodium_crypto_sign_detached(file_get_contents($path), base64_decode($secretKey ?? $this->secretKey)));

        return [$path, $signature];
    }

    /** Fait repondre GitHub avec une release dont l'archive est $zip. $apiOverrides modifie la reponse de l'API. */
    protected function fakeGithub(string $zip, string $signature, string $version = '1.1.0', array $apiOverrides = [], ?string $downloadedBody = null): void
    {
        $base = "https://github.com/Owner/Repo/releases/download/v{$version}/";
        $api = array_replace_recursive([
            'tag_name' => "v{$version}",
            'body' => "Notes de la version {$version}",
            'published_at' => '2026-10-26T10:00:00Z',
            'assets' => [
                ['name' => "novacreneaux-{$version}.zip", 'size' => filesize($zip), 'browser_download_url' => $base . "novacreneaux-{$version}.zip"],
                ['name' => "novacreneaux-{$version}.zip.sig", 'size' => strlen($signature), 'browser_download_url' => $base . "novacreneaux-{$version}.zip.sig"],
            ],
        ], $apiOverrides);

        Http::fake([
            'api.github.com/*' => Http::response($api, 200),
            "{$base}novacreneaux-{$version}.zip" => Http::response($downloadedBody ?? file_get_contents($zip), 200),
            "{$base}novacreneaux-{$version}.zip.sig" => Http::response($signature, 200),
        ]);
    }
}
