<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

/**
 * Transforme une vue Blade en fichier PDF a telecharger. Seul endroit qui configure dompdf :
 * aucune ressource distante (images, polices, feuilles de style) ni PHP dans le document,
 * le HTML doit tout contenir (styles en ligne).
 */
class PdfRenderer
{
    public function render(string $view, array $data = [], string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans'); // accents et symboles
        $options->setChroot(resource_path('views'));
        // Cache des mesures de polices hors de vendor (remplace par les mises a jour, pas forcement accessible en ecriture)
        $options->setFontCache($this->cacheDirectory());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function cacheDirectory(): string
    {
        $directory = storage_path('framework/cache/dompdf');
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }

    public function download(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        return response($this->render($view, $data, $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
