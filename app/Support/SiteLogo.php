<?php

namespace App\Support;

use App\Services\SettingService;
use Illuminate\Http\UploadedFile;

/**
 * Logo du site. Le fichier est garde dans storage (config nova.logo_directory) et servi par le site lui-meme
 * (route site.logo) : il s'affiche quelle que soit la configuration du serveur web (racine du site, regles pour les
 * images). Le reglage site_logo ne contient que le nom du fichier, de la forme logo_<16 hex>.<extension>.
 */
final class SiteLogo
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private const NAME = '/^logo_([a-f0-9]{16})\.(jpg|jpeg|png|gif|webp)$/';

    public static function directory(): string
    {
        return config('nova.logo_directory');
    }

    /** Fichier du logo, ou null. Seul un nom au format attendu est lu : aucun chemin venu du reglage n'est suivi. */
    public static function path(): ?string
    {
        $name = (string) setting('site_logo', '');
        if (!preg_match(self::NAME, $name)) {
            return null;
        }
        $path = self::directory() . '/' . $name;

        return is_file($path) ? $path : null;
    }

    /** Adresse du logo, avec une version qui change a chaque nouveau logo (le navigateur peut le garder en cache). */
    public static function url(): ?string
    {
        $path = self::path();

        return $path === null ? null : route('site.logo', ['v' => substr(basename($path), 5, 16)]);
    }

    /** Remplace le logo ; $extension est deduite du contenu du fichier par l'appelant, jamais du nom envoye. */
    public static function store(UploadedFile $file, string $extension): void
    {
        if (!is_dir(self::directory())) {
            mkdir(self::directory(), 0755, true);
        }
        self::delete();
        $name = 'logo_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $file->move(self::directory(), $name);
        app(SettingService::class)->set('site_logo', $name);
    }

    public static function delete(): void
    {
        if ($path = self::path()) {
            @unlink($path);
        }
        app(SettingService::class)->set('site_logo', '');
    }
}
