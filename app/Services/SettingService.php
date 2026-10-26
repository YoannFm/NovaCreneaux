<?php
namespace App\Services;
use App\Models\Setting;
use App\Support\SecretValue;
use Illuminate\Support\Facades\Cache;
class SettingService {
    /** Reglages chiffres en base, jamais reaffiches ni mis en cache en clair. */
    public const SECRET_KEYS = ['mail_smtp_password', 'sms_ntfy_token', 'sms_android_pass', 'sms_free_mobile_key'];

    public static function isSecret(string $key): bool {
        return in_array($key, self::SECRET_KEYS, true);
    }

    public function get(string $key, mixed $default = null): mixed {
        // Le cache garde la valeur telle que stockee (chiffree pour un secret)
        $stored = Cache::rememberForever("setting_{$key}", function() use ($key,$default) {
            $s = Setting::find($key);
            return $s ? $s->value : $default;
        });
        // Un secret illisible (APP_KEY changee) vaut sa valeur par defaut : il est considere comme non renseigne
        return self::isSecret($key) ? (SecretValue::decrypt($stored) ?? $default) : $stored;
    }
    public function set(string $key, mixed $value): void {
        $stored = self::isSecret($key) ? SecretValue::encrypt((string) $value) : $value;
        Setting::updateOrCreate(['key'=>$key],['value'=>$stored]);
        Cache::forget("setting_{$key}");
    }
    /** Reglages pour les formulaires : sans les secrets (voir secretsStatus). */
    public function all(): array {
        return Setting::all()->reject(fn ($s) => self::isSecret($s->key))->pluck('value','key')->toArray();
    }
    /** Pour chaque secret, indique s'il est renseigne, sans jamais renvoyer sa valeur. */
    public function secretsStatus(): array {
        $stored = Setting::whereIn('key', self::SECRET_KEYS)->pluck('value', 'key');
        return collect(self::SECRET_KEYS)->mapWithKeys(fn ($key) => [$key => filled($stored[$key] ?? null)])->all();
    }
}
