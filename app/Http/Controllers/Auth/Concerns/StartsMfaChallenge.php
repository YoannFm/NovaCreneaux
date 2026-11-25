<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Compte reconnu mais avec double authentification : la connexion n'a lieu qu'apres le defi (MfaController). */
trait StartsMfaChallenge
{
    protected function startMfaChallenge(Request $request, User $user, bool $remember): RedirectResponse
    {
        $request->session()->put('mfa_user_id', $user->id);
        $request->session()->put('mfa_remember', $remember);

        return redirect()->route('mfa.challenge.form');
    }
}
