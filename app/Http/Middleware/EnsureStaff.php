<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Entree de l'administration : administrateurs et roles disposant d'au moins une permission. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->hasAdminAccess()) {
            return redirect('/');
        }

        return $next($request);
    }
}
