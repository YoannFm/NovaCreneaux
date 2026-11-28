<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

/** Reserve une section aux administrateurs (le personnel delegue est renvoye au tableau de bord). */
class AdminMiddleware {
    public function handle(Request $request, Closure $next) {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard')->with('error', "Cette section est réservée aux administrateurs.");
        }
        return $next($request);
    }
}
