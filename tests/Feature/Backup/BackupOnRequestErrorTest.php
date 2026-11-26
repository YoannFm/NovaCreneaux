<?php

namespace Tests\Feature\Backup;

use App\Http\Middleware\RunDueBackupOnRequest;
use App\Services\Backup\AutomaticBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** Base injoignable : la sauvegarde lancee apres une requete echoue sans gener la page, et l'erreur n'est notee qu'une fois par heure. */
class BackupOnRequestErrorTest extends TestCase
{
    public function test_a_repeated_failure_is_reported_once_per_hour(): void
    {
        config(['nova.backups.on_request' => true]);
        Exceptions::fake();
        $this->mock(AutomaticBackup::class)->shouldReceive('runIfDue')->andThrow(new \RuntimeException('Connection refused'));
        $run = function () {
            Cache::forget('backup_due_check');
            (new RunDueBackupOnRequest())->terminate(Request::create('/'), new Response());
        };

        $run();
        $run();
        $this->travel(61)->minutes();
        $run();

        Exceptions::assertReportedCount(2);
    }
}
