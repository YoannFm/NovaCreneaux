<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Comme le cast "encrypted" de Laravel, mais une valeur illisible (APP_KEY changee) se lit null au lieu de lever une
 * exception : sinon chaque page qui touche la colonne repondait 500. La valeur stockee reste en base telle quelle.
 */
class EncryptedOrUnreadable implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            Log::warning("La colonne {$key} du compte #{$model->getKey()} est illisible avec la cle APP_KEY actuelle.");

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::encryptString((string) $value);
    }
}
