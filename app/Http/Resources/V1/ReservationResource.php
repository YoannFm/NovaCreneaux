<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Reservation */
class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->reservation_date->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
            'slot' => new SlotResource($this->slot),
            'resource' => [
                'id' => $this->slot->resource->id,
                'name' => $this->slot->resource->name,
            ],
        ];
    }
}
