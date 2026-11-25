<?php

namespace App\Support;

/**
 * Connexion avec le compte de l'etablissement (Parametres > Compte de l'établissement) : OpenID Connect ou CAS.
 * Desactivee par defaut ; la connexion par mot de passe reste toujours disponible.
 */
final class SsoSettings
{
    public const NONE = 'none';
    public const OIDC = 'oidc';
    public const CAS = 'cas';

    public const DRIVERS = [self::NONE => 'Désactivée', self::OIDC => 'OpenID Connect (OIDC)', self::CAS => 'CAS'];

    public const DEFAULT_LABEL = "Se connecter avec le compte de l'établissement";
    public const DEFAULT_SCOPES = 'openid email profile';
    public const DEFAULT_CAS_ATTRIBUTE = 'mail';

    /** Methode active, ou NONE si elle n'est pas choisie ou pas entierement configuree. */
    public static function driver(): string
    {
        return match ((string) setting('sso_driver', self::NONE)) {
            self::OIDC => filled(setting('oidc_issuer')) && filled(setting('oidc_client_id')) ? self::OIDC : self::NONE,
            self::CAS => filled(setting('cas_url')) ? self::CAS : self::NONE,
            default => self::NONE,
        };
    }

    public static function enabled(): bool
    {
        return self::driver() !== self::NONE;
    }

    public static function label(): string
    {
        return (string) setting('sso_label', '') ?: self::DEFAULT_LABEL;
    }

    /** Creer le compte d'une adresse inconnue (desactive par defaut : seuls les comptes existants se connectent). */
    public static function autoCreate(): bool
    {
        return setting('sso_auto_create', '0') === '1';
    }

    public static function oidcScopes(): string
    {
        return (string) setting('oidc_scopes', '') ?: self::DEFAULT_SCOPES;
    }

    public static function casEmailAttribute(): string
    {
        return (string) setting('cas_email_attribute', '') ?: self::DEFAULT_CAS_ATTRIBUTE;
    }

    /** Regles du formulaire d'administration. */
    public static function rules(): array
    {
        $url = ['nullable', 'url:https', 'max:255'];

        return [
            'sso_driver' => 'required|in:' . implode(',', array_keys(self::DRIVERS)),
            'sso_label' => 'nullable|string|max:80',
            'sso_auto_create' => 'nullable|in:0,1',
            'oidc_issuer' => [...$url, 'required_if:sso_driver,' . self::OIDC],
            'oidc_client_id' => ['nullable', 'string', 'max:255', 'required_if:sso_driver,' . self::OIDC],
            'oidc_client_secret' => 'nullable|string|max:500',
            'oidc_scopes' => ['nullable', 'string', 'max:255', 'regex:/^[\w:.\- ]*$/'],
            'cas_url' => [...$url, 'required_if:sso_driver,' . self::CAS],
            'cas_email_attribute' => ['nullable', 'string', 'max:60', 'regex:/^[\w.\-]*$/'],
        ];
    }
}
