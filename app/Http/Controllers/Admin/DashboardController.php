<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Http\Request;
class DashboardController extends Controller {
    /**
     * Point d'entree de tout le personnel : chaque chiffre et la liste des dernieres reservations ne s'affichent
     * qu'avec la permission de la page correspondante (sinon un membre charge des pages voyait les reservations).
     */
    public function index(Request $request) {
        $user = $request->user();
        $cards = [];
        if ($user->hasPermission(Permission::USERS)) {
            $cards[] = ['label' => 'Utilisateurs', 'value' => User::where('is_admin', false)->count(), 'icon' => 'people', 'color' => 'primary', 'route' => route('admin.users.index')];
        }
        if ($user->hasPermission(Permission::RESOURCES)) {
            $cards[] = ['label' => 'Ressources', 'value' => Resource::count(), 'icon' => 'collection', 'color' => 'success', 'route' => route('admin.resources.index')];
            $cards[] = ['label' => 'Créneaux', 'value' => Slot::count(), 'icon' => 'clock', 'color' => 'warning', 'route' => route('admin.resources.index')];
        }
        $recent = null;
        if ($user->hasPermission(Permission::RESERVATIONS)) {
            $cards[] = ['label' => "Réservations aujourd'hui", 'value' => Reservation::whereDate('reservation_date', today())->count(), 'icon' => 'calendar-check', 'color' => 'info', 'route' => route('admin.reservations.index')];
            $cards[] = ['label' => 'Total réservations', 'value' => Reservation::count(), 'icon' => 'bar-chart', 'color' => 'danger', 'route' => route('admin.reservations.index')];
            $recent = Reservation::with(['user', 'slot.resource'])->orderByDesc('created_at')->limit(10)->get();
        }
        return view('admin.dashboard', compact('cards', 'recent'));
    }
}
