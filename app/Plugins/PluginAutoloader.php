<?php

namespace App\Plugins;

/** Charge les classes d'une extension depuis son dossier, et seulement depuis son dossier. */
final class PluginAutoloader
{
    /** @var array<string, string> prefixe => dossier reel */
    private static array $roots = [];
    private static bool $registered = false;

    public static function add(string $namespace, string $directory): void
    {
        $real = realpath($directory);
        if ($real === false) {
            return;
        }
        self::$roots[$namespace] = $real;
        if (! self::$registered) {
            spl_autoload_register(self::load(...));
            self::$registered = true;
        }
    }

    private static function load(string $class): void
    {
        foreach (self::$roots as $prefix => $root) {
            if (! str_starts_with($class, $prefix)) {
                continue;
            }
            $file = realpath($root . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php');
            // Un lien symbolique ou un nom detourne ne doit pas sortir du dossier de l'extension
            if ($file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
                require_once $file;
            }

            return;
        }
    }
}
