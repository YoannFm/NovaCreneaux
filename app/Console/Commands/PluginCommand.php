<?php

namespace App\Console\Commands;

use App\Plugins\PluginException;
use App\Plugins\PluginManager;
use Illuminate\Console\Command;

/** Gestion des extensions en ligne de commande : utile si une extension rend l'administration inaccessible. */
class PluginCommand extends Command
{
    protected $signature = 'plugin:manage {action : list, enable ou disable} {id? : identifiant de l\'extension}';
    protected $description = 'Liste, active ou désactive les extensions';

    public function handle(PluginManager $plugins): int
    {
        $action = $this->argument('action');
        if ($action === 'list') {
            $this->table(['Id', 'Nom', 'Version', 'État'], array_map(fn ($plugin) => [
                $plugin->id, $plugin->name(), $plugin->version() ?? '-',
                $plugin->enabled ? 'active' : ($plugin->failure ? "en échec : {$plugin->failure}" : ($plugin->problems ? 'invalide' : 'inactive')),
            ], array_values($plugins->all())));

            return self::SUCCESS;
        }

        $id = $this->argument('id');
        if (! in_array($action, ['enable', 'disable'], true) || $id === null) {
            $this->error('Usage : plugin:manage list | enable ID | disable ID');

            return self::FAILURE;
        }
        try {
            $action === 'enable' ? $plugins->enable($id) : $plugins->disable($id);
        } catch (PluginException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info($action === 'enable' ? 'Extension activée.' : 'Extension désactivée.');

        return self::SUCCESS;
    }
}
