<?php

namespace App\Support;

/**
 * Textes et choix propres a l'etablissement (Admin > Parametres) : une seule description pour les
 * formulaires, la validation et l'affichage. Une valeur vide en base reprend la valeur par defaut,
 * ce qui garde le site neutre tant que l'administrateur n'a rien saisi.
 */
final class Customization
{
    /** Reglages de la page Parametres > General : cle => [valeur par defaut, regle de validation]. */
    public const GENERAL = [
        'app_tagline' => ['Réservation de créneaux', 'nullable|string|max:120'],
        'email_placeholder' => ['prenom.nom@exemple.fr', 'nullable|string|max:120'],
        'contact_label' => ["l'administrateur du site", 'nullable|string|max:120'],
        'user_term' => ['élève', 'nullable|string|max:40'],
        'user_term_plural' => ['élèves', 'nullable|string|max:40'],
        'user_term_feminine' => ['0', 'nullable|in:0,1'],
        'group_label' => ['Classe', 'nullable|string|max:40'],
        'group_placeholder' => ['ex. Groupe A', 'nullable|string|max:60'],
        'default_theme' => ['system', 'nullable|in:system,light,dark'],
    ];

    public const PASSWORD_MIN_LENGTH = ['default' => 8, 'min' => 8, 'max' => 64];

    public const THEMES = ['system' => 'Celui du système', 'light' => 'Clair', 'dark' => 'Sombre'];

    public static function get(string $key): string
    {
        $value = (string) setting($key, '');

        return $value !== '' ? $value : self::GENERAL[$key][0];
    }

    /** @return array<string, string> regles de validation de la page Parametres > General */
    public static function rules(): array
    {
        return array_map(fn (array $field) => $field[1], self::GENERAL);
    }

    // Vocabulaire : comment le site appelle une personne qui reserve

    public static function userTerm(): string
    {
        return self::get('user_term');
    }

    public static function userTermPlural(): string
    {
        return self::get('user_term_plural');
    }

    /** "un élève", "une adhérente" */
    public static function aUser(): string
    {
        return (self::get('user_term_feminine') === '1' ? 'une ' : 'un ') . self::userTerm();
    }

    /** "l'élève", "le membre", "la stagiaire" */
    public static function theUser(): string
    {
        $term = self::userTerm();
        if (preg_match('/^[aeiouàâéèêëîïôöûùü]/iu', $term)) {
            return "l'" . $term;
        }

        return (self::get('user_term_feminine') === '1' ? 'la ' : 'le ') . $term;
    }

    public static function passwordMinLength(): int
    {
        $length = (int) setting('password_min_length', self::PASSWORD_MIN_LENGTH['default']);

        return max(self::PASSWORD_MIN_LENGTH['min'], min(self::PASSWORD_MIN_LENGTH['max'], $length));
    }

    /** Couleur des e-mails : celle choisie dans Parametres > E-mail, sinon l'accent du site. */
    public static function mailColor(): string
    {
        $color = (string) setting('mail_color', '');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : ThemeSettings::value(ThemeSettings::ACCENT);
    }
}
