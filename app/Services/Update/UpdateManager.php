<?php
namespace App\Services\Update;
use App\Services\ActivityLogger;
use App\Services\Backup\BackupException;
use App\Services\Backup\BackupManager;
use App\Services\Backup\DatabaseRestorer;
use App\Services\SettingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Mise a jour par archive signee publiee sur GitHub : telecharger et verifier, installer (maintenance, sauvegarde,
 * copie), puis finaliser dans une seconde requete (migrations, caches) pour que ce soit le nouveau code qui s'execute.
 */
class UpdateManager {
    private const MAX_ZIP_BYTES = 200 * 1024 * 1024;
    private const KEPT_BACKUPS = 2;

    public function __construct(
        private ReleaseClient $client,
        private SignatureVerifier $signature,
        private FileInstaller $installer,
        private SettingService $settings,
        private BackupManager $databaseBackups,
        private DatabaseRestorer $databaseRestorer,
        private ?string $basePath = null,
        private ?string $storagePath = null,
    ) {
        $this->basePath ??= base_path();
        $this->storagePath ??= storage_path('app');
    }

    public function currentVersion(): string {
        return config('nova.version');
    }

    public function signingConfigured(): bool {
        return $this->signature->isConfigured(config('nova.update_public_key'));
    }

    /** Une version plus recente est connue, d'apres le dernier controle (sans requete reseau). */
    public function cachedHasUpdate(): bool {
        $release = $this->client->cached()['release'] ?? null;

        return $release !== null && version_compare($release['version'], $this->currentVersion(), '>');
    }

    public function status(bool $force = false): array {
        $check = $this->client->check($force);
        $release = $check['release'];
        $hasUpdate = $release !== null && version_compare($release['version'], $this->currentVersion(), '>');

        return [
            'current' => $this->currentVersion(),
            'release' => $release,
            'has_update' => $hasUpdate,
            'error' => $check['error'],
            'checked_at' => $check['checked_at'],
            'downloaded' => $hasUpdate && is_file($this->zipPath($release['version'])) && is_file($this->zipPath($release['version']) . '.sig'),
            'pending' => $this->pending(),
            'backup' => $this->latestBackup(),
            'database_backup' => $this->rollbackDatabaseBackup(),
            'signing_configured' => $this->signingConfigured(),
        ];
    }

    /** Telecharge l'archive et sa signature, les verifie et controle leur contenu. */
    public function download(): array {
        $release = $this->availableRelease();
        $zip = $this->zipPath($release['version']);
        $this->guard(fn () => $this->fetchFiles($release, $zip));

        try {
            $manifest = $this->verifiedManifest($zip, $release['version']);
        } catch (\Throwable $e) {
            @unlink($zip);
            @unlink($zip . '.sig');
            throw $e;
        }

        return ['version' => $release['version'], 'manifest' => $manifest];
    }

    /** Etape 1 de l'installation : maintenance, sauvegarde, copie des fichiers. Les migrations viennent a l'etape suivante. */
    public function install(bool $backupDatabase = true): array {
        $release = $this->availableRelease();
        $zip = $this->zipPath($release['version']);
        if (!is_file($zip)) {
            throw new UpdateException("Téléchargez d'abord la mise à jour.");
        }

        $lock = Cache::lock('app-update', 900);
        if (!$lock->get()) {
            throw new UpdateException('Une mise à jour est déjà en cours.');
        }

        try {
            if ($this->pending() !== null) {
                throw new UpdateException("Une mise à jour précédente n'est pas terminée : finalisez-la d'abord.");
            }
            // L'archive est reverifiee juste avant l'installation : le fichier a pu changer depuis le telechargement
            $manifest = $this->verifiedManifest($zip, $release['version']);
            $this->assertReady($zip);
            @set_time_limit(300);

            $from = $this->currentVersion();
            $pending = ['from' => $from, 'to' => $release['version'], 'maintenance' => $this->settings->get('maintenance_mode', '0'), 'maintenance_message' => $this->settings->get('maintenance_message', '')];
            $this->settings->set('maintenance_mode', '1');
            $this->settings->set('maintenance_message', 'Mise à jour en cours, le site revient dans quelques minutes.');

            try {
                // Sauvegarde de la base retenue avec la mise a jour : "Revenir en arriere" peut la restaurer
                $pending['database_backup'] = $backupDatabase ? $this->backupDatabase() : null;
                $counts = $this->installer->install($zip, $this->basePath, $this->backupPath($from, $release['version']), $manifest['deleted'] ?? [], ['from' => $from, 'to' => $release['version']]);
            } catch (\Throwable $e) {
                $this->restoreMaintenance($pending);
                throw $e;
            }

            $this->writeJson($this->pendingPath(), $pending);
            $this->writeJson($this->lastUpdatePath(), $pending);
            $this->resetOpcache();
            $this->pruneBackups();

            return ['from' => $from, 'to' => $release['version']] + $counts;
        } finally {
            $lock->release();
        }
    }

