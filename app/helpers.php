<?php
if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed {
        return app(\App\Services\SettingService::class)->get($key, $default);
    }
}
