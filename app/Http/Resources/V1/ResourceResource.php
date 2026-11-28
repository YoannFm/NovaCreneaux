<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Une ressource reservable (salle, poste...) : modele App\Models\Resource. @mixin \App\Models\Resource */
class ResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'open_time' => substr($this->open_time, 0, 5),
            'close_time' => substr($this->close_time, 0, 5),
            // Jours de fermeture hebdomadaire, norme ISO : 1 = lundi ... 7 = dimanche
            'closed_days' => $this->closed_days ?? [],
            'slots' => SlotResource::collection($this->whenLoaded('activeSlots')),
        ];
    }
}
