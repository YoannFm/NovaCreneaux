<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function __construct(private ApiTokenService $tokens) {}

    public function index()
    {
        return view('admin.api-tokens.index', [
            'tokens' => PersonalAccessToken::with('tokenable')->latest('id')->get(),
            'users' => User::where('is_active', true)->with('role')->orderBy('name')->get(),
            'abilities' => ApiTokenService::ABILITIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:' . implode(',', array_keys(ApiTokenService::ABILITIES))],
            'days' => ['nullable', 'integer', 'between:1,730'],
        ]);

        try {
            $token = $this->tokens->create(User::findOrFail($data['user_id']), $data['name'], $data['abilities'], $data['days'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Le secret n'est conserve que le temps d'une page : il ne sera plus jamais affichable
        return redirect()->route('admin.api-tokens.index')->with('new_token', $token->plainTextToken);
    }

    public function destroy(PersonalAccessToken $token)
    {
        $this->tokens->revoke($token);

        return redirect()->route('admin.api-tokens.index')->with('success', 'Jeton révoqué : il ne fonctionne plus.');
    }
}
