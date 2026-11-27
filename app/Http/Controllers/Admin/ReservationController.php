<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PdfRenderer;
use App\Services\ReservationService;
use App\Support\CsvCell;
use App\Support\Customization;
use App\Support\QueryFilters;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Support\RequestText;
class ReservationController extends Controller {
    /** Mises en page de l'export PDF, au choix de la personne qui exporte. */
    public const PDF_LAYOUTS = ['list' => 'Une seule liste', 'slots' => 'Un bloc par créneau', 'pages' => 'Une page par créneau'];

    public function __construct(private ReservationService $service, private PdfRenderer $pdf) {}

    public function index(Request $request) {
        // La date sert aussi au nom du fichier exporte : format strict. Un filtre invalide est ignore (QueryFilters)
        $filters = QueryFilters::valid($request, [
            'date' => 'date_format:Y-m-d',
            'resource_id' => 'integer',
            'layout' => 'in:' . implode(',', array_keys(self::PDF_LAYOUTS)),
        ]);
        $date = $filters['date'] ?? now()->toDateString();
        $resourceId = $filters['resource_id'] ?? null;
        $query = Reservation::with(['user.role','slot.resource'])->whereDate('reservation_date',$date);
        if ($resourceId) $query->whereHas('slot', fn($q) => $q->where('resource_id',$resourceId));
        $reservations = $query->orderBy('created_at')->get();
        $resources = Resource::orderBy('name')->get();
        if ($request->get('export') === 'csv') return $this->exportCsv($reservations,$date);
        if ($request->get('export') === 'pdf') return $this->exportPdf($reservations, $date, $resources->firstWhere('id', $resourceId), $filters['layout'] ?? 'list');

        return view('admin.reservations.index', [
            'date' => Carbon::parse($date),
            'resourceId' => $resourceId,
            'resources' => $resources,
            'planning' => $this->planning($reservations, $resources, $resourceId),
            'total' => $reservations->count(),
            'pdfLayouts' => self::PDF_LAYOUTS,
        ]);
    }

    /**
     * Ressources a afficher, chacune avec ses creneaux (actifs, ou inactifs mais reserves ce jour) et les reservations
     * de chaque creneau : [['resource' => Resource, 'slots' => [['slot' => Slot, 'reservations' => Collection]]]].
     */
    private function planning($reservations, $resources, ?int $resourceId): array {
        $bySlot = $reservations->groupBy('slot_id');
        // Une ressource supprimee n'apparait que pour un jour ou elle a des reservations (historique)
        $deleted = $reservations->pluck('slot.resource')->filter(fn (Resource $resource) => $resource->trashed())->unique('id');
        $shown = $resources->concat($deleted)->sortBy('name');
        if ($resourceId) $shown = $shown->where('id', $resourceId);
        $slots = Slot::withTrashed()->whereIn('resource_id', $shown->pluck('id'))->orderBy('start_time')->get()->groupBy('resource_id');

        $planning = [];
        foreach ($shown as $resource) {
            $rows = ($slots[$resource->id] ?? collect())
                ->filter(fn (Slot $slot) => $slot->isOpenForBooking() || $bySlot->has($slot->id))
                ->map(fn (Slot $slot) => ['slot' => $slot, 'reservations' => ($bySlot[$slot->id] ?? collect())->sortBy(fn ($r) => self::sortKey($r->user->name))->values()])
                ->values();
            if ($rows->isNotEmpty() || $resourceId) {
                $planning[] = ['resource' => $resource, 'slots' => $rows];
            }
        }
        return $planning;
    }

    /** Ajout par le personnel, pour un compte qui ne peut pas reserver lui-meme (ReservationService::createByStaff). */
    public function store(Request $request) {
        // Erreurs dans le sac "add" : la fenetre d'ajout se rouvre avec la saisie
        $data = $request->validateWithBag('add', [
            'user_id' => 'required|integer|exists:users,id',
            'slot_id' => ['required', 'integer', Slot::existsRule()],
            'date' => 'required|date_format:Y-m-d',
        ], [], ['user_id' => 'personne', 'slot_id' => 'créneau']);
        $user = User::findOrFail($data['user_id']);
        abort_unless($request->user()->canManage($user), 403);

        $result = $this->service->createByStaff($user, $data['slot_id'], $data['date']);
        if (!$result['ok']) {
            return back()->withErrors(['slot_id' => $result['error']], 'add')->withInput();
        }

        $slot = $result['reservation']->slot;
        ActivityLogger::log('Réservation ajoutée (admin)', "{$user->name} : {$slot->resource->name}, {$slot->name} le {$data['date']}");
        return back()->with('success', "{$user->name} est inscrit(e) : {$slot->resource->name}, {$slot->name}.");
    }

    /** Recherche d'un compte pour la fenetre d'ajout : 10 resultats au plus, seulement les comptes que l'on gere. */
    public function searchUsers(Request $request) {
        // Pas de validation (qui redirigerait au lieu de repondre en JSON) : une recherche trop courte ne renvoie rien
        $term = mb_substr(trim(RequestText::get($request, 'q')), 0, 100);
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }
        $staff = $request->user();
        $like = "%{$term}%";

        $users = User::with('role')
            ->withExists(['sanctions as is_suspended' => fn ($q) => $q->activeBan()])
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('classe', 'like', $like))
            ->orderBy('name')->limit(25)->get()
            ->filter(fn (User $user) => $staff->canManage($user))
            ->take(10);

        return response()->json($users->map(fn (User $user) => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'classe' => $user->classe, 'suspended' => (bool) $user->is_suspended,
        ])->values());
    }
    public function destroy(Request $request, Reservation $reservation) {
        // Meme regle que l'ajout : le personnel delegue ne touche pas aux reservations des administrateurs ni du personnel
        abort_unless($request->user()->canManage($reservation->user), 403);
        // Seance terminee : elle reste dans l'historique, et personne n'est prevenu d'une annulation sans objet
        if ($reservation->hasEnded()) {
            return back()->with('error', "Cette séance est terminée : la réservation reste dans l'historique.");
        }
        $reason = $request->validate(['reason' => 'nullable|string|max:300'])['reason'] ?? null;
        $this->service->cancelByAdmin($reservation, $reason);
        ActivityLogger::log('Réservation supprimée (admin)', "#{$reservation->id}" . ($reason ? ", motif : {$reason}" : ''));
        return back()->with('success','Réservation supprimée.');
    }
    /**
     * Liste a imprimer : classee par ressource, horaire du creneau puis nom. $layout (PDF_LAYOUTS) : une seule liste,
     * un bloc par creneau, ou une page par creneau (feuille d'appel avec une case de presence).
     */
    private function exportPdf($reservations, string $date, ?Resource $resource, string $layout) {
        ActivityLogger::log('Export PDF des réservations', $date . ($resource ? ", {$resource->name}" : '') . ($layout !== 'list' ? ', ' . mb_strtolower(self::PDF_LAYOUTS[$layout]) : ''));

        return $this->pdf->download('admin.reservations.pdf', [
            'reservations' => $reservations->sortBy([
                fn ($a, $b) => strcmp(self::sortKey($a->slot->resource->name), self::sortKey($b->slot->resource->name)),
                fn ($a, $b) => strcmp($a->slot->start_time, $b->slot->start_time),
                fn ($a, $b) => strcmp(self::sortKey($a->user->name), self::sortKey($b->user->name)),
            ])->values(),
            'date' => Carbon::parse($date),
            'resource' => $resource,
            'layout' => $layout,
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
