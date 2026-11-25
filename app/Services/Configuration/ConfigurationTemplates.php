<?php

namespace App\Services\Configuration;

/**
 * Modeles prets a importer : un fichier de configuration par usage dans resources/configuration-templates
 * (id = nom du fichier). Ajouter un modele = ajouter un fichier ; la page le propose tout de suite.
 */
class ConfigurationTemplates
{
    private const ID = '/^[a-z0-9-]+$/';

    public function __construct(private ?string $directory = null)
    {
        $this->directory ??= resource_path('configuration-templates');
    }

    /** @return array<string, array> id => contenu du fichier, dans l'ordre alphabetique des ids */
    public function all(): array
    {
        $templates = [];
        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            $id = basename($file, '.json');
            if (preg_match(self::ID, $id) && is_array($data = json_decode((string) file_get_contents($file), true))) {
                $templates[$id] = $data;
            }
        }
        ksort($templates);

        return $templates;
    }

    public function find(string $id): ?array
    {
        return preg_match(self::ID, $id) ? ($this->all()[$id] ?? null) : null;
    }
}
