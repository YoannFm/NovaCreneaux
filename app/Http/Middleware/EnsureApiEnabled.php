<?php

namespace App\Http\Middleware;

use App\Http\ApiError;
use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\Request;

/** Interrupteur general de l'API (Admin > Integrations API) : tout /api/v1 repond 503 tant qu'elle est coupee. */
class EnsureApiEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (! ApiTokenService::enabled()) {
            return ApiError::response('api_disabled', "L'API est désactivée par un administrateur.", 503);
        }

        return $next($request);
    }
}
