<?php

namespace App\Listeners;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;

class LogAuthListener
{
    public function handle(Login|Logout|Registered $event): void
    {
        $user = match (true) {
            $event instanceof Login      => $event->user,
            $event instanceof Logout     => $event->user,
            $event instanceof Registered => $event->user,
        };

        $action = match (true) {
            $event instanceof Login      => 'Connexion',
            $event instanceof Logout     => 'Déconnexion',
            $event instanceof Registered => 'Inscription',
        };

        if (($event instanceof Login || $event instanceof Registered) && $user) {
            try {
                $updates = ['last_ip_address' => request()->ip()];
                if ($event instanceof Login) {
                    $updates['last_login_at'] = now();
                }
                $user->update($updates);
            } catch (\Throwable) {}
        }

        try {
            \App\Models\ActivityLog::create([
                'user_id'    => $user?->id,
                'user_name'  => $user?->name ?? 'Inconnu',
                'action'     => $action,
                'context'    => $user?->email,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {}
    }
}
