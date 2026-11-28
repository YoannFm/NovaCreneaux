<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Usage : `permission:users.manage`. Un administrateur passe toujours. */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (! $request->user()?->hasPermission($permission)) {
            return redirect()->route('admin.dashboard')->with('error', "Vous n'avez pas accès à cette section.");
        }

        return $next($request);
    }
}
