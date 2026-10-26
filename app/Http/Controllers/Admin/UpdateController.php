<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Update\UpdateException;
use App\Services\Update\UpdateManager;
use Illuminate\Http\JsonResponse;

class UpdateController extends Controller
{
    public function __construct(private UpdateManager $updates) {}

    public function index()
    {
        return view('admin.update.index', ['status' => $this->updates->status()]);
    }

    /** Force une nouvelle lecture des releases GitHub. */
    public function check()
    {
        $status = $this->updates->status(true);

        if ($status['error']) {
            return back()->with('error', $status['error']);
        }

        return back()->with('success', $status['has_update'] ? "La version {$status['release']['version']} est disponible." : 'Vous utilisez déjà la dernière version.');
    }

    public function download(): JsonResponse
    {
        return $this->run(fn () => $this->updates->download(), 'Mise à jour téléchargée et signature vérifiée.');
    }

    public function install(): JsonResponse
    {
        return $this->run(fn () => $this->updates->install(), 'Fichiers installés. Finalisation en cours...');
    }

    public function finalize(): JsonResponse
    {
        return $this->run(fn () => $this->updates->finalize(), 'Mise à jour terminée.', flash: true);
    }

    public function restore(): JsonResponse
    {
        return $this->run(fn () => $this->updates->restore(), 'Fichiers de la version précédente restaurés.', flash: true);
    }

    /** Reponse JSON commune : un echec previsible devient un 422 avec un message lisible. */
    private function run(callable $action, string $success, bool $flash = false): JsonResponse
    {
        try {
            $data = $action();
        } catch (UpdateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Erreur inattendue : ' . $e->getMessage()], 500);
        }

        if ($flash) {
            session()->flash('success', $success);
        }

        return response()->json(['message' => $success, 'data' => $data]);
    }
}
