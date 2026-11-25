<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\Configuration\ConfigurationExporter;
use App\Services\Configuration\ConfigurationImporter;
use App\Services\Configuration\ConfigurationTemplates;
use App\Services\Configuration\ImportReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Parametres > Configuration : export, import d'un fichier et modeles prets a importer (administrateurs). */
class ConfigurationController extends Controller
{
    public function __construct(private ConfigurationImporter $importer, private ConfigurationTemplates $templates) {}

    public function index()
    {
        return view('admin.settings.configuration', ['templates' => $this->templates->all()]);
    }

    public function export(Request $request, ConfigurationExporter $exporter): StreamedResponse|RedirectResponse
    {
        if ($refused = $this->refuseWithoutPassword($request, 'export')) {
            return $refused;
        }
        $json = json_encode($exporter->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $name = 'configuration-' . (Str::slug((string) setting('app_name', '')) ?: 'novacreneaux') . '-' . now()->format('Y-m-d') . '.json';
        ActivityLogger::log('Configuration exportée', $name);

        return response()->streamDownload(fn () => print($json), $name, ['Content-Type' => 'application/json']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validateWithBag('import', ['configuration' => 'required|file|max:1024'], [], ['configuration' => 'fichier']);
        if ($refused = $this->refuseWithoutPassword($request, 'import')) {
            return $refused;
        }
        $file = $request->file('configuration');
        $data = json_decode((string) file_get_contents($file->getRealPath()), true);

        return $this->apply($data, 'fichier ' . $file->getClientOriginalName());
    }

    public function template(Request $request, string $template): RedirectResponse
    {
        $data = $this->templates->find($template) ?? abort(404);
        if ($refused = $this->refuseWithoutPassword($request, 'modèle')) {
            return $refused;
        }

        return $this->apply($data, 'modèle ' . ($data['title'] ?? $template));
    }

    /** La configuration decrit tout le site : chaque action redemande le mot de passe de l'administrateur connecte. */
    private function refuseWithoutPassword(Request $request, string $action): ?RedirectResponse
    {
        if (Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
            return null;
        }
        ActivityLogger::log("Mot de passe refusé pour la configuration ({$action})");

        return back()->with('error', "Mot de passe incorrect : {$action} annulé.");
    }

    private function apply(mixed $data, string $source): RedirectResponse
    {
        try {
            $report = $this->importer->import($data, $source);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors(), 'import')->with('error', "Rien n'a été importé : le fichier n'est pas valide.");
        }

        return back()->with('success', $this->message($report));
    }

    private function message(ImportReport $report): string
    {
        $message = $report->changed() ? 'Configuration importée : ' . $report->summary() . '.' : 'Rien à changer : le site a déjà cette configuration.';
        if ($report->skipped()) {
            $message .= ' Ignoré : ' . implode(' ; ', $report->skipped()) . '.';
        }

        return $message;
    }
}
