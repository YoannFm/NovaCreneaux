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
    public function update(Request $request, Resource $resource, Slot $slot) {
        $slot->update($this->validated($request));
        return redirect()->route('admin.slots.index',$resource)->with('success','Créneau mis à jour.');
    }
    public function destroy(ReservationService $reservations, Resource $resource, Slot $slot) {
        $cancelled = $reservations->cancelUpcomingForSlots([$slot->id], "Le créneau {$slot->name} de {$resource->name} n'existe plus.");
        $slot->delete();
        $note = $cancelled > 0 ? " {$cancelled} réservation(s) à venir annulée(s), les élèves ont été prévenus." : '';
        return redirect()->route('admin.slots.index',$resource)->with('success','Créneau supprimé.' . $note);
    }
    private function validated(Request $request): array {
        $data = $request->validate([
            'name'=>'required|string|max:50',
            'start_time'=>'required|date_format:H:i',
            'end_time'=>'required|date_format:H:i|after:start_time',
            'max_capacity'=>'required|integer|min:1|max:500',
            'is_active'=>'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
