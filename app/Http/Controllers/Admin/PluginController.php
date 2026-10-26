<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Plugins\Catalog;
use App\Plugins\PluginException;
use App\Plugins\PluginInstaller;
use App\Plugins\PluginManager;
use Illuminate\Http\RedirectResponse;

/** Extensions : liste, catalogue, installation, activation (reservee aux administrateurs : une extension est du code). */
class PluginController extends Controller
{
    public function __construct(private PluginManager $plugins, private Catalog $catalog, private PluginInstaller $installer) {}

    public function index()
    {
        $catalog = $this->catalog->get();
        $installed = $this->plugins->all();

        return view('admin.plugins.index', [
            'installed' => $installed,
            'catalog' => $catalog,
            'available' => array_diff_key($catalog['plugins'], $installed),
            'updates' => array_filter($catalog['plugins'], fn (array $entry) => isset($installed[$entry['id']])
                && $installed[$entry['id']]->version() !== null && version_compare($entry['version'], $installed[$entry['id']]->version(), '>')),
            'signingConfigured' => $this->catalog->signingConfigured(),
            'unsignedAllowed' => (bool) config('nova.plugins.allow_unsigned'),
            'apiVersion' => PluginManager::API_VERSION,
        ]);
    }

    public function refresh(): RedirectResponse
    {
        $catalog = $this->catalog->get(true);

        return $catalog['error'] ? back()->with('error', $catalog['error']) : back()->with('success', 'Catalogue actualisé.');
    }

    public function install(string $plugin): RedirectResponse
    {
        $entry = $this->catalog->get()['plugins'][$plugin] ?? null;
        if ($entry === null) {
            return back()->with('error', "Cette extension n'est pas dans le catalogue.");
        }

        return $this->attempt(function () use ($entry) {
            @set_time_limit(180);
            $installed = $this->installer->install($entry);

            return "{$installed->name()} {$installed->version()} installée. Activez-la pour l'utiliser.";
        });
    }

    public function enable(string $plugin): RedirectResponse
    {
        return $this->attempt(function () use ($plugin) {
            $this->plugins->enable($plugin);

            return 'Extension activée.';
        });
    }

    public function disable(string $plugin): RedirectResponse
    {
        return $this->attempt(function () use ($plugin) {
            $this->plugins->disable($plugin);

            return 'Extension désactivée.';
        });
    }

    public function destroy(string $plugin): RedirectResponse
    {
        return $this->attempt(function () use ($plugin) {
            $this->plugins->delete($plugin);

            return "Extension supprimée. Ses tables de base de données ont été conservées.";
        });
    }

    private function attempt(callable $action): RedirectResponse
    {
        try {
            return redirect()->route('admin.plugins.index')->with('success', $action());
        } catch (PluginException $e) {
            return redirect()->route('admin.plugins.index')->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.plugins.index')->with('error', 'Erreur inattendue : ' . $e->getMessage());
        }
    }
}
