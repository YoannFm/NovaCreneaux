<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Services\AvailabilityService;
use App\Services\ReservationService;

class HomeController extends Controller
{
    public function __construct(private ReservationService $reservations, private AvailabilityService $availability) {}

    public function index()
    {
        $user  = auth()->user();
        $today = now()->toDateString();
        $resources = Resource::with('activeSlots')->where('is_active', true)->get();

        // Creneaux que l'utilisateur peut encore reserver aujourd'hui : memes regles que les boutons de la page de
        // reservation (heure de fermeture, creneau commence, places, limites...)
        $counts = $this->availability->freeSpots($resources->flatMap->activeSlots, [$today]);
        $slotsAvailableToday = collect($this->reservations->statuses($user, $resources, [$today], $counts))
            ->filter(fn (array $byDate) => $byDate[$today] === null)->count();

        // Prochaines reservations : celles dont le creneau n'est pas termine
        $upcomingAll = $user->upcomingReservations();
        $upcoming = $upcomingAll->take(3);
        $totalUpcoming = $upcomingAll->count();

        // Ressources actives avec dispo aujourd'hui
        $resourcesPreview = $resources->filter(fn($r) => $r->isOpenOn($today))->take(4);

        return view('home.index', compact(
            'slotsAvailableToday',
            'upcoming',
            'totalUpcoming',
            'resourcesPreview',
            'today',
        ));
    }
}
