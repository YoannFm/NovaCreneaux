<?php

use App\Support\SecretValue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Chiffre les secrets deja enregistres en clair. Sans effet sur ceux qui le sont deja. */
return new class extends Migration {
    // Liste figee : une migration ne doit pas dependre d'une constante qui peut evoluer
    private const KEYS = ['mail_smtp_password', 'sms_ntfy_token', 'sms_android_pass', 'sms_free_mobile_key'];

    public function up(): void {
        $this->rewrite(fn (string $stored) => SecretValue::isEncrypted($stored) ? $stored : SecretValue::encrypt($stored));
    }

    public function down(): void {
        $this->rewrite(fn (string $stored) => SecretValue::decrypt($stored) ?? '');
    }

    private function rewrite(callable $transform): void {
        foreach (DB::table('settings')->whereIn('key', self::KEYS)->get() as $row) {
            if (filled($row->value)) {
                DB::table('settings')->where('key', $row->key)->update(['value' => $transform($row->value)]);
            }
            Cache::forget("setting_{$row->key}");
        }
    }
};
