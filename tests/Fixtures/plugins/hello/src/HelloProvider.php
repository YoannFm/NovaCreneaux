<?php

namespace NovaPlugin\Hello;

use App\Plugins\PluginProvider;

class HelloProvider extends PluginProvider
{
    public function boot(): void
    {
        $this->loadViews();
        $this->adminRoutes('routes/admin.php', 'voir');
    }
}
