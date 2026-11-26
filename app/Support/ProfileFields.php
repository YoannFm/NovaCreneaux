<?php

namespace App\Support;

/**
 * Champs du profil que chacun peut modifier lui-meme (Parametres > Authentification). Autorise par defaut.
 * Un champ verrouille s'affiche sans pouvoir etre change, et une valeur envoyee quand meme est ignoree.
 */
final class ProfileFields
{
    /** champ => reglage */
    public const SETTINGS = [
        'name'   => 'allow_name_change',
        'email'  => 'allow_email_change',
        'classe' => 'allow_classe_change',
    ];

    public static function editable(string $field): bool
    {
        return setting(self::SETTINGS[$field], '1') === '1';
    }

    /** Au moins un champ modifiable : sinon le formulaire du profil n'a rien a enregistrer. */
    public static function anyEditable(): bool
    {
        foreach (array_keys(self::SETTINGS) as $field) {
            if (self::editable($field)) {
                return true;
            }
        }

        return false;
    }

    /** Libelle de l'option dans l'administration ("Permettre aux utilisateurs de changer leur ..."). */
    public static function label(string $field): string
    {
        return match ($field) {
            'name' => 'leur nom',
            'email' => 'leur adresse e-mail',
            'classe' => 'leur ' . mb_strtolower(Customization::get('group_label')),
        };
    }
}
