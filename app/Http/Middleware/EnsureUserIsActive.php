<?php

namespace App\Http\Middleware;

use App\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Deconnecte immediatement un compte desactive, y compris s'il a une session ou un cookie "se souvenir de moi". */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            if ($request->is('api/*')) {
                return ApiError::response('account_disabled', 'Ce compte est désactivé.', 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Votre compte est désactivé.']);
        }

        return $next($request);
    }
}
