<?php
namespace App\Console\Commands;
use App\Models\ActivityLog;
use Illuminate\Console\Command;

class PurgeOldLogs extends Command
{
    protected $signature   = 'logs:purge {--days=90 : Supprimer les logs plus anciens que N jours}';
    protected $description = 'Supprime les journaux d\'activité de plus de N jours';

    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $deleted = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("{$deleted} log(s) supprimé(s) (plus de {$days} jours).");
        return Command::SUCCESS;
    }
}