    /** Etape 2, dans une requete distincte : migrations, caches, fin de la maintenance. */
    public function finalize(): array {
        $pending = $this->pending();
        if ($pending === null) {
            throw new UpdateException('Aucune mise à jour à finaliser.');
        }
        @set_time_limit(300);
        $this->resetOpcache();

        $migrations = $this->runMigrations($pending);
        Artisan::call('optimize:clear');

        $this->restoreMaintenance($pending);
        @unlink($this->pendingPath());
        @unlink($this->zipPath($pending['to']));
        @unlink($this->zipPath($pending['to']) . '.sig');
        Cache::forget('update_check');
        ActivityLogger::log('Mise à jour installée', "v{$pending['from']} vers v{$pending['to']}");

        return ['from' => $pending['from'], 'to' => $pending['to'], 'migrations' => $migrations];
    }

    /**
     * Remet les fichiers de la version precedente. Avec $restoreDatabase, remet aussi la base telle qu'avant la mise
     * a jour (sauvegarde faite a l'installation) : tout ce qui a ete saisi depuis est perdu. Sans cela, la base garde
     * les changements des migrations, que l'ancien code peut ne pas comprendre.
     */
    public function restore(bool $restoreDatabase = false): array {
        $backup = $this->latestBackup();
        if ($backup === null) {
            throw new UpdateException('Aucune sauvegarde de fichiers disponible.');
        }
        $databaseBackup = $restoreDatabase ? $this->rollbackDatabaseBackup() : null;
        if ($restoreDatabase && $databaseBackup === null) {
            throw new UpdateException("Aucune sauvegarde de la base n'est associée à cette mise à jour : rien n'a été modifié.");
        }

        $lock = Cache::lock('app-update', 900);
        if (!$lock->get()) {
            throw new UpdateException('Une mise à jour est en cours.');
        }

        try {
            $pending = $this->pending();
            $lastUpdate = $pending ?? $this->readJson($this->lastUpdatePath());
            $result = $this->installer->restore($backup['path'], $this->basePath);
            if ($databaseBackup !== null) {
                $this->restoreDatabase($databaseBackup['path']);
            }
            // Maintenance d'avant la mise a jour : a remettre si elle n'est pas finalisee, ou si la base restauree
            // (sauvegardee site deja en maintenance) l'a reactivee
            $maintenance = $databaseBackup !== null ? $lastUpdate : $pending;
            if ($maintenance) {
                $this->restoreMaintenance($maintenance);
            }
            @unlink($this->pendingPath());
            @unlink($this->lastUpdatePath());
            $this->resetOpcache();
            Artisan::call('optimize:clear');
            @unlink($backup['path']);
            Cache::forget('update_check');
            $what = $databaseBackup ? 'fichiers et base de données' : 'fichiers seulement';
            ActivityLogger::log('Mise à jour annulée', "retour à la version {$result['from']} ({$what})");

            return $result + ['database_restored' => $databaseBackup !== null];
        } finally {
            $lock->release();
        }
    }

