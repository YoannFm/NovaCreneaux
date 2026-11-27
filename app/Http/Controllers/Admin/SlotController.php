<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\Slot;
use App\Services\ReservationService;
use Illuminate\Http\Request;
class SlotController extends Controller {
    public function index(Resource $resource) {
        return view('admin.slots.index', ['resource'=>$resource,'slots'=>$resource->slots()->withCount('reservations')->get()]);
    }
    public function create(Resource $resource) {
        return view('admin.slots.form', ['resource'=>$resource,'slot'=>new Slot()]);
    }
    public function store(Request $request, Resource $resource) {
        $resource->slots()->create($this->validated($request));
        return redirect()->route('admin.slots.index',$resource)->with('success','Créneau créé.');
    }
    public function edit(Resource $resource, Slot $slot) {
        return view('admin.slots.form', compact('resource','slot'));
    }
    public function update(ReservationService $reservations, Request $request, Resource $resource, Slot $slot) {
        [$previousStart, $previousEnd, $wasActive] = [$slot->start_time, $slot->end_time, $slot->is_active];
        $slot->update($this->validated($request));

        $message = 'Créneau mis à jour.';
        if ($slot->wasChanged(['start_time', 'end_time'])) {
            $warned = $reservations->notifyRescheduled($slot, $previousStart, $previousEnd);
            $message .= $warned > 0 ? " {$warned} personne(s) ayant réservé ont été prévenues du nouvel horaire." : '';
        }
        $deactivated = $wasActive && !$slot->is_active ? $reservations->countUpcomingForSlots([$slot->id]) : 0;
        $warning = $deactivated > 0
            ? "Créneau désactivé : {$deactivated} réservation(s) à venir sont conservées et les personnes n'ont pas été prévenues. Retirez-les depuis le planning si besoin."
            : $this->overbookedWarning($slot);

        return redirect()->route('admin.slots.index',$resource)->with('success', $message)->with('warning', $warning);
    }

    /**
     * Capacite descendue sous le nombre de reservations deja faites un jour a venir : rien n'est annule (le personnel
     * choisit qui retirer), mais il est averti, jour par jour. Null si aucun jour n'est en surnombre.
     */
    private function overbookedWarning(Slot $slot): ?string {
        $days = $slot->reservations()->whereDate('reservation_date', '>=', today())
            ->selectRaw('reservation_date, COUNT(*) as taken')->groupBy('reservation_date')
            ->havingRaw('COUNT(*) > ?', [$slot->max_capacity])->orderBy('reservation_date')->get();
        if ($days->isEmpty()) return null;

        $list = $days->map(fn ($day) => $day->reservation_date->isoFormat('dddd D MMMM') . " ({$day->taken} réservations)")->implode(', ');
        $places = $slot->max_capacity . ($slot->max_capacity > 1 ? ' places' : ' place');
        return "La capacité ({$places}) est inférieure aux réservations déjà faites : {$list}. Aucune réservation n'a été annulée : retirez-en depuis le planning si besoin.";
    }
    public function destroy(ReservationService $reservations, Resource $resource, Slot $slot) {
        $cancelled = $reservations->cancelUpcomingForSlots([$slot->id], "Le créneau {$slot->name} de {$resource->name} n'existe plus.");
        $slot->delete();
        $note = ReservationService::cancelledNote($cancelled);
        return redirect()->route('admin.slots.index',$resource)->with('success','Créneau supprimé.' . $note);
    }
    private function validated(Request $request): array {
        $data = $request->validate(Slot::rules());
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
