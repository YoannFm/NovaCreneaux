<?php
namespace App\Console\Commands;

use App\Services\Backup\AutomaticBackup;
use Illuminate\Console\Command;

class RunScheduledBackup extends Command
{
    protected $signature   = 'backup:run-due';
    protected $description = 'Lance la sauvegarde automatique de la base si elle est due';

    public function handle(AutomaticBackup $automatic): int
    {
        $backup = $automatic->runIfDue();
        if ($backup) {
            $this->info("Sauvegarde créée : {$backup['name']}");
        }

        return Command::SUCCESS;
    }
}
