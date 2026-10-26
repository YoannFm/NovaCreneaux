<?php

namespace App\Console\Commands;

use App\Services\Webhook\WebhookRunner;
use Illuminate\Console\Command;

class DeliverWebhooks extends Command
{
    protected $signature = 'webhooks:deliver';
    protected $description = 'Envoie les webhooks en attente';

    public function handle(WebhookRunner $runner): int
    {
        $count = $runner->run(50);
        if ($count > 0) {
            $this->info("{$count} envoi(s) tenté(s).");
        }

        return Command::SUCCESS;
    }
}
