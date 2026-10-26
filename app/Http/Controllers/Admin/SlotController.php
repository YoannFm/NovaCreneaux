<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\Slot;
use App\Services\ActivityLogger;
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
        ActivityLogger::log('Créneau créé', "{$resource->name} — {$request->name}");
        return redirect()->route('admin.slots.index',$resource)->with('success','Créneau créé.');
    }
    public function edit(Resource $resource, Slot $slot) {
        return view('admin.slots.form', compact('resource','slot'));
    }
    public function update(Request $request, Resource $resource, Slot $slot) {
        $slot->update($this->validated($request));
        ActivityLogger::log('Créneau modifié', "{$resource->name} — {$slot->name}");
        return redirect()->route('admin.slots.index',$resource)->with('success','Créneau mis à jour.');
    }
    public function destroy(Resource $resource, Slot $slot) {
        ActivityLogger::log('Créneau supprimé', "{$resource->name} — {$slot->name}");
        $slot->delete();
        return redirect()->route('admin.slots.index',$resource)->with('success','Créneau supprimé.');
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
