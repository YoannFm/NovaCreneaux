<?php
namespace App\Services;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
class SettingService {
    public function get(string $key, mixed $default = null): mixed {
        return Cache::rememberForever("setting_{$key}", function() use ($key,$default) {
            $s = Setting::find($key);
            return $s ? $s->value : $default;
        });
    }
    public function set(string $key, mixed $value): void {
        Setting::updateOrCreate(['key'=>$key],['value'=>$value]);
        Cache::forget("setting_{$key}");
    }
    public function all(): array {
        return Setting::all()->pluck('value','key')->toArray();
    }
}
