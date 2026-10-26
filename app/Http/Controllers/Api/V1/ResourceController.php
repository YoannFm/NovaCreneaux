<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ResourceResource;
use App\Http\Resources\V1\SlotResource;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResourceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $params = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $resources = Resource::where('is_active', true)
            ->when($params['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderBy('name')
            ->paginate($params['per_page'] ?? 25)
            ->withQueryString();

        return ResourceResource::collection($resources);
    }

    public function show(int $resource): ResourceResource
    {
        return new ResourceResource($this->activeResource($resource)->load('activeSlots'));
    }

    public function slots(int $resource): AnonymousResourceCollection
    {
        return SlotResource::collection($this->activeResource($resource)->activeSlots()->orderBy('start_time')->get());
    }

    /** Une ressource desactivee n'existe pas pour l'API. */
    private function activeResource(int $id): Resource
    {
        return Resource::where('is_active', true)->findOrFail($id);
    }
}
