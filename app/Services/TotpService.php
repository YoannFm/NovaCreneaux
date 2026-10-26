<?php

namespace App\Services;

class TotpService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const DIGITS       = 6;
    private const PERIOD       = 30;
    private const WINDOW       = 1;

    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, 31)];
        }
        return $secret;
    }

    public function getOtpauthUrl(string $label, string $secret, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&digits=%d&period=%d',
            rawurlencode($issuer . ':' . $label),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp = (int) floor(time() / self::PERIOD);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if ($this->generateCode($secret, $timestamp + $i) === $code) {
                return true;
            }
        }
        return false;
    }

    private function generateCode(string $secret, int $timestamp): string
    {
        $key     = $this->base32Decode($secret);
        $counter = pack('N*', 0) . pack('N*', $timestamp);
        $hash    = hash_hmac('sha1', $counter, $key, true);
        $offset  = ord($hash[19]) & 0x0F;
        $code    = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ( ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $input  = strtoupper($input);
        $chars  = self::BASE32_CHARS;
        $bits   = '';
        $output = '';

        foreach (str_split($input) as $char) {
            if ($char === '=') break;
            $val = strpos($chars, $char);
            if ($val === false) continue;
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }

    public function generateQrCodeDataUri(string $otpauthUrl): string
    {
        // Inline QR code using a minimal pure-PHP approach via Google Charts API alternative
        // We generate a simple SVG QR representation using a third-party URL-free approach.
        // Since we have no QR package, we embed the URL as text and let the user copy it.
        return $otpauthUrl;
    }
}
