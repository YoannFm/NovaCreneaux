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
                'action'     => $action,
                'context'    => $context,
                'changes'    => $changes ?: null,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Ne jamais faire crasher l'app à cause des logs
        }
    }
}
