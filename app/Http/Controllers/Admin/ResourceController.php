<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Services\ReservationService;
use Illuminate\Http\Request;
class ResourceController extends Controller {
    public function index() {
        return view('admin.resources.index', ['resources' => Resource::withCount('slots')->get()]);
    }
    public function create() {
        return view('admin.resources.form', ['resource' => new Resource()]);
    }
    public function store(Request $request) {
        Resource::create($this->validated($request));
        return redirect()->route('admin.resources.index')->with('success','Ressource créée.');
    }
    public function edit(Resource $resource) {
        return view('admin.resources.form', compact('resource'));
    }
    public function update(Request $request, Resource $resource) {
        $resource->update($this->validated($request));
        return redirect()->route('admin.resources.index')->with('success','Ressource mise à jour.');
    }
    public function destroy(ReservationService $reservations, Resource $resource) {
        $cancelled = $reservations->cancelUpcomingForSlots($resource->slots()->pluck('id'), "La ressource {$resource->name} n'est plus disponible.");
        // Ses creneaux sont supprimes avec elle (en base, ils restent pour l'historique)
        $resource->slots()->delete();
        $resource->delete();
        return redirect()->route('admin.resources.index')->with('success', 'Ressource supprimée.' . ReservationService::cancelledNote($cancelled));
    }
    private function validated(Request $request): array {
        $data = $request->validate(Resource::rules());
        $data['is_active'] = $request->boolean('is_active');
        $data['closed_days'] = $data['closed_days'] ?? [];
        return $data;
    }
}
