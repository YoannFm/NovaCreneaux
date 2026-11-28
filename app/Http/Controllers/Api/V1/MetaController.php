<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    /** Informations publiques utiles a un client : version de l'API, fuseau horaire du site, heure du serveur. */
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'api_version' => 'v1',
            'app_name' => setting('app_name', config('app.name')),
            'timezone' => config('app.timezone'),
            'server_time' => now()->toIso8601String(),
        ]]);
    }

    /** Le compte de service (ou l'utilisateur) auquel appartient le jeton. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'classe' => $user->classe,
            'role' => $user->role ? ['name' => $user->role->name, 'label' => $user->role->label] : null,
            'abilities' => $user->currentAccessToken()->abilities,
        ]]);
    }
}
