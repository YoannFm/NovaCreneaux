<?php

namespace Tests\Unit;

use App\Services\Backup\BackupSchedule;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BackupScheduleTest extends TestCase
{
    private function schedule(string $frequency, string $time = '03:00', int $weekday = 1): BackupSchedule
    {
        return new BackupSchedule(true, $frequency, $time, $weekday, 7);
    }

    public static function latestOccurrences(): array
    {
        return [
            'tous les jours, apres l heure' => [BackupSchedule::DAILY, '03:00', 1, '2026-10-26 10:00:00', '2026-10-26 03:00:00', '2026-10-27 03:00:00'],
            'tous les jours, avant l heure' => [BackupSchedule::DAILY, '03:00', 1, '2026-10-26 02:59:00', '2026-10-25 03:00:00', '2026-10-26 03:00:00'],
            'tous les jours, pile a l heure' => [BackupSchedule::DAILY, '03:00', 1, '2026-10-26 03:00:00', '2026-10-26 03:00:00', '2026-10-27 03:00:00'],
            'tous les jours, passage de mois' => [BackupSchedule::DAILY, '23:30', 1, '2026-11-01 00:10:00', '2026-10-31 23:30:00', '2026-11-01 23:30:00'],
            'chaque semaine, le jour meme apres l heure' => [BackupSchedule::WEEKLY, '03:00', 1, '2026-10-26 10:00:00', '2026-10-26 03:00:00', '2026-11-02 03:00:00'],
            'chaque semaine, le jour meme avant l heure' => [BackupSchedule::WEEKLY, '03:00', 1, '2026-10-26 02:00:00', '2026-10-19 03:00:00', '2026-10-26 03:00:00'],
            'chaque semaine, en milieu de semaine' => [BackupSchedule::WEEKLY, '03:00', 1, '2026-10-29 12:00:00', '2026-10-26 03:00:00', '2026-11-02 03:00:00'],
            'chaque semaine, le dimanche' => [BackupSchedule::WEEKLY, '04:00', 0, '2026-10-26 12:00:00', '2026-10-25 04:00:00', '2026-11-01 04:00:00'],
            'chaque mois, apres l heure' => [BackupSchedule::MONTHLY, '03:00', 1, '2026-10-26 10:00:00', '2026-10-01 03:00:00', '2026-11-01 03:00:00'],
            'chaque mois, le 1er avant l heure' => [BackupSchedule::MONTHLY, '03:00', 1, '2026-10-01 02:00:00', '2026-09-01 03:00:00', '2026-10-01 03:00:00'],
            'chaque mois, passage d annee' => [BackupSchedule::MONTHLY, '03:00', 1, '2026-12-15 10:00:00', '2026-12-01 03:00:00', '2027-01-01 03:00:00'],
        ];
    }

    #[DataProvider('latestOccurrences')]
    public function test_the_latest_and_next_occurrences(string $frequency, string $time, int $weekday, string $now, string $latest, string $next): void
    {
        $schedule = $this->schedule($frequency, $time, $weekday);
        $now = Carbon::parse($now);

        $this->assertSame($latest, $schedule->latestOccurrence($now)->format('Y-m-d H:i:s'));
        $this->assertSame($next, $schedule->nextOccurrence($now)->format('Y-m-d H:i:s'));
    }

    public function test_the_next_occurrence_is_always_after_now_and_the_latest_never_after_it(): void
    {
        foreach ([BackupSchedule::DAILY, BackupSchedule::WEEKLY, BackupSchedule::MONTHLY] as $frequency) {
            for ($minute = 0; $minute < 60 * 24 * 40; $minute += 197) {
                $now = Carbon::parse('2026-10-20 00:00:00')->addMinutes($minute);
                $schedule = $this->schedule($frequency, '03:15', 3);

                $this->assertLessThanOrEqual($now, $schedule->latestOccurrence($now), "{$frequency} {$now}");
                $this->assertGreaterThan($now, $schedule->nextOccurrence($now), "{$frequency} {$now}");
            }
        }
    }
}
