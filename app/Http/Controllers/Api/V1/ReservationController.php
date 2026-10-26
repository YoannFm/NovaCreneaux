<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\ApiError;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ReservationResource;
use App\Services\ActivityLogger;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Toutes les operations portent sur les reservations du compte du jeton, jamais sur celles d'un autre utilisateur. */
class ReservationController extends Controller
{
    public function __construct(private ReservationService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $params = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $reservations = $request->user()->reservations()
            ->with('slot.resource')
            ->when($params['from'] ?? null, fn ($query, $from) => $query->where('reservation_date', '>=', $from))
            ->when($params['to'] ?? null, fn ($query, $to) => $query->where('reservation_date', '<=', $to))
            ->orderByDesc('reservation_date')
            ->orderByDesc('id')
            ->paginate($params['per_page'] ?? 25)
            ->withQueryString();

        return ReservationResource::collection($reservations);
    }

    public function show(Request $request, int $reservation): ReservationResource
    {
        return new ReservationResource($request->user()->reservations()->with('slot.resource')->findOrFail($reservation));
    }

    /** Memes regles que le site. Un nouvel envoi de la meme demande renvoie 409 already_booked : aucun doublon possible. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $result = $this->service->create($request->user(), $data['slot_id'], $data['date']);
        if (!$result['ok']) {
            return ApiError::fromReservationFailure($result);
        }

        $reservation = $result['reservation'];
        ActivityLogger::log('Réservation créée (API)', "{$reservation->slot->resource->name} - {$reservation->slot->name} le {$data['date']}, jeton #{$request->user()->currentAccessToken()->id}");

        return (new ReservationResource($reservation))->response()->setStatusCode(201)
            ->header('Location', route('api.v1.reservations.show', $reservation->id));
    }

    public function destroy(Request $request, int $reservation): JsonResponse|Response
    {
        $result = $this->service->cancel($request->user(), $reservation);
        if (!$result['ok']) {
            return ApiError::fromReservationFailure($result);
        }

        ActivityLogger::log('Réservation annulée (API)', "#{$reservation}, jeton #{$request->user()->currentAccessToken()->id}");

        return response()->noContent();
    }
}
