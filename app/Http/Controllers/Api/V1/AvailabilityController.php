<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Services\AvailabilityService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(private AvailabilityService $availability, private ReservationService $reservations) {}

    /** Places libres d'une date, et pour chaque creneau si le compte du jeton peut le reserver (memes regles que le site). */
    public function __invoke(Request $request): JsonResponse
    {
        $params = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'resource_id' => ['nullable', 'integer'],
        ]);
        $date = $params['date'];
        $user = $request->user();

        $resources = Resource::with('activeSlots')
            ->where('is_active', true)
            ->when($params['resource_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->orderBy('name')
            ->get();

        $free = $this->availability->freeSpots($resources->flatMap->activeSlots, [$date]);

        return response()->json(['data' => [
            'date' => $date,
            'resources' => $resources->map(fn (Resource $resource) => [
                'id' => $resource->id,
                'name' => $resource->name,
                'slots' => $resource->activeSlots->sortBy('start_time')->values()->map(function ($slot) use ($resource, $free, $date, $user) {
                    $slot->setRelation('resource', $resource);
                    $failure = $this->reservations->checkBookable($user, $slot, $date);

                    return [
                        'id' => $slot->id,
                        'name' => $slot->name,
                        'start_time' => substr($slot->start_time, 0, 5),
                        'end_time' => substr($slot->end_time, 0, 5),
                        'capacity' => $slot->max_capacity,
                        'free_spots' => $free[$slot->id][$date],
                        'bookable' => $failure === null,
                        'reason_code' => $failure['code'] ?? null,
                    ];
                }),
            ]),
        ]]);
    }
}
