<?php
namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/** Valeur secrete chiffree avec APP_KEY. Le prefixe distingue une valeur chiffree d'une valeur en clair d'avant le chiffrement. */
final class SecretValue {
    private const PREFIX = 'enc:v1:';

    public static function encrypt(string $plain): string {
        return $plain === '' ? '' : self::PREFIX . Crypt::encryptString($plain);
    }

    public static function isEncrypted(?string $stored): bool {
        return $stored !== null && str_starts_with($stored, self::PREFIX);
    }

    /** Valeur en clair, ou null si le chiffre est illisible (APP_KEY changee) : l'administrateur doit alors ressaisir le secret. */
    public static function decrypt(?string $stored): ?string {
        if (!self::isEncrypted($stored)) {
            return $stored;
        }

        try {
            return Crypt::decryptString(substr($stored, strlen(self::PREFIX)));
        } catch (DecryptException) {
            Log::warning('Un secret enregistre est illisible avec la cle APP_KEY actuelle : il doit etre ressaisi.');

            return null;
        }
    }
}
