<?php

use App\Console\Commands\PurgeOldLogs;
use App\Console\Commands\RunScheduledBackup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(PurgeOldLogs::class)->daily();
// Avec le cron du serveur, la sauvegarde part exactement a l'heure choisie dans Admin > Mise a jour
Schedule::command(RunScheduledBackup::class)->everyMinute()->withoutOverlapping();
