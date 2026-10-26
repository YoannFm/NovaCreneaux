<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Rules\AllowedEmailDomain;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'terms' => setting('registration_terms', ''),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if (setting('registration_open', '0') !== '1') {
            abort(403, 'Les inscriptions sont fermées.');
        }

        $rules = [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class, new AllowedEmailDomain],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'classe'   => ['nullable', 'string', 'max:20'],
        ];

        // Terms acceptance
        if (!empty(strip_tags(setting('registration_terms', '')))) {
            $rules['terms_accepted'] = ['accepted'];
        }

        $request->validate($rules);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'classe'   => $request->classe,
            'role_id'  => Role::defaultUser()?->id,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('home'));
    }
}
