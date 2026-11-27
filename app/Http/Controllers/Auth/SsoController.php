<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\StartsMfaChallenge;
use App\Http\Controllers\Controller;
use App\Services\Sso\SsoException;
use App\Services\Sso\SsoLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Support\RequestText;

/** Connexion avec le compte de l'etablissement (OpenID Connect ou CAS) ; le mot de passe reste toujours possible. */
class SsoController extends Controller
{
    use StartsMfaChallenge;

    private const SESSION_KEY = 'sso_attempt';

    public function __construct(private SsoLogin $sso) {}

    public function redirect(Request $request): RedirectResponse
    {
        try {
            $provider = $this->sso->provider();
            $state = ['state' => Str::random(40), 'nonce' => Str::random(40), 'verifier' => Str::random(64)];
            $url = $provider->redirectUrl(route('sso.callback'), $state);
        } catch (SsoException $e) {
            return $this->fail($e->getMessage());
        }
        $request->session()->put(self::SESSION_KEY, $state);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull(self::SESSION_KEY);
        if (!is_array($state) || !hash_equals($state['state'], RequestText::get($request, 'state'))) {
            return $this->fail("La connexion avec le compte de l'établissement a expiré. Recommencez.");
        }

        try {
            $identity = $this->sso->provider()->identity($request, route('sso.callback'), $state);
            $user = $this->sso->user($identity);
        } catch (SsoException $e) {
            return $this->fail($e->getMessage());
        }

        if ($user->hasAnyMfa()) {
            return $this->startMfaChallenge($request, $user, false);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('reservations.index'));
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
