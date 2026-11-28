<?php

namespace App\Support;

/** Ecriture de valeurs dans le fichier .env (assistant d'installation), avec echappement des guillemets, antislash et dollar. */
class EnvFile
{
    public function __construct(private ?string $path = null)
    {
        $this->path ??= base_path('.env');
    }

    public function set(array $values): void
    {
        $env = file_get_contents($this->path);
        foreach ($values as $key => $value) {
            $line = $key . '="' . addcslashes((string) $value, "\"\\$") . '"';
            if (preg_match("/^{$key}=/m", $env)) {
                $env = preg_replace_callback("/^{$key}=.*/m", fn () => $line, $env);
            } else {
                $env .= "\n{$line}";
            }
        }
        file_put_contents($this->path, $env);
    }
}
