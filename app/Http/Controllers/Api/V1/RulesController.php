<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RulesController extends Controller
{
    /** Limites de reservation du compte du jeton. null = illimite. Sans role, les valeurs par defaut du service s'appliquent. */
    public function __invoke(Request $request): JsonResponse
    {
        $role = $request->user()->role;
        $limit = fn (?int $value) => $value === null || $value === -1 ? null : $value;

        return response()->json(['data' => [
            'role' => $role ? ['name' => $role->name, 'label' => $role->label] : null,
            'max_reservations_per_day' => $limit($role?->max_reservations_day),
            'max_reservations_per_week' => $limit($role?->max_reservations_week),
            'cancel_deadline_minutes' => $limit($role?->cancel_deadline_minutes),
            'advance_days' => $role ? $role->advance_days : 30,
        ]]);
    }
}
