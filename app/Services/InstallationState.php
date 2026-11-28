<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Dit si l'assistant d'installation a deja ete execute.
 *
 * Le site est considere installe des qu'un verrou local existe (fichier ou APP_INSTALLED),
 * sans interroger la base : une panne de base ne doit jamais rouvrir l'installation. Sans verrou local, la base
 * dit installe si la table `installation` le dit ou si un administrateur existe.
 */
class InstallationState
{
    public function isInstalled(): bool
    {
        if (config('app.installed') || file_exists($this->lockPath())) {
            return true;
        }

        $installed = $this->databaseSaysInstalled();
        if ($installed) {
            $this->writeLock();
        }

        return $installed;
    }

    /** La ligne de la table `installation`, ou a defaut un administrateur existant (verrou perdu, installation interrompue). */
    private function databaseSaysInstalled(): bool
    {
        foreach ([
            fn () => DB::table('installation')->where('completed', true)->exists(),
            fn () => DB::table('users')->where('is_admin', true)->exists(),
        ] as $check) {
            try {
                if ($check()) {
                    return true;
                }
            } catch (\Throwable) {
                // Table absente ou base indisponible : cette verification ne dit rien
            }
        }

        return false;
    }

    public function markInstalled(): void
    {
        DB::table('installation')->truncate();
        DB::table('installation')->insert(['completed' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->writeLock();
    }

    public function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    private function writeLock(): void
    {
        @file_put_contents($this->lockPath(), now()->toIso8601String());
    }
}
