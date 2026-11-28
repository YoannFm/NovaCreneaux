<?php
namespace App\Services\Backup;

use App\Services\SettingService;
use Carbon\CarbonImmutable;

/** Lecture et ecriture des reglages de sauvegarde et de l'etat des executions automatiques. Valeurs par defaut : tous les jours a 03:00, 7 sauvegardes gardees. */
class BackupSettings {
    private const DEFAULT_TIME = '03:00';
    private const DEFAULT_WEEKDAY = 1;
    private const DEFAULT_KEEP = 7;

    public function __construct(private SettingService $settings) {}

    public function schedule(): BackupSchedule {
        $frequency = $this->settings->get('backup_frequency', BackupSchedule::DAILY);
        $time = $this->settings->get('backup_time', self::DEFAULT_TIME);
        $weekday = (int) $this->settings->get('backup_weekday', self::DEFAULT_WEEKDAY);
        $keep = (int) $this->settings->get('backup_keep', self::DEFAULT_KEEP);

        // Une valeur alteree en base ne doit jamais empecher le calcul
        return new BackupSchedule(
            enabled: $this->settings->get('backup_enabled', '1') === '1',
            frequency: array_key_exists($frequency, BackupSchedule::FREQUENCIES) ? $frequency : BackupSchedule::DAILY,
            time: preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $time) ? $time : self::DEFAULT_TIME,
            weekday: array_key_exists($weekday, BackupSchedule::WEEKDAYS) ? $weekday : self::DEFAULT_WEEKDAY,
            keep: min(max($keep, 1), 90),
        );
    }

    public function saveSchedule(bool $enabled, string $frequency, string $time, int $weekday, int $keep): void {
        $this->settings->set('backup_enabled', $enabled ? '1' : '0');
        $this->settings->set('backup_frequency', $frequency);
        $this->settings->set('backup_time', $time);
        $this->settings->set('backup_weekday', (string) $weekday);
        $this->settings->set('backup_keep', (string) $keep);
    }

    public function lastAttemptAt(): ?CarbonImmutable {
        return $this->date('backup_last_attempt_at');
    }

    public function lastSuccessAt(): ?CarbonImmutable {
        return $this->date('backup_last_success_at');
    }

    public function lastError(): ?string {
        return $this->settings->get('backup_last_error') ?: null;
    }

    public function recordAttempt(CarbonImmutable $at): void {
        $this->settings->set('backup_last_attempt_at', $at->toIso8601String());
    }

    public function recordSuccess(CarbonImmutable $at): void {
        $this->settings->set('backup_last_success_at', $at->toIso8601String());
        $this->settings->set('backup_last_error', '');
    }

    public function recordFailure(string $message): void {
        $this->settings->set('backup_last_error', $message);
    }

    private function date(string $key): ?CarbonImmutable {
        $value = $this->settings->get($key);

        return $value ? CarbonImmutable::parse($value) : null;
    }
}
