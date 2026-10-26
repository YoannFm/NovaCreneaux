<?php
namespace App\Services\Backup;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Calendrier des sauvegardes automatiques : quand a eu lieu la derniere echeance, quand tombe la prochaine. */
final class BackupSchedule {
    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';
    public const MONTHLY = 'monthly';

    public const FREQUENCIES = [
        self::DAILY => 'Tous les jours',
        self::WEEKLY => 'Toutes les semaines',
        self::MONTHLY => 'Tous les mois (le 1er)',
    ];

    /** Numero de jour de Carbon (0 = dimanche) vers son libelle, le lundi en premier. */
    public const WEEKDAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 0 => 'Dimanche'];

    public function __construct(
        public readonly bool $enabled,
        public readonly string $frequency,
        public readonly string $time,
        public readonly int $weekday,
        public readonly int $keep,
    ) {}

    /** Derniere echeance passee ou en cours (<= $now). */
    public function latestOccurrence(CarbonInterface $now): CarbonImmutable {
        $now = CarbonImmutable::instance($now);

        return match ($this->frequency) {
            self::WEEKLY => $this->atTime($now->subDays(($now->dayOfWeek - $this->weekday + 7) % 7), $now, fn ($d) => $d->subWeek()),
            self::MONTHLY => $this->atTime($now->startOfMonth(), $now, fn ($d) => $d->subMonthNoOverflow()->startOfMonth()),
            default => $this->atTime($now, $now, fn ($d) => $d->subDay()),
        };
    }

    public function nextOccurrence(CarbonInterface $now): CarbonImmutable {
        $latest = $this->latestOccurrence($now);

        return match ($this->frequency) {
            self::WEEKLY => $latest->addWeek(),
            self::MONTHLY => $latest->addMonthNoOverflow()->startOfMonth()->setTimeFromTimeString($this->time),
            default => $latest->addDay(),
        };
    }

    /** Place l'heure du jour sur $day ; si le resultat est dans le futur, recule d'une periode. */
    private function atTime(CarbonImmutable $day, CarbonImmutable $now, callable $stepBack): CarbonImmutable {
        $candidate = $day->setTimeFromTimeString($this->time);

        return $candidate > $now ? $stepBack($candidate)->setTimeFromTimeString($this->time) : $candidate;
    }
}
