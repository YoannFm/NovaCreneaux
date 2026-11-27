<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Limite les adresses e-mail aux domaines definis dans Parametres > Authentification (vide = tous). */
class AllowedEmailDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Un tableau a la place du texte : refuse ici (les autres regles du champ le signalent deja)
        if (is_string($value) && self::allows($value)) {
            return;
        }

        $fail('Seules les adresses @' . implode(', @', self::domains()) . ' sont autorisées.');
    }

    /** L'adresse est-elle acceptee (aussi pour un compte cree a la connexion par l'etablissement) ? */
    public static function allows(string $email): bool
    {
        $domains = self::domains();
        $email = strtolower($email);

        return $domains === [] || array_filter($domains, fn (string $domain) => str_ends_with($email, '@' . $domain)) !== [];
    }

    /** @return list<string> */
    private static function domains(): array
    {
        $raw = (string) setting('allowed_email_domain', '');

        return array_values(array_filter(array_map(
            fn ($domain) => strtolower(trim($domain)),
            preg_split('/[\n,]+/', $raw)
        )));
    }
}
