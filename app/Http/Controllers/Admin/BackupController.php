<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\Backup\BackupException;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupSchedule;
use App\Services\Backup\BackupSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Sauvegardes de la base (section de la page Mise a jour). Telecharger ou supprimer exige le mot de passe de l'administrateur. */
class BackupController extends Controller
{
    public function __construct(private BackupManager $backups, private BackupSettings $settings) {}

    public function store(): RedirectResponse
    {
        try {
            $backup = $this->backups->create(BackupManager::MANUAL);
        } catch (BackupException $e) {
            return $this->toBackups()->with('backup_error', $e->getMessage());
        }

        ActivityLogger::log('Sauvegarde de la base créée', $backup['name']);

        return $this->toBackups()->with('backup_success', 'Sauvegarde créée.');
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'frequency' => ['required', Rule::in(array_keys(BackupSchedule::FREQUENCIES))],
            'time'      => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'weekday'   => ['nullable', Rule::in(array_keys(BackupSchedule::WEEKDAYS))],
            'keep'      => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $enabled = $request->boolean('enabled');
        $this->settings->saveSchedule($enabled, $data['frequency'], $data['time'], (int) ($data['weekday'] ?? 1), (int) $data['keep']);
        ActivityLogger::log('Planification des sauvegardes modifiée', $enabled ? "{$data['frequency']} à {$data['time']}" : 'désactivée');

        return $this->toBackups()->with('backup_success', 'Planification enregistrée.');
    }

    public function download(Request $request, string $name): BinaryFileResponse|RedirectResponse
    {
        if (!$this->passwordIsValid($request)) {
            return $this->refused('téléchargement', $name);
        }

        $path = $this->pathOrFail($name);
        ActivityLogger::log('Sauvegarde de la base téléchargée', $name);

        return response()->download($path, $name, ['Content-Type' => 'application/gzip', 'Cache-Control' => 'no-store']);
    }

    public function destroy(Request $request, string $name): RedirectResponse
    {
        if (!$this->passwordIsValid($request)) {
            return $this->refused('suppression', $name);
        }

        $this->pathOrFail($name);
        $this->backups->delete($name);
        ActivityLogger::log('Sauvegarde de la base supprimée', $name);

        return $this->toBackups()->with('backup_success', 'Sauvegarde supprimée.');
    }

    private function passwordIsValid(Request $request): bool
    {
        return Hash::check((string) $request->input('password'), $request->user()->password);
    }

    private function refused(string $action, string $name): RedirectResponse
    {
        ActivityLogger::log("Mot de passe refusé pour la sauvegarde ({$action})", $name);

        return $this->toBackups()->with('backup_error', "Mot de passe incorrect : {$action} annulé.");
    }

    private function pathOrFail(string $name): string
    {
        try {
            return $this->backups->path($name);
        } catch (BackupException) {
            abort(404);
        }
    }

    private function toBackups(): RedirectResponse
    {
        return redirect(route('admin.update.index') . '#sauvegardes');
    }
}
