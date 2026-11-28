<?php

namespace App\Http\Middleware;

use App\Http\ApiError;
use App\Services\ApiAccessLog;
use Closure;
use Illuminate\Http\Request;

/** Les jetons d'un compte dont le role n'a pas acces a l'API cessent de fonctionner, sans etre supprimes. */
class EnsureApiAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->canUseApi()) {
            ApiAccessLog::denied('api_access_denied', $request);

            return ApiError::response('api_access_denied', "Le rôle de ce compte n'a pas accès à l'API.", 403);
        }

        return $next($request);
    }
}
