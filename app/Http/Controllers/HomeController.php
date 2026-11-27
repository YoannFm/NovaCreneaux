<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\Slot;
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
        $statuses = $this->reservations->statuses($user, $resources, [$today], $counts);
        $bookable = fn (Slot $slot) => $statuses[$slot->id][$today] === null;
        $slotsAvailableToday = $resources->flatMap->activeSlots->filter($bookable)->count();
        // Ressources ou il reste au moins un creneau reservable aujourd'hui (meme regle que le compteur de creneaux)
        $bookableResources = $resources->filter(fn (Resource $resource) => $resource->activeSlots->contains($bookable))->values();

        // Prochaines reservations : celles dont le creneau n'est pas termine
        $upcomingAll = $user->upcomingReservations();
        $upcoming = $upcomingAll->take(3);
        $totalUpcoming = $upcomingAll->count();

        return view('home.index', compact(
            'slotsAvailableToday',
            'upcoming',
            'totalUpcoming',
            'bookableResources',
            'today',
        ));
    }
}
