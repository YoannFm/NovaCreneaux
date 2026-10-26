<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Liste d'adresses IP ou de reseaux (CIDR) separes par des virgules, ou "*" : proxys dont on croit X-Forwarded-For. */
class TrustedProxyList implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (self::parse((string) $value) as $entry) {
            if ($entry !== '*' && !self::isIpOrCidr($entry)) {
                $fail("« {$entry} » n'est pas une adresse IP ni un réseau valide (exemple : 127.0.0.1 ou 10.0.0.0/8).");
                return;
            }
        }
    }

    /** @return list<string> entrees nettoyees, sans doublon */
    public static function parse(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }

    private static function isIpOrCidr(string $entry): bool
    {
        [$ip, $mask] = array_pad(explode('/', $entry, 2), 2, null);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($mask === null) {
            return true;
        }
        $max = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($mask) && (int) $mask <= $max;
    }
}
