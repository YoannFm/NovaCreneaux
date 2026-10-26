<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Dit si l'assistant d'installation a deja ete execute.
 *
 * Le site est considere installe des qu'un verrou local existe (fichier ou APP_INSTALLED),
 * sans interroger la base : une panne de base ne doit jamais rouvrir l'installation.
 */
class InstallationState
{
    public function isInstalled(): bool
    {
        if (config('app.installed') || file_exists($this->lockPath())) {
            return true;
        }

        try {
            $installed = DB::table('installation')->where('completed', true)->exists();
        } catch (\Throwable) {
            return false;
        }

        if ($installed) {
            $this->writeLock();
        }

        return $installed;
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
