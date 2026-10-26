<?php
namespace App\Http\Controllers;
use App\Models\Resource;
use App\Models\Slot;
use App\Services\ActivityLogger;
use App\Services\AvailabilityService;
use App\Services\ReservationService;
use Illuminate\Http\Request;
class ReservationController extends Controller {
    public function __construct(private ReservationService $service, private AvailabilityService $availability) {}
    public function index() {
        $resources = Resource::with(['activeSlots'])->where('is_active',true)->get();

        // Plage dynamique selon advance_days du rôle (défaut : 1 = today + demain)
        $user = auth()->user();
        $advanceDays = max(1, (int) ($user?->role?->advance_days ?: 1));

        $today = now()->toDateString();
        $dates = [];
        for ($i = 0; $i <= $advanceDays; $i++) {
            $dates[] = now()->addDays($i)->toDateString();
        }

        $counts = $this->availability->freeSpots($resources->flatMap->activeSlots, $dates);

        return view('reservations.index', compact('resources', 'today', 'dates', 'counts'));
    }
    public function store(Request $request) {
        $data = $request->validate([
            'slot_id'=>'required|integer|exists:slots,id',
            'reservation_date'=>'required|date|after_or_equal:today',
        ]);
        $result = $this->service->create(auth()->user(), $data['slot_id'], $data['reservation_date']);
        if (!$result['ok']) return back()->withErrors(['reservation'=>$result['error']]);
        $slot = Slot::with('resource')->find($data['slot_id']);
        ActivityLogger::log('Réservation créée', "{$slot?->resource?->name} — {$slot?->name} le {$data['reservation_date']}");
        return back()->with('success','Réservation confirmée !');
    }
    public function my() {
        $reservations = auth()->user()->reservations()
            ->with('slot.resource')->orderBy('reservation_date','desc')->paginate(20);
        return view('reservations.my', compact('reservations'));
    }
    public function destroy(int $id) {
        $result = $this->service->cancel(auth()->user(), $id);
        if (!$result['ok']) return back()->withErrors(['cancel'=>$result['error']]);
        ActivityLogger::log('Réservation annulée', "#{$id}");
        return back()->with('success','Réservation annulée.');
    }
}
