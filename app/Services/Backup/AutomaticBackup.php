<?php
namespace App\Services\Backup;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Decide si une sauvegarde automatique est due et la lance (planificateur Laravel ou, a defaut, premiere visite apres l'heure prevue). */
class AutomaticBackup {
    /** Delai avant de reessayer apres un echec. */
    private const RETRY_AFTER_MINUTES = 60;

    public function __construct(private BackupManager $backups, private BackupSettings $settings) {}

    public function isDue(?CarbonInterface $now = null): bool {
        $now = CarbonImmutable::instance($now ?? now());
        $schedule = $this->settings->schedule();
        if (!$schedule->enabled) {
            return false;
        }

        $occurrence = $schedule->latestOccurrence($now);
        $lastAttempt = $this->settings->lastAttemptAt();
        $lastSuccess = $this->settings->lastSuccessAt();

        // Une echeance se tente une fois ; apres un echec, on retente toutes les heures jusqu'a reussir
        $notAttemptedYet = $lastAttempt === null || $lastAttempt < $occurrence;
        $retry = $lastAttempt !== null
            && ($lastSuccess === null || $lastSuccess < $occurrence)
            && $lastAttempt <= $now->subMinutes(self::RETRY_AFTER_MINUTES);

        return $notAttemptedYet || $retry;
    }

    /** Lance la sauvegarde si elle est due. Ne leve jamais d'exception : appelee depuis une requete ou une tache planifiee. */
    public function runIfDue(?CarbonInterface $now = null): ?array {
        $now = CarbonImmutable::instance($now ?? now());

        try {
            if (!$this->isDue($now)) {
                return null;
            }

            // La tentative est enregistree avant : deux processus ne demarrent pas la meme echeance
            $this->settings->recordAttempt($now);

            try {
                $backup = $this->backups->create(BackupManager::AUTOMATIC);
                $this->backups->pruneAutomatic($this->settings->schedule()->keep);
                $this->settings->recordSuccess($now);

                return $backup;
            } catch (\Throwable $e) {
                report($e);
                $this->settings->recordFailure($e instanceof BackupException ? $e->getMessage() : 'La sauvegarde automatique a échoué, voir le journal du serveur.');
            }
        } catch (\Throwable $e) {
            // Base indisponible : rien a enregistrer, la prochaine requete ou minute reessaiera
            report($e);
        }

        return null;
    }
}
