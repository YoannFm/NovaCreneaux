<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

/** Notifications affichees sur le site (cloche de l'en-tete). Les e-mails sont envoyes a part, par les appelants. */
class SiteNotifier
{
    public const TYPES = ['info', 'success', 'warning', 'danger'];

    public function send(User $user, string $title, ?string $message = null, string $type = 'info'): UserNotification
    {
        return UserNotification::create(['user_id' => $user->id, 'title' => $title, 'message' => $message, 'type' => $type]);
    }
}
