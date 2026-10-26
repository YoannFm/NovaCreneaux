<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\AccountDeletionNotice;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        if (setting('allow_name_change', '1') !== '1') {
            $request->merge(['name' => $request->user()->name]);
        }

        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        ActivityLogger::log('Profil mis à jour');
        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $request->validate([
            'theme_mode'   => ['nullable', 'in:system,dark,light'],
            'accent_color' => ['nullable', 'regex:/^(#[0-9a-fA-F]{6})?$/'],
        ]);

        $request->user()->update([
            'theme_mode'   => $request->input('theme_mode', 'system'),
            'accent_color' => $request->input('accent_color') ?: null,
        ]);

        return Redirect::route('profile.edit')->with('status', 'theme-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (setting('allow_account_deletion', '1') !== '1') {
            return back()->with('error', 'La suppression de compte est désactivée.');
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->is_admin) {
            return back()->with('error', 'Un administrateur ne peut pas supprimer son propre compte.');
        }

        ActivityLogger::log('Compte supprimé', $user->name);
        app(AccountDeletionNotice::class)->send($user, false);
        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
