<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Cle de l'application d'authentification (users.two_factor_secret) chiffree avec APP_KEY, comme les autres secrets
 * (cast "encrypted" de User). Une cle deja chiffree n'est pas chiffree une seconde fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->convert(fn (string $value) => $this->isEncrypted($value) ? null : Crypt::encryptString($value));
    }

    public function down(): void
    {
        $this->convert(fn (string $value) => $this->isEncrypted($value) ? Crypt::decryptString($value) : null);
    }

    /** @param callable(string): ?string $transform null : ligne laissee telle quelle */
    private function convert(callable $transform): void
    {
        foreach (DB::table('users')->whereNotNull('two_factor_secret')->select('id', 'two_factor_secret')->get() as $row) {
            $value = $transform($row->two_factor_secret);
            if ($value !== null) {
                DB::table('users')->where('id', $row->id)->update(['two_factor_secret' => $value]);
            }
        }
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
