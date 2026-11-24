<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Resource;
use App\Services\ActivityLogger;
use App\Services\PdfRenderer;
use App\Services\ReservationService;
use App\Support\CsvCell;
use App\Support\Customization;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ReservationController extends Controller {
    public function __construct(private ReservationService $service, private PdfRenderer $pdf) {}

    public function index(Request $request) {
        // La date sert aussi au nom du fichier exporte : format strict
        $filters = $request->validate(['date' => 'nullable|date_format:Y-m-d', 'resource_id' => 'nullable|integer']);
        $date = $filters['date'] ?? now()->toDateString();
        $resourceId = $filters['resource_id'] ?? null;
        $query = Reservation::with(['user','slot.resource'])->whereDate('reservation_date',$date);
        if ($resourceId) $query->whereHas('slot', fn($q) => $q->where('resource_id',$resourceId));
        $reservations = $query->orderBy('created_at')->get();
        $resources = Resource::orderBy('name')->get();
        if ($request->get('export') === 'csv') return $this->exportCsv($reservations,$date);
        if ($request->get('export') === 'pdf') return $this->exportPdf($reservations, $date, $resources->firstWhere('id', $resourceId));
        return view('admin.reservations.index', compact('reservations','resources','date','resourceId'));
    }
    public function destroy(Request $request, Reservation $reservation) {
        $reason = $request->validate(['reason' => 'nullable|string|max:300'])['reason'] ?? null;
        $this->service->cancelByAdmin($reservation, $reason);
        ActivityLogger::log('Réservation supprimée (admin)', "#{$reservation->id}" . ($reason ? ", motif : {$reason}" : ''));
        return back()->with('success','Réservation supprimée.');
    }
    /** Liste a imprimer : classee par ressource, horaire du creneau puis nom, comme une feuille d'appel. */
    private function exportPdf($reservations, string $date, ?Resource $resource) {
        ActivityLogger::log('Export PDF des réservations', $date . ($resource ? ", {$resource->name}" : ''));

        return $this->pdf->download('admin.reservations.pdf', [
            'reservations' => $reservations->sortBy([
                fn ($a, $b) => strcmp(self::sortKey($a->slot->resource->name), self::sortKey($b->slot->resource->name)),
                fn ($a, $b) => strcmp($a->slot->start_time, $b->slot->start_time),
                fn ($a, $b) => strcmp(self::sortKey($a->user->name), self::sortKey($b->user->name)),
            ])->values(),
            'date' => Carbon::parse($date),
            'resource' => $resource,
        ], "reservations_{$date}.pdf");
    }

    /** Ordre alphabetique sans tenir compte des accents ni des majuscules ("Élodie" avant "Noah"). */
    private static function sortKey(string $text): string {
        return Str::lower(Str::ascii($text));
    }

    private function exportCsv($reservations, string $date) {
        $headers = ['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>"attachment; filename=\"reservations_{$date}.csv\""];
        $callback = function() use ($reservations) {
            $out = fopen('php://output','w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out,['Nom','Email',Customization::get('group_label'),'Ressource','Créneau','Date','Réservé le'],';');
            foreach ($reservations as $r) {
                fputcsv($out,[CsvCell::safe($r->user->name),CsvCell::safe($r->user->email),CsvCell::safe($r->user->classe),CsvCell::safe($r->slot->resource->name),CsvCell::safe($r->slot->name),$r->reservation_date->format('d/m/Y'),$r->created_at->format('d/m/Y H:i')],';');
            }
            fclose($out);
        };
        return response()->stream($callback,200,$headers);
    }
}
