<?php

namespace App\Services;

class SmsService
{
    public function send(string $phone, string $message): bool
    {
        $driver = setting('sms_driver', 'disabled');

        return match ($driver) {
            'free_mobile'    => $this->sendFreeMobile($phone, $message),
            'ntfy'           => $this->sendNtfy($phone, $message),
            'android_gw'     => $this->sendAndroidGateway($phone, $message),
            'webhook'        => $this->sendWebhook($phone, $message),
            default          => false,
        };
    }

    public function isConfigured(): bool
    {
        $driver = setting('sms_driver', 'disabled');
        return match ($driver) {
            'free_mobile' => setting('sms_free_mobile_user', '') !== '' && setting('sms_free_mobile_key', '') !== '',
            'ntfy'        => true, // ntfy.sh public is always available
            'android_gw'  => setting('sms_android_url', '') !== '',
            'webhook'     => setting('sms_webhook_url', '') !== '',
            default       => false,
        };
    }

    public function driver(): string
    {
        return setting('sms_driver', 'disabled');
    }

    // ──────────────────────────────────────────────────────────────────
    // ntfy (open-source, auto-hébergeable ou ntfy.sh gratuit)
    // Docs: https://docs.ntfy.sh/
    // L'utilisateur entre son "topic" ntfy (stocké dans mfa_phone)
    // ──────────────────────────────────────────────────────────────────
    private function sendNtfy(string $topic, string $message): bool
    {
        $server = rtrim(setting('sms_ntfy_url', 'https://ntfy.sh'), '/');
        $token  = setting('sms_ntfy_token', '');

        $headers = ['Content-Type: text/plain; charset=utf-8'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init("{$server}/{$topic}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $message,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code === 200;
    }

    // ──────────────────────────────────────────────────────────────────
    // Android SMS Gateway (open-source, auto-hébergé)
    // https://github.com/capcom6/android-sms-gateway
    // Installer l'app sur un Android → expose une API HTTP locale
    // ──────────────────────────────────────────────────────────────────
    private function sendAndroidGateway(string $phone, string $message): bool
    {
        $url   = rtrim(setting('sms_android_url', ''), '/');
        $login = setting('sms_android_login', 'user');
        $pass  = setting('sms_android_pass', '');

        if (!$url) return false;

        $ch = curl_init("{$url}/api/mobile/v1/message");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false, // réseau local souvent sans cert valide
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'message'      => $message,
                'phoneNumbers' => [$phone],
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode("{$login}:{$pass}"),
            ],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code === 200 || $code === 201;
    }

    // ──────────────────────────────────────────────────────────────────
    // Free Mobile API (gratuit pour les abonnés Free Mobile)
    // https://mobile.free.fr → Mon Compte → Mes Options → Notifications SMS
    // ──────────────────────────────────────────────────────────────────
    private function sendFreeMobile(string $phone, string $message): bool
    {
        $login = setting('sms_free_mobile_user', '');
        $key   = setting('sms_free_mobile_key', '');

        if (!$login || !$key) return false;

        $ch = curl_init('https://smsapi.free-mobile.fr/sendmsg?' . http_build_query([
            'user' => $login,
            'pass' => $key,
            'msg'  => $message,
        ]));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code === 200;
    }

    // ──────────────────────────────────────────────────────────────────
    // Webhook HTTP générique (compatible Twilio, OVH SMS, etc.)
    // ──────────────────────────────────────────────────────────────────
    private function sendWebhook(string $phone, string $message): bool
    {
        $urlTemplate = setting('sms_webhook_url', '');
        if (!$urlTemplate) return false;

        $method = strtoupper(setting('sms_webhook_method', 'GET'));
        $url    = str_replace(['{to}', '{message}'], [urlencode($phone), urlencode($message)], $urlTemplate);

        $ch   = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($method === 'POST') {
            $body = setting('sms_webhook_body', '');
            $body = $body
                ? str_replace(['{to}', '{message}'], [$phone, $message], $body)
                : http_build_query(['to' => $phone, 'message' => $message]);
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code >= 200 && $code < 300;
    }
}
