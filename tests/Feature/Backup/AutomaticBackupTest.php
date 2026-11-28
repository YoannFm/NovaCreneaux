<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\AutomaticBackup;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupSettings;
use App\Services\Backup\DatabaseDumper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AutomaticBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/nc-auto-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function backups(?DatabaseDumper $dumper = null): BackupManager
    {
        return new BackupManager($dumper ?? new DatabaseDumper(), $this->dir);
    }

    private function auto(?DatabaseDumper $dumper = null): AutomaticBackup
    {
        return new AutomaticBackup($this->backups($dumper), app(BackupSettings::class));
    }

    private function failingDumper(): DatabaseDumper
    {
        return new class extends DatabaseDumper {
            public function dump(string $path, ?string $connection = null): void
            {
                throw new \RuntimeException('echec simule');
            }
        };
    }

    public function test_the_default_schedule_is_every_day_at_three_and_a_backup_is_due_the_first_time(): void
    {
        $schedule = app(BackupSettings::class)->schedule();

        $this->assertTrue($schedule->enabled);
        $this->assertSame('daily', $schedule->frequency);
        $this->assertSame('03:00', $schedule->time);
        $this->assertSame(7, $schedule->keep);
        $this->assertTrue($this->auto()->isDue(Carbon::parse('2026-10-26 10:00:00')));
    }

    public function test_a_due_backup_runs_once_per_occurrence_and_records_its_success(): void
    {
        $auto = $this->auto();

        $first = $auto->runIfDue(Carbon::parse('2026-10-26 03:00:30'));
        $again = $auto->runIfDue(Carbon::parse('2026-10-26 03:01:30'));
        $later = $auto->runIfDue(Carbon::parse('2026-10-26 18:00:00'));
        $nextDay = $auto->runIfDue(Carbon::parse('2026-10-27 03:00:10'));

        $this->assertNotNull($first);
        $this->assertNull($again);
        $this->assertNull($later);
        $this->assertNotNull($nextDay);
        $this->assertCount(2, $this->backups()->list());
        $this->assertSame('2026-10-27 03:00:10', app(BackupSettings::class)->lastSuccessAt()->format('Y-m-d H:i:s'));
        $this->assertNull(app(BackupSettings::class)->lastError());
    }

    public function test_nothing_runs_when_automatic_backups_are_disabled(): void
    {
        app(BackupSettings::class)->saveSchedule(false, 'daily', '03:00', 1, 7);

        $this->assertNull($this->auto()->runIfDue(Carbon::parse('2026-10-26 10:00:00')));
        $this->assertSame([], $this->backups()->list());
    }

    public function test_a_catch_up_runs_after_the_planned_hour_but_only_one_backup_even_after_days_of_downtime(): void
    {
        app(BackupSettings::class)->recordSuccess(Carbon::parse('2026-10-20 03:00:00')->toImmutable());
        app(BackupSettings::class)->recordAttempt(Carbon::parse('2026-10-20 03:00:00')->toImmutable());

        $this->auto()->runIfDue(Carbon::parse('2026-10-26 07:30:00'));
        $second = $this->auto()->runIfDue(Carbon::parse('2026-10-26 07:31:00'));

        $this->assertNull($second);
        $this->assertCount(1, $this->backups()->list());
    }

    public function test_a_failure_is_recorded_without_exception_and_retried_after_an_hour(): void
    {
        $failing = $this->auto($this->failingDumper());

        $this->assertNull($failing->runIfDue(Carbon::parse('2026-10-26 03:00:00')));
        $this->assertNotNull(app(BackupSettings::class)->lastError());
        $this->assertNull($failing->runIfDue(Carbon::parse('2026-10-26 03:30:00')));
        $this->assertFalse($this->auto()->isDue(Carbon::parse('2026-10-26 03:30:00')));
        $this->assertTrue($this->auto()->isDue(Carbon::parse('2026-10-26 04:01:00')));

        $this->assertNotNull($this->auto()->runIfDue(Carbon::parse('2026-10-26 04:01:00')));
        $this->assertNull(app(BackupSettings::class)->lastError());
    }

    public function test_old_automatic_backups_are_pruned_after_each_run(): void
    {
        app(BackupSettings::class)->saveSchedule(true, 'daily', '03:00', 1, 2);
        $auto = $this->auto();

        foreach (['2026-10-24', '2026-10-25', '2026-10-26'] as $day) {
            Carbon::setTestNow("{$day} 03:00:05");
            $auto->runIfDue(Carbon::parse("{$day} 03:00:05"));
        }

        $this->assertCount(2, $this->backups()->list());
    }

    public function test_corrupted_settings_fall_back_to_safe_defaults(): void
    {
        app(\App\Services\SettingService::class)->set('backup_frequency', 'tous-les-quarts-dheure');
        app(\App\Services\SettingService::class)->set('backup_time', '25:99');
        app(\App\Services\SettingService::class)->set('backup_keep', '100000');

        $schedule = app(BackupSettings::class)->schedule();

        $this->assertSame('daily', $schedule->frequency);
        $this->assertSame('03:00', $schedule->time);
        $this->assertSame(90, $schedule->keep);
    }
}
