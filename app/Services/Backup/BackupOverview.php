<?php
namespace App\Services\Backup;

use Carbon\CarbonImmutable;

/** Donnees affichees par la section "Sauvegardes" de la page de mise a jour. */
class BackupOverview {
    public function __construct(private BackupManager $backups, private BackupSettings $settings) {}

    public function get(): array {
        $schedule = $this->settings->schedule();
        $backups = $this->backups->list();

        return [
            'schedule' => $schedule,
            'next' => $schedule->enabled ? $schedule->nextOccurrence(CarbonImmutable::now()) : null,
            'lastSuccess' => $this->settings->lastSuccessAt(),
            'lastError' => $this->settings->lastError(),
            'backups' => $backups,
            'totalSize' => array_sum(array_column($backups, 'size')),
        ];
    }
}