    public function pending(): ?array {
        return $this->readJson($this->pendingPath());
    }

    /** Sauvegarde de la base faite juste avant la derniere mise a jour, si elle existe encore : ['name', 'path', 'created_at']. */
    public function rollbackDatabaseBackup(): ?array {
        $update = $this->pending() ?? $this->readJson($this->lastUpdatePath());
        $name = $update['database_backup'] ?? null;
        if (!$name) {
            return null;
        }
        try {
            $path = $this->databaseBackups->path($name);
        } catch (BackupException) {
            return null; // supprimee depuis
        }

        return ['name' => $name, 'path' => $path, 'created_at' => date('c', filemtime($path))];
    }

    /** Derniere sauvegarde de fichiers : ['path', 'name', 'created_at']. */
    public function latestBackup(): ?array {
        $files = glob($this->storagePath . '/backups/*.zip') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $files === [] ? null : ['path' => $files[0], 'name' => basename($files[0], '.zip'), 'created_at' => date('c', filemtime($files[0]))];
    }

    private function availableRelease(): array {
        if (!$this->signingConfigured()) {
            throw new UpdateException('La clé de signature n\'est pas configurée : les mises à jour sont désactivées.');
        }
        $status = $this->status(true);
        if ($status['error']) {
            throw new UpdateException($status['error']);
        }
        if (!$status['has_update']) {
            throw new UpdateException('Le site est déjà à jour.');
        }

        return $status['release'];
    }

    private function fetchFiles(array $release, string $zip): void {
        if ($release['zip']['size'] <= 0 || $release['zip']['size'] > self::MAX_ZIP_BYTES) {
            throw new UpdateException("Taille d'archive refusée.");
        }
        @mkdir(dirname($zip), 0755, true);

        $part = $zip . '.part';
        try {
            Http::connectTimeout(5)->timeout(180)->withOptions(['sink' => $part])->get($release['zip']['url'])->throw();
            $signature = Http::connectTimeout(5)->timeout(20)->get($release['signature']['url'])->throw()->body();
        } catch (\Throwable $e) {
            @unlink($part);
            throw new UpdateException('Téléchargement impossible : ' . $e->getMessage());
        }

        if (filesize($part) !== $release['zip']['size']) {
            @unlink($part);
            throw new UpdateException("L'archive téléchargée est incomplète.");
        }
        rename($part, $zip);
        file_put_contents($zip . '.sig', mb_substr($signature, 0, 1024));
    }

    /** Verifie la signature, puis que l'archive annonce bien la version attendue, plus recente, compatible avec ce PHP. */
    private function verifiedManifest(string $zip, string $expectedVersion): array {
        $this->signature->verify($zip, (string) @file_get_contents($zip . '.sig'), config('nova.update_public_key'));
        $manifest = $this->installer->manifest($zip);

        if (($manifest['version'] ?? null) !== $expectedVersion) {
            throw new UpdateException("L'archive annonce la version {$manifest['version']} au lieu de {$expectedVersion}.");
        }
        if (version_compare($manifest['version'], $this->currentVersion(), '<=')) {
            throw new UpdateException('Cette archive ne remplace pas une version plus ancienne.');
        }
        if (version_compare(PHP_VERSION, $manifest['php'], '<')) {
            throw new UpdateException("Cette version demande PHP {$manifest['php']} ou plus ; le serveur utilise PHP " . PHP_VERSION . '.');
        }

        return $manifest;
    }

    private function assertReady(string $zip): void {
        if (!is_writable($this->basePath) || !is_writable($this->basePath . '/app') || !is_writable($this->basePath . '/vendor')) {
            throw new UpdateException("Le dossier du site n'est pas accessible en écriture pour PHP : mise à jour impossible depuis l'interface.");
        }
        $free = @disk_free_space($this->storagePath);
        if ($free !== false && $free < 3 * filesize($zip)) {
            throw new UpdateException("Espace disque insuffisant pour la sauvegarde et l'installation.");
        }
    }

