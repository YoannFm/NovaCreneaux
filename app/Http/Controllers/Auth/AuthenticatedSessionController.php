<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\StartsMfaChallenge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use StartsMfaChallenge;

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        if (! auth()->user()->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            // Redirection explicite : la session videe a perdu l'adresse precedente, un "retour" menait a l'accueil
            // puis a la connexion, et le message etait perdu en route
            return redirect()->route('login')->withErrors(['email' => 'Votre compte est désactivé.'])->onlyInput('email');
        }

        $user = auth()->user();
        if ($user->hasAnyMfa()) {
            auth()->logout();
            return $this->startMfaChallenge($request, $user, $request->boolean('remember'));
        }

        $request->session()->regenerate();

        return redirect()->intended(route('reservations.index'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
