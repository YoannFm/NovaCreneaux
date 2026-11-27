<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogger
{
    /** @param array<string, array{old?: mixed, new?: mixed}> $changes detail affiche par l'oeil du journal (App\Support\ActivityChanges) */
    public static function log(string $action, ?string $context = null, array $changes = []): void
    {
        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'user_name'  => auth()->user()?->name ?? 'Système',
                // Longueurs des colonnes : sur MySQL strict, un texte trop long ferait perdre l'entree sans le dire
                'action'     => mb_substr($action, 0, 150),
                'context'    => $context === null ? null : mb_substr($context, 0, 500),
                'changes'    => $changes ?: null,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Ne jamais faire crasher l'app à cause des logs
        }
    }
}
