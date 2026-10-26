<?php

namespace App\Plugins;

/** Une extension trouvee dans le dossier des extensions, avec son etat. `manifest` est null si plugin.json est invalide. */
final class Plugin
{
    /**
     * @param  ?array  $manifest  voir Manifest::parse
     * @param  list<string>  $problems  ce qui empeche de l'utiliser
     * @param  ?array  $signature  contenu de .nova-signed : trace de l'installation d'une archive verifiee
     */
    public function __construct(
        public readonly string $id,
        public readonly string $path,
        public readonly ?array $manifest,
        public readonly array $problems,
        public readonly ?array $signature,
        public readonly bool $enabled = false,
        public readonly ?string $failure = null,
    ) {}

    public function name(): string
    {
        return $this->manifest['name'] ?? $this->id;
    }

    public function version(): ?string
    {
        return $this->manifest['version'] ?? null;
    }

    public function permissions(): array
    {
        return $this->manifest['permissions'] ?? [];
    }

    public function hasMigrations(): bool
    {
        return (glob($this->path . '/database/migrations/*.php') ?: []) !== [];
    }
}
