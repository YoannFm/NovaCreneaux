<?php

namespace App\Services;

use App\Models\MfaCode;
use App\Models\User;

class OtpService
{
    private const EXPIRY_MINUTES = 15;
    private const RESEND_SECONDS = 60;

    public function generate(User $user, string $type): string
    {
        // Invalidate existing codes
        MfaCode::where('user_id', $user->id)->where('type', $type)->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        MfaCode::create([
            'user_id'    => $user->id,
            'type'       => $type,
            'code'       => hash('sha256', $code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        return $code;
    }

    public function verify(User $user, string $type, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        $record = MfaCode::where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('code', hash('sha256', $code))
            ->first();

        if (!$record) return false;

        $record->update(['used_at' => now()]);
        return true;
    }

    public function canResend(User $user, string $type): bool
    {
        return !MfaCode::where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('used_at')
            ->where('created_at', '>', now()->subSeconds(self::RESEND_SECONDS))
            ->exists();
    }
}
