<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Resource;
use App\Services\ActivityLogger;
use App\Services\ReservationService;
use App\Support\CsvCell;
use Illuminate\Http\Request;
class ReservationController extends Controller {
    public function __construct(private ReservationService $service) {}

    public function index(Request $request) {
        $date = $request->get('date', now()->toDateString());
        $resourceId = $request->get('resource_id');
        $query = Reservation::with(['user','slot.resource'])->whereDate('reservation_date',$date);
        if ($resourceId) $query->whereHas('slot', fn($q) => $q->where('resource_id',$resourceId));
        $reservations = $query->orderBy('created_at')->get();
        $resources = Resource::orderBy('name')->get();
        if ($request->get('export') === 'csv') return $this->exportCsv($reservations,$date);
        return view('admin.reservations.index', compact('reservations','resources','date','resourceId'));
    }
    public function destroy(Reservation $reservation) {
        $this->service->cancelByAdmin($reservation);
        ActivityLogger::log('Réservation supprimée (admin)', "#{$reservation->id}");
        return back()->with('success','Réservation supprimée.');
    }
    private function exportCsv($reservations, string $date) {
        $headers = ['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>"attachment; filename=\"reservations_{$date}.csv\""];
        $callback = function() use ($reservations) {
            $out = fopen('php://output','w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out,['Nom','Email','Classe','Ressource','Créneau','Date','Réservé le'],';');
            foreach ($reservations as $r) {
                fputcsv($out,[CsvCell::safe($r->user->name),CsvCell::safe($r->user->email),CsvCell::safe($r->user->classe),CsvCell::safe($r->slot->resource->name),CsvCell::safe($r->slot->name),$r->reservation_date->format('d/m/Y'),$r->created_at->format('d/m/Y H:i')],';');
            }
            fclose($out);
        };
        return response()->stream($callback,200,$headers);
    }
}
