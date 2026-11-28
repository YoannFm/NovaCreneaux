<?php

namespace Tests\Feature\Update;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\Update\UpdateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BuildsReleases;
use Tests\TestCase;

class UpdateManagerTest extends TestCase
{
    use BuildsReleases, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRelease();
        Cache::forget('update_check');
    }

    protected function tearDown(): void
    {
        $this->tearDownRelease();
        parent::tearDown();
    }

    private function setting(string $key): ?string
    {
        cache()->forget("setting_{$key}");

        return Setting::find($key)?->value;
    }

    /** Publie une 1.1.0 qui remplace Old.php, ajoute New.php et supprime Removed.php. */
    private function publish(array $manifest = [], ?string $secretKey = null, array $apiOverrides = [], ?string $body = null): void
    {
        [$zip, $signature] = $this->buildZip(['app/Old.php' => 'nouveau', 'app/New.php' => 'ajoute'], $manifest + ['deleted' => ['app/Removed.php']], $secretKey);
        $this->fakeGithub($zip, $signature, '1.1.0', $apiOverrides, $body);
    }

    // ---- etat -----------------------------------------------------------------------------------

    public function test_status_reports_an_available_update(): void
    {
        $this->publish();

        $status = $this->manager()->status();

        $this->assertSame('1.0.0', $status['current']);
        $this->assertTrue($status['has_update']);
        $this->assertSame('1.1.0', $status['release']['version']);
        $this->assertTrue($status['signing_configured']);
        $this->assertFalse($status['downloaded']);
        $this->assertTrue($this->manager()->cachedHasUpdate());
    }

    public function test_the_same_or_an_older_release_is_not_an_update(): void
    {
        [$zip, $signature] = $this->buildZip(['app/A.php' => 'a'], ['version' => '1.0.0']);
        $this->fakeGithub($zip, $signature, '1.0.0');

        $this->assertFalse($this->manager()->status()['has_update']);
        $this->assertFalse($this->manager()->cachedHasUpdate());
    }

    public function test_updates_are_refused_without_a_signing_key(): void
    {
        $this->publish();
        config(['nova.update_public_key' => '']);

        $this->assertFalse($this->manager()->status()['signing_configured']);
        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('clé de signature');
        $this->manager()->download();
    }

    // ---- telechargement et verification --------------------------------------------------------------

    public function test_a_signed_archive_is_downloaded_and_verified(): void
    {
        $this->publish();

        $result = $this->manager()->download();

        $this->assertSame('1.1.0', $result['version']);
        $this->assertFileExists("{$this->storageDir}/updates/novacreneaux-1.1.0.zip");
        $this->assertFileExists("{$this->storageDir}/updates/novacreneaux-1.1.0.zip.sig");
        $this->assertTrue($this->manager()->status()['downloaded']);
    }

    public function test_an_archive_signed_with_another_key_is_refused_and_deleted(): void
    {
        $other = base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));
        $this->publish(secretKey: $other);

        try {
            $this->manager()->download();
            $this->fail('Archive acceptee');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('signature', $e->getMessage());
        }

        $this->assertFileDoesNotExist("{$this->storageDir}/updates/novacreneaux-1.1.0.zip");
        $this->assertFileDoesNotExist("{$this->storageDir}/updates/novacreneaux-1.1.0.zip.sig");
    }

    public function test_a_tampered_download_is_refused(): void
    {
        [$zip, $signature] = $this->buildZip(['app/Old.php' => 'nouveau']);
        $tampered = file_get_contents($zip) . 'octets ajoutes';
        // taille annoncee identique a celle recue pour ne tester que la signature
        $this->fakeGithub($zip, $signature, '1.1.0', ['assets' => [['size' => strlen($tampered)]]], $tampered);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('signature de l\'archive est invalide');
        $this->manager()->download();
    }

    public function test_an_incomplete_download_is_refused(): void
    {
        $this->publish(body: 'tronque');

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('incomplète');
        $this->manager()->download();
    }

    public function test_the_archive_must_announce_the_version_of_its_release(): void
    {
        $this->publish(['version' => '9.9.9']);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('annonce la version 9.9.9 au lieu de 1.1.0');
        $this->manager()->download();
    }

    public function test_an_archive_that_requires_a_newer_php_is_refused(): void
    {
        $this->publish(['php' => '99.0.0']);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('demande PHP 99.0.0');
        $this->manager()->download();
    }

    public function test_an_oversized_archive_is_refused_before_downloading(): void
    {
        $this->publish(apiOverrides: ['assets' => [['size' => 500 * 1024 * 1024]]]);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Taille d\'archive refusée');
        $this->manager()->download();
    }

    // ---- installation en deux etapes -----------------------------------------------------------------

    public function test_install_then_finalize_updates_the_site(): void
    {
        $this->publish();
        $manager = $this->manager();
        $manager->download();

        $result = $manager->install();

        $this->assertSame(['from' => '1.0.0', 'to' => '1.1.0', 'replaced' => 1, 'added' => 1, 'deleted' => 1], $result);
        $this->assertSame('nouveau', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertFileDoesNotExist("{$this->siteDir}/app/Removed.php");
        $this->assertSame('APP_KEY=secret', file_get_contents("{$this->siteDir}/.env"));
        $this->assertSame('1', $this->setting('maintenance_mode'), 'Le site est en maintenance entre les deux etapes');
        $this->assertSame('1.1.0', $manager->pending()['to']);
        $this->assertNotNull($manager->latestBackup());

        $final = $manager->finalize();

        $this->assertSame(['from' => '1.0.0', 'to' => '1.1.0'], ['from' => $final['from'], 'to' => $final['to']]);
        $this->assertNull($manager->pending());
        $this->assertSame('0', $this->setting('maintenance_mode'), 'La maintenance est retiree');
        $this->assertFileDoesNotExist("{$this->storageDir}/updates/novacreneaux-1.1.0.zip");
        $this->assertTrue(ActivityLog::where('action', 'Mise à jour installée')->exists());
    }

    public function test_an_existing_maintenance_setting_is_restored_not_cleared(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1']);
        Setting::create(['key' => 'maintenance_message', 'value' => 'Vacances']);
        $this->publish();
        $manager = $this->manager();
        $manager->download();
        $manager->install();

        $manager->finalize();

        $this->assertSame('1', $this->setting('maintenance_mode'));
        $this->assertSame('Vacances', $this->setting('maintenance_message'));
    }

    public function test_install_requires_the_download_first(): void
    {
        $this->publish();

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Téléchargez d\'abord');
        $this->manager()->install();
    }

    public function test_the_archive_is_verified_again_right_before_installing(): void
    {
        $this->publish();
        $manager = $this->manager();
        $manager->download();
        file_put_contents("{$this->storageDir}/updates/novacreneaux-1.1.0.zip", 'remplace apres le telechargement');

        try {
            $manager->install();
            $this->fail('Installation acceptee');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('signature', $e->getMessage());
        }

        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertNotSame('1', $this->setting('maintenance_mode'));
    }

    public function test_a_second_install_is_blocked_until_the_first_is_finalized(): void
    {
        $this->publish();
        $manager = $this->manager();
        $manager->download();
        $manager->install();

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('pas terminée');
        $manager->install();
    }

    public function test_concurrent_updates_are_refused(): void
    {
        $this->publish();
        $manager = $this->manager();
        $manager->download();
        $lock = Cache::lock('app-update', 60);
        $lock->get();

        try {
            $manager->install();
            $this->fail('Installation acceptee');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('déjà en cours', $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function test_a_failed_install_restores_files_and_maintenance(): void
    {
        [$zip, $signature] = $this->buildZip(['app/A.php' => 'a', 'app/Old.php/Sub.php' => 'impossible'], ['deleted' => ['app/Removed.php']]);
        $this->fakeGithub($zip, $signature);
        $manager = $this->manager();
        $manager->download();

        try {
            $manager->install();
            $this->fail('Installation acceptee');
        } catch (UpdateException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertFileExists("{$this->siteDir}/app/Removed.php");
        $this->assertNotSame('1', $this->setting('maintenance_mode'));
        $this->assertNull($manager->pending());
    }

    public function test_finalize_without_a_pending_update_is_refused(): void
    {
        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Aucune mise à jour à finaliser');
        $this->manager()->finalize();
    }

    // ---- retour arriere ---------------------------------------------------------------------------------

    public function test_the_previous_files_can_be_restored(): void
    {
        $this->publish();
        $manager = $this->manager();
        $manager->download();
        $manager->install();
        $manager->finalize();

        $result = $manager->restore();

        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->assertSame('sera supprime', file_get_contents("{$this->siteDir}/app/Removed.php"));
        $this->assertFileDoesNotExist("{$this->siteDir}/app/New.php");
        $this->assertSame('1.0.0', $result['from']);
        $this->assertNull($manager->latestBackup());
        $this->assertTrue(ActivityLog::where('action', 'Mise à jour annulée')->exists());
    }

    public function test_restore_without_a_backup_is_refused(): void
    {
        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Aucune sauvegarde');
        $this->manager()->restore();
    }

    public function test_only_the_two_latest_backups_are_kept(): void
    {
        @mkdir("{$this->storageDir}/backups", 0755, true);
        foreach (['a', 'b', 'c'] as $i => $name) {
            touch("{$this->storageDir}/backups/{$name}.zip", time() - 1000 + $i);
        }
        $this->publish();
        $manager = $this->manager();
        $manager->download();

        $manager->install();

        $this->assertCount(2, glob("{$this->storageDir}/backups/*.zip"));
    }
}
