<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogger
{
    public static function log(string $action, ?string $context = null): void
    {
        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'user_name'  => auth()->user()?->name ?? 'Système',
                'action'     => $action,
                'context'    => $context,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Ne jamais faire crasher l'app à cause des logs
        }
    }
}