    /** Un Throwable inattendu pendant le telechargement devient un message lisible. */
    private function guard(callable $callback): void {
        try {
            $callback();
        } catch (UpdateException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw new UpdateException('Erreur inattendue : ' . $e->getMessage());
        }
    }

    /**
     * Sauvegarde de la base juste avant de toucher aux fichiers. Un echec arrete l'installation : mieux vaut ne rien changer.
     * @return string nom de la sauvegarde creee
     */
    private function backupDatabase(): string {
        try {
            $backup = $this->databaseBackups->create(BackupManager::BEFORE_UPDATE);
        } catch (BackupException $e) {
            throw new UpdateException("La sauvegarde de la base a échoué ({$e->getMessage()}). Rien n'a été modifié : corrigez le problème, ou décochez la sauvegarde de la base pour installer quand même.");
        }
        $this->databaseBackups->pruneBeforeUpdate();
        ActivityLogger::log('Sauvegarde de la base avant mise à jour', $backup['name']);

        return $backup['name'];
    }

    /**
     * Lance les migrations et verifie qu'il n'en reste aucune en attente. En cas d'echec, la maintenance reste active
     * et le fichier de reprise garde l'erreur : la page propose de reessayer ou de revenir en arriere.
     */
    private function runMigrations(array $pending): string {
        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());
            $left = $this->pendingMigrations();
            if ($exitCode !== 0 || $left !== []) {
                throw new UpdateException('Les migrations ne sont pas toutes passées'
                    . ($left ? ' (en attente : ' . implode(', ', $left) . ')' : '') . ($output !== '' ? ". {$output}" : '.'));
            }

            return $output;
        } catch (\Throwable $e) {
            report($e);
            $message = $e instanceof UpdateException ? $e->getMessage() : 'Une migration a échoué : ' . $e->getMessage();
            $this->writeJson($this->pendingPath(), ['error' => mb_substr($message, 0, 2000), 'failed_at' => now()->toIso8601String()] + $pending);
            ActivityLogger::log('Mise à jour : échec des migrations', "v{$pending['from']} vers v{$pending['to']}");

            throw new UpdateException("{$message} Le site reste en maintenance : réessayez, ou revenez à la version précédente.");
        }
    }

    /** Migrations du site pas encore passees (celles des extensions se lancent depuis Admin > Extensions). */
    private function pendingMigrations(): array {
        $migrator = app('migrator');
        $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];

        return array_values(array_diff($files, $ran));
    }

    private function restoreDatabase(string $path): void {
        try {
            $this->databaseRestorer->restore($path);
        } catch (BackupException $e) {
            throw new UpdateException("Les fichiers sont restaurés, mais la base ne l'est peut-être qu'en partie : {$e->getMessage()} Réessayez, ou restaurez à la main la sauvegarde " . basename($path) . '.');
        }
    }

    private function writeJson(string $path, array $data): void {
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode($data));
    }

    private function readJson(string $path): ?array {
        $content = @file_get_contents($path);

        return $content ? json_decode($content, true) : null;
    }

    private function restoreMaintenance(array $pending): void {
        $this->settings->set('maintenance_mode', $pending['maintenance'] ?? '0');
        $this->settings->set('maintenance_message', $pending['maintenance_message'] ?? '');
    }

    private function resetOpcache(): void {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function pruneBackups(): void {
        $files = glob($this->storagePath . '/backups/*.zip') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, self::KEPT_BACKUPS) as $old) {
            @unlink($old);
        }
    }

    private function zipPath(string $version): string {
        return "{$this->storagePath}/updates/novacreneaux-{$version}.zip";
    }

    private function backupPath(string $from, string $to): string {
        return "{$this->storagePath}/backups/{$from}-vers-{$to}-" . now()->format('Ymd-His') . '.zip';
    }

    private function lastUpdatePath(): string {
        return $this->storagePath . '/updates/last-update.json';
    }

    private function pendingPath(): string {
        return $this->storagePath . '/updates/pending.json';
    }
}
