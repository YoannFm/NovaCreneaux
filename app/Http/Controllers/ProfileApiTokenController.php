<?php

namespace App\Http\Controllers;

use App\Services\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Jetons d'API personnels : l'utilisateur gere les siens depuis son profil, si le role de son compte l'autorise. */
class ProfileApiTokenController extends Controller
{
    private const BAG = 'apiToken';

    public function __construct(private ApiTokenService $tokens) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canUseApi(), 403);

        $data = $request->validateWithBag(self::BAG, [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:' . implode(',', array_keys(ApiTokenService::ABILITIES))],
            'days' => ['required', 'integer', 'in:' . implode(',', ApiTokenService::PERSONAL_DURATIONS)],
            'password' => ['required', 'current_password'],
        ]);

        try {
            $token = $this->tokens->createPersonal($user, $data['name'], $data['abilities'], (int) $data['days']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(route('profile.edit') . '#api')->withInput($request->except('password'))->with('api_error', $e->getMessage());
        }

        // Le secret n'est conserve que le temps d'une page : il ne sera plus jamais affichable
        return redirect()->to(route('profile.edit') . '#api')->with('new_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $own = $request->user()->tokens()->findOrFail($token);
        $this->tokens->revoke($own);

        return redirect()->to(route('profile.edit') . '#api')->with('api_success', 'Jeton révoqué : il ne fonctionne plus.');
    }
}
