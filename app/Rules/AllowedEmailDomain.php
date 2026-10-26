<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Limite les adresses e-mail aux domaines definis dans Parametres > Authentification (vide = tous). */
class AllowedEmailDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domains = $this->domains();
        if ($domains === []) {
            return;
        }

        $email = strtolower((string) $value);
        foreach ($domains as $domain) {
            if (str_ends_with($email, '@' . $domain)) {
                return;
            }
        }

        $fail('Seules les adresses @' . implode(', @', $domains) . ' sont autorisées.');
    }

    /** @return list<string> */
    private function domains(): array
    {
        $raw = (string) setting('allowed_email_domain', '');

        return array_values(array_filter(array_map(
            fn ($domain) => strtolower(trim($domain)),
            preg_split('/[\n,]+/', $raw)
        )));
    }
}
