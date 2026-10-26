<?php

namespace App\Services;

use App\Mail\AccountDeleted;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail envoye a l'ancien titulaire d'un compte, a appeler avant la suppression.
 * Une suppression par un administrateur peut etre desactivee (reglage notify_account_deletion), pas celle faite par l'utilisateur.
 * Un e-mail qui echoue n'empeche jamais la suppression.
 */
class AccountDeletionNotice
{
    public function send(User $user, bool $byAdmin): void
    {
        if ($byAdmin && setting('notify_account_deletion', '1') !== '1') {
            return;
        }

        try {
            Mail::to($user->email)->send(new AccountDeleted($user->name, $byAdmin));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
