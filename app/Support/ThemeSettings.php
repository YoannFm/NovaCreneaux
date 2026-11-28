<?php

namespace App\Support;

/**
 * Couleurs du site configurables dans Admin > Apparence : une seule description pour le formulaire,
 * la validation et les valeurs par defaut utilisees par les mises en page.
 */
final class ThemeSettings
{
    public const ACCENT = 'theme_accent';
    public const DARK = 'dark';
    public const LIGHT = 'light';

    /** Champs par mode : cle du reglage => [libelle, icone Bootstrap, valeur par defaut]. */
    private const MODES = [
        self::DARK => [
            'theme_body_bg' => ['Fond général', 'bi-app', '#111113'],
            'theme_card_bg' => ['Fond des cartes', 'bi-card-text', '#212227'],
            'theme_header_bg' => ['Fond topbar', 'bi-layout-sidebar-reverse', '#1a1b1f'],
            'theme_text_color' => ['Couleur du texte', 'bi-fonts', '#e2e8f0'],
        ],
        self::LIGHT => [
            'theme_light_body_bg' => ['Fond général', 'bi-app', '#f4f5f7'],
            'theme_light_card_bg' => ['Fond des cartes', 'bi-card-text', '#ffffff'],
            'theme_light_header_bg' => ['Fond topbar', 'bi-layout-sidebar-reverse', '#ffffff'],
            'theme_light_text_color' => ['Couleur du texte', 'bi-fonts', '#1a1a2e'],
        ],
    ];

    private const ACCENT_DEFAULT = '#efb703';
    private const COLOR_RULE = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

    /** @return array<string, array{label: string, icon: string, default: string}> */
    public static function fields(string $mode): array
    {
        $fields = [];
        foreach (self::MODES[$mode] as $key => [$label, $icon, $default]) {
            $fields[$key] = compact('label', 'icon', 'default');
        }

        return $fields;
    }

    /** Valeur enregistree, ou celle par defaut tant que l'administrateur n'a rien choisi. */
    public static function value(string $key): string
    {
        return setting($key) ?: self::defaults()[$key];
    }

    /** @return array<string, string> */
    public static function defaults(): array
    {
        $defaults = [self::ACCENT => self::ACCENT_DEFAULT];
        foreach (self::MODES as $fields) {
            foreach ($fields as $key => [, , $default]) {
                $defaults[$key] = $default;
            }
        }

        return $defaults;
    }

    /** Regles de validation de toutes les couleurs. */
    public static function rules(): array
    {
        return array_map(fn () => self::COLOR_RULE, self::defaults());
    }
}
