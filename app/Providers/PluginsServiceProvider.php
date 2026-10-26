<?php

namespace App\Providers;

use App\Plugins\PluginManager;
use Illuminate\Support\ServiceProvider;

/** Charge les extensions actives. Declare avant tout autre fournisseur qui en depend (permissions, menu). */
class PluginsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginManager::class);
        $this->app->make(PluginManager::class)->load($this->app);
    }
}
