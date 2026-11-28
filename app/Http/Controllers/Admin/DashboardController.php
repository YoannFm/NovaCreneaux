<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\User;
class DashboardController extends Controller {
    public function index() {
        $stats = [
            'users'     => User::where('is_admin',false)->count(),
            'resources' => Resource::count(),
            'slots'     => Slot::count(),
            'today'     => Reservation::whereDate('reservation_date',today())->count(),
            'total'     => Reservation::count(),
        ];
        $recent = Reservation::with(['user','slot.resource'])->orderByDesc('created_at')->limit(10)->get();
        return view('admin.dashboard', compact('stats','recent'));
    }
}
