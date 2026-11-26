<?php
namespace App\Services;
use App\Models\Setting;
use App\Support\SecretValue;
use Illuminate\Support\Facades\Cache;
class SettingService {
    /** Reglages chiffres en base, jamais reaffiches ni mis en cache en clair. */
    public const SECRET_KEYS = ['mail_smtp_password', 'sms_ntfy_token', 'sms_android_pass', 'sms_free_mobile_key', 'oidc_client_secret'];

    public static function isSecret(string $key): bool {
        return in_array($key, self::SECRET_KEYS, true);
    }

    /**
     * Valeur d'un reglage, ou $default s'il n'existe pas. Le cache garde ce qui est en base (chiffre pour un secret),
     * ou l'absence de valeur : jamais le $default d'un appel, qui deviendrait celui de tous les appels suivants
     * (le nom par defaut lu pendant l'assistant restait affiche apres l'installation).
     */
    public function get(string $key, mixed $default = null): mixed {
        $entry = Cache::rememberForever(self::cacheKey($key), fn () => ['value' => Setting::find($key)?->value]);
        // Ancienne forme du cache (valeur seule), le temps qu'une mise a jour le vide
        $stored = is_array($entry) && array_key_exists('value', $entry) ? $entry['value'] : $entry;
        if ($stored === null) {
            return $default;
        }

        // Un secret illisible (APP_KEY changee) vaut sa valeur par defaut : il est considere comme non renseigne
        return self::isSecret($key) ? (SecretValue::decrypt($stored) ?? $default) : $stored;
    }
    public function set(string $key, mixed $value): void {
        $stored = self::isSecret($key) ? SecretValue::encrypt((string) $value) : $value;
        Setting::updateOrCreate(['key'=>$key],['value'=>$stored]);
        Cache::forget(self::cacheKey($key));
    }
    /** Ecrit les reglages encore absents (valeurs initiales de l'installation), sans toucher aux autres. */
    public function setMissing(array $values): void {
        foreach ($values as $key => $value) {
            if (!Setting::whereKey($key)->exists()) {
                $this->set($key, $value);
            }
        }
    }
    private static function cacheKey(string $key): string {
        return "setting_{$key}";
    }
    /** Reglages pour les formulaires : sans les secrets (voir secretsStatus). */
    public function all(): array {
        return Setting::all()->reject(fn ($s) => self::isSecret($s->key))->pluck('value','key')->toArray();
    }
    /**
     * Etat de tous les reglages pour le journal d'activite : valeurs en clair, sauf les secrets, remplaces par
     * une empreinte de leur valeur chiffree (dit qu'un secret a change sans jamais le reveler).
     */
    public function snapshot(): array {
        return Setting::all()->mapWithKeys(fn ($s) => [
            $s->key => self::isSecret($s->key) ? (filled($s->value) ? hash('sha256', (string) $s->value) : '') : $s->value,
        ])->all();
    }

    /** Pour chaque secret, indique s'il est renseigne, sans jamais renvoyer sa valeur. */
    public function secretsStatus(): array {
        $stored = Setting::whereIn('key', self::SECRET_KEYS)->pluck('value', 'key');
        return collect(self::SECRET_KEYS)->mapWithKeys(fn ($key) => [$key => filled($stored[$key] ?? null)])->all();
    }
}
