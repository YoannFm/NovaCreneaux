<?php

namespace App\Services\Sso;

use App\Models\Role;
use App\Models\User;
use App\Rules\AllowedEmailDomain;
use App\Services\ActivityLogger;
use App\Support\Customization;
use App\Support\SsoSettings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Connexion avec le compte de l'etablissement : choisit le fournisseur configure et retrouve le compte du site
 * par son adresse e-mail. Une adresse inconnue n'entre que si la creation automatique est activee.
 */
class SsoLogin
{
    public function __construct(private OidcProvider $oidc, private CasProvider $cas) {}

    /** @throws SsoException */
    public function provider(): SsoProvider
    {
        return match (SsoSettings::driver()) {
            SsoSettings::OIDC => $this->oidc,
            SsoSettings::CAS => $this->cas,
            default => throw new SsoException("La connexion avec le compte de l'établissement n'est pas activée."),
        };
    }

    /** @throws SsoException */
    public function user(SsoIdentity $identity): User
    {
        $email = User::normalizeEmail($identity->email);
        $user = User::where('email', $email)->first() ?? $this->create($email, $identity->name);

        if (!$user->is_active) {
            throw new SsoException('Votre compte est désactivé.');
        }

        return $user;
    }

    private function create(string $email, ?string $name): User
    {
        if (!SsoSettings::autoCreate()) {
            throw new SsoException("Aucun compte ne correspond à l'adresse {$email}. Contactez " . Customization::get('contact_label') . '.');
        }
        if (!AllowedEmailDomain::allows($email)) {
            throw new SsoException("L'adresse {$email} n'est pas autorisée sur ce site.");
        }

        $user = User::create([
            'name' => mb_substr($name ?: Str::before($email, '@'), 0, 255),
            'email' => $email,
            // Mot de passe aleatoire jamais communique : "Mot de passe oublie" permet d'en choisir un
            'password' => Hash::make(Str::random(64)),
            'role_id' => Role::defaultUser()?->id,
            'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        ActivityLogger::log("Compte créé à la connexion par l'établissement", $email);

        return $user;
    }
}
