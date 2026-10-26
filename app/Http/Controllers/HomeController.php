<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Slot;

class HomeController extends Controller
{
    public function index()
    {
        $user  = auth()->user();
        $today = now()->toDateString();

        // Créneaux disponibles aujourd'hui (toutes ressources actives)
        $resources = Resource::with('activeSlots')->where('is_active', true)->get();
        $slotsAvailableToday = 0;
        foreach ($resources as $resource) {
            if ($resource->isOpenOn($today)) {
                foreach ($resource->activeSlots as $slot) {
                    if ($slot->availableCountFor($today) > 0) {
                        $slotsAvailableToday++;
                    }
                }
            }
        }

        // Prochaines réservations de l'utilisateur
        $upcoming = $user->reservations()
            ->with('slot.resource')
            ->where('reservation_date', '>=', $today)
            ->orderBy('reservation_date')
            ->limit(3)
            ->get();

        $totalUpcoming = $user->reservations()
            ->where('reservation_date', '>=', $today)
            ->count();

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
