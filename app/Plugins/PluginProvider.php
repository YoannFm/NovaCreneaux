<?php

namespace App\Plugins;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Classe de base du fournisseur de services d'une extension. Fournit ce dont une extension a besoin et rien de plus :
 * vues et routes d'administration protegees par une de ses permissions. Les migrations de database/migrations sont
 * lancees par le site a l'activation et a l'installation d'une mise a jour de l'extension, jamais par la mise a jour du site.
 */
abstract class PluginProvider extends ServiceProvider
{
    protected Plugin $plugin;

    public function bindPlugin(Plugin $plugin): void
    {
        $this->plugin = $plugin;
    }

    /** Vues de resources/views, utilisables avec le prefixe de l'extension : `view('stats::index')`. */
    protected function loadViews(): void
    {
        $this->loadViewsFrom($this->plugin->path . '/resources/views', $this->plugin->id);
    }

    /**
     * Routes d'administration : `/admin/p/{id}/...`, noms `admin.plugin.{id}....`, reservees au personnel qui a la
     * permission donnee (cle declaree dans plugin.json) ou, avec `Manifest::ADMIN_ONLY`, aux administrateurs.
     */
    protected function adminRoutes(string $file, string $permission): void
    {
        if ($permission !== Manifest::ADMIN_ONLY && ! array_key_exists($permission, $this->plugin->permissions())) {
            throw new \LogicException("Permission « {$permission} » non déclarée dans plugin.json.");
        }
        $guard = $permission === Manifest::ADMIN_ONLY ? 'admin' : "permission:{$this->plugin->id}.{$permission}";

        Route::middleware(['web', 'auth', 'staff', $guard])
            ->prefix("admin/p/{$this->plugin->id}")
            ->name("admin.plugin.{$this->plugin->id}.")
            ->group($this->plugin->path . '/' . $file);
    }
}
