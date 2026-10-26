<?php

namespace Tests\Feature\Backup;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupSettings;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupAdminTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'MotDePasse-Admin-1';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/nc-admin-backups-' . bin2hex(random_bytes(4));
        config(['nova.backups.directory' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true, 'password' => bcrypt(self::PASSWORD)]);
    }

    private function manager(): BackupManager
    {
        return app(BackupManager::class);
    }

    private function aBackup(): array
    {
        return $this->manager()->create(BackupManager::MANUAL);
    }

    public function test_only_administrators_reach_the_backup_actions(): void
    {
        $student = User::factory()->create(['role_id' => Role::defaultUser()->id]);
        $backup = $this->aBackup();

        $this->post(route('admin.backups.store'))->assertRedirect(route('login'));
        $this->actingAs($student)->post(route('admin.backups.store'))->assertRedirect('/');
        $this->actingAs($student)->put(route('admin.backups.schedule'), ['frequency' => 'daily', 'time' => '03:00', 'keep' => 7])->assertRedirect('/');
        $this->actingAs($student)->post(route('admin.backups.download', $backup['name']), ['password' => self::PASSWORD])->assertRedirect('/');
        $this->actingAs($student)->delete(route('admin.backups.destroy', $backup['name']), ['password' => self::PASSWORD])->assertRedirect('/');

        $this->assertCount(1, $this->manager()->list());
    }

    public function test_the_update_page_shows_the_backup_section_with_the_default_schedule(): void
    {
        $this->actingAs($this->admin())->get(route('admin.update.index'))->assertOk()
            ->assertSee('Sauvegardes de la base de données')
            ->assertSee('Sauvegarder maintenant')
            ->assertSee('Tous les jours')
            ->assertSee('value="03:00"', false)
            ->assertSee('prochaine le', false)
            ->assertSee("Aucune sauvegarde pour l'instant", false);
    }

    public function test_a_manual_backup_is_created_logged_and_listed(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.backups.store'))
            ->assertRedirect(route('admin.update.index') . '#sauvegardes')->assertSessionHas('backup_success');

        $this->assertCount(1, $this->manager()->list());
        $this->assertSame(BackupManager::MANUAL, $this->manager()->list()[0]['trigger']);
        $this->assertTrue(ActivityLog::where('action', 'Sauvegarde de la base créée')->exists());
        $this->actingAs($admin)->get(route('admin.update.index'))->assertSee('Manuelle')->assertSee('Télécharger')->assertSee('Supprimer');
    }

    public function test_the_schedule_can_be_changed_and_automatic_backups_disabled(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.backups.schedule'), ['enabled' => '1', 'frequency' => 'weekly', 'weekday' => '3', 'time' => '04:30', 'keep' => '5'])
            ->assertSessionHasNoErrors()->assertSessionHas('backup_success');
        $schedule = app(BackupSettings::class)->schedule();
        $this->assertSame(['weekly', '04:30', 3, 5, true], [$schedule->frequency, $schedule->time, $schedule->weekday, $schedule->keep, $schedule->enabled]);

        $this->actingAs($admin)->put(route('admin.backups.schedule'), ['frequency' => 'daily', 'time' => '03:00', 'keep' => '7']);
        $this->assertFalse(app(BackupSettings::class)->schedule()->enabled);
        $this->actingAs($admin)->get(route('admin.update.index'))->assertSee('désactivée');
    }

    public function test_invalid_schedule_values_are_rejected_and_nothing_is_saved(): void
    {
        $admin = $this->admin();

        foreach ([
            ['frequency' => 'chaque-seconde', 'time' => '03:00', 'keep' => '7'],
            ['frequency' => 'daily', 'time' => '25:00', 'keep' => '7'],
            ['frequency' => 'daily', 'time' => '3h', 'keep' => '7'],
            ['frequency' => 'daily', 'time' => '03:00', 'keep' => '0'],
            ['frequency' => 'daily', 'time' => '03:00', 'keep' => '1000'],
            ['frequency' => 'weekly', 'weekday' => '9', 'time' => '03:00', 'keep' => '7'],
        ] as $payload) {
            $this->actingAs($admin)->put(route('admin.backups.schedule'), $payload)->assertSessionHasErrors();
        }

        $this->assertSame('daily', app(BackupSettings::class)->schedule()->frequency);
        $this->assertSame(7, app(BackupSettings::class)->schedule()->keep);
    }

    public function test_a_last_automatic_failure_is_shown_to_the_administrator(): void
    {
        app(BackupSettings::class)->recordFailure('Une sauvegarde est déjà en cours.');

        $this->actingAs($this->admin())->get(route('admin.update.index'))->assertSee('La dernière sauvegarde automatique a échoué')->assertSee('déjà en cours');
    }

    public function test_download_needs_the_admin_password(): void
    {
        $admin = $this->admin();
        $backup = $this->aBackup();

        foreach ([null, '', 'mauvais'] as $password) {
            $this->actingAs($admin)->post(route('admin.backups.download', $backup['name']), ['password' => $password])
                ->assertRedirect(route('admin.update.index') . '#sauvegardes')->assertSessionHas('backup_error');
        }
        $this->assertSame(3, ActivityLog::where('action', 'like', 'Mot de passe refusé%')->count());

        $response = $this->actingAs($admin)->post(route('admin.backups.download', $backup['name']), ['password' => self::PASSWORD]);

        $response->assertOk()->assertDownload($backup['name']);
        $this->assertSame('application/gzip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('-- NovaCreneaux', gzdecode(file_get_contents($response->baseResponse->getFile()->getPathname())));
        $this->assertTrue(ActivityLog::where('action', 'Sauvegarde de la base téléchargée')->where('context', $backup['name'])->exists());
    }

    public function test_deleting_needs_the_admin_password(): void
    {
        $admin = $this->admin();
        $backup = $this->aBackup();

        $this->actingAs($admin)->delete(route('admin.backups.destroy', $backup['name']), ['password' => 'mauvais'])->assertSessionHas('backup_error');
        $this->assertCount(1, $this->manager()->list());

        $this->actingAs($admin)->delete(route('admin.backups.destroy', $backup['name']), ['password' => self::PASSWORD])->assertSessionHas('backup_success');
        $this->assertCount(0, $this->manager()->list());
        $this->assertTrue(ActivityLog::where('action', 'Sauvegarde de la base supprimée')->exists());
    }

    public function test_unknown_or_forged_names_are_not_found_even_with_the_right_password(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // la limite d'essais a son propre test
        $admin = $this->admin();
        $this->aBackup();

        foreach (['..', 'nova-20200101-000000-manual-aaaaaaaaaaaaaaaa.sql.gz', '.env', 'notes.txt'] as $name) {
            $this->actingAs($admin)->post(route('admin.backups.download', $name), ['password' => self::PASSWORD])->assertNotFound();
            $this->actingAs($admin)->delete(route('admin.backups.destroy', $name), ['password' => self::PASSWORD])->assertNotFound();
        }
        $this->actingAs($admin)->post('/admin/backups/..%2F..%2F.env/download', ['password' => self::PASSWORD])->assertNotFound();

        $this->assertCount(1, $this->manager()->list());
    }

    public function test_password_guessing_is_rate_limited(): void
    {
        $admin = $this->admin();
        $backup = $this->aBackup();

        $statuses = [];
        for ($i = 0; $i < 8; $i++) {
            $statuses[] = $this->actingAs($admin)->post(route('admin.backups.download', $backup['name']), ['password' => "essai{$i}"])->getStatusCode();
        }

        $this->assertSame([302, 302, 302, 302, 302, 302, 429, 429], $statuses);
    }

    public function test_the_scheduled_command_creates_an_automatic_backup_once_per_occurrence(): void
    {
        $this->artisan('backup:run-due')->assertSuccessful();
        $this->artisan('backup:run-due')->assertSuccessful();

        $backups = $this->manager()->list();
        $this->assertCount(1, $backups);
        $this->assertSame(BackupManager::AUTOMATIC, $backups[0]['trigger']);
    }

    public function test_without_cron_the_first_visit_triggers_a_due_backup_after_the_response(): void
    {
        config(['nova.backups.on_request' => true]);

        $this->get(route('login'))->assertOk();
        $this->get(route('login'))->assertOk();

        $this->assertCount(1, $this->manager()->list());
    }

    public function test_the_visit_trigger_does_nothing_when_disabled_by_configuration_or_by_the_administrator(): void
    {
        $this->get(route('login'))->assertOk();
        $this->assertCount(0, $this->manager()->list());

        config(['nova.backups.on_request' => true]);
        app(SettingService::class)->set('backup_enabled', '0');
        $this->get(route('login'))->assertOk();

        $this->assertCount(0, $this->manager()->list());
    }
}
