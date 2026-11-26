<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Mail\EmailChanged;
use App\Support\ActivityChanges;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\AccountDeletionNotice;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /** Champs du profil dont le journal d'activite garde l'avant / apres. */
    private const LOGGED_FIELDS = ['name', 'email', 'classe'];

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
        $user = $request->user();
        $before = $user->only(self::LOGGED_FIELDS);
        $user->fill(collect($request->validated())->except('current_password')->all());
        $oldEmail = $user->isDirty('email') ? $user->getOriginal('email') : null;
        if ($oldEmail !== null) {
            $user->email_verified_at = null;
        }
        $user->save();

        ActivityLogger::log('Profil mis à jour', null, ActivityChanges::diff($before, $user->only(self::LOGGED_FIELDS)));
        if ($oldEmail !== null) {
            $this->warnPreviousAddress($oldEmail, $user->name, $user->email);
        }
        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /** L'ancienne adresse est prevenue ; un e-mail qui echoue n'annule pas le changement (erreur journalisee). */
    private function warnPreviousAddress(string $oldEmail, string $name, string $newEmail): void
    {
        try {
            Mail::to($oldEmail)->send(new EmailChanged($name, $newEmail));
        } catch (\Throwable $e) {
            report($e);
        }
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

        if ($user->isAdmin()) {
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
