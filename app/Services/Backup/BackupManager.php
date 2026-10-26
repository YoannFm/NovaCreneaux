<?php
namespace App\Services\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/** Fichiers de sauvegarde de la base : creation, liste, telechargement, suppression, purge. Rien n'est servi publiquement. */
class BackupManager {
    public const MANUAL = 'manual';
    public const AUTOMATIC = 'auto';
    public const BEFORE_UPDATE = 'update';

    /** Sauvegardes faites juste avant l'installation d'une mise a jour : on garde les dernieres seulement. */
    private const KEPT_BEFORE_UPDATE = 3;

    // Le nom porte toutes les metadonnees : pas de table, la base ne se sauvegarde pas elle-meme.
    // Les 16 caracteres aleatoires rendent le nom impossible a deviner, meme si le dossier etait expose par erreur.
    private const NAME_PATTERN = '/^nova-(\d{8}-\d{6})-(auto|manual|update)-[a-f0-9]{16}\.sql\.gz$/';

    public function __construct(
        private DatabaseDumper $dumper,
        private ?string $directory = null,
    ) {
        $this->directory ??= config('nova.backups.directory');
    }

    /** @return array{name: string, size: int, created_at: CarbonImmutable, trigger: string} */
    public function create(string $trigger): array {
        if (!in_array($trigger, [self::MANUAL, self::AUTOMATIC, self::BEFORE_UPDATE], true)) {
            throw new \InvalidArgumentException("Type de sauvegarde inconnu : {$trigger}");
        }

        $lock = Cache::lock('database-backup', 900);
        if (!$lock->get()) {
            throw new BackupException('Une sauvegarde est déjà en cours.');
        }

        try {
            @mkdir($this->directory, 0700, true);
            $name = sprintf('nova-%s-%s-%s.sql.gz', now()->format('Ymd-His'), $trigger, bin2hex(random_bytes(8)));
            $partial = "{$this->directory}/{$name}.partial";

            try {
                $this->dumper->dump($partial);
                if (!DatabaseDumper::isComplete($partial)) {
                    throw new BackupException('Le fichier de sauvegarde est incomplet (disque plein ?).');
                }
                chmod($partial, 0600);
                rename($partial, "{$this->directory}/{$name}");
            } catch (\Throwable $e) {
                @unlink($partial);
                if ($e instanceof BackupException) {
                    throw $e;
                }
                report($e);
                throw new BackupException('La sauvegarde a échoué, voir le journal du serveur.', 0, $e);
            }

            return $this->describe($name);
        } finally {
            $lock->release();
        }
    }

    /** Plus recentes d'abord. */
    public function list(): array {
        $backups = [];
        foreach (glob($this->directory . '/nova-*.sql.gz') ?: [] as $file) {
            if (preg_match(self::NAME_PATTERN, basename($file))) {
                $backups[] = $this->describe(basename($file));
            }
        }
        usort($backups, fn ($a, $b) => [$b['created_at'], $b['name']] <=> [$a['created_at'], $a['name']]);

        return $backups;
    }

    /** Chemin d'une sauvegarde existante ; le nom est valide strictement (aucun chemin n'est accepte). */
    public function path(string $name): string {
        $path = "{$this->directory}/{$name}";
        if (!preg_match(self::NAME_PATTERN, $name) || !is_file($path)) {
            throw new BackupException('Sauvegarde introuvable.');
        }

        return $path;
    }

    public function delete(string $name): void {
        unlink($this->path($name));
    }

    /** Ne garde que les $keep sauvegardes automatiques les plus recentes ; les manuelles ne sont jamais supprimees seules. */
    public function pruneAutomatic(int $keep): int {
        return $this->prune(self::AUTOMATIC, $keep);
    }

    public function pruneBeforeUpdate(): int {
        return $this->prune(self::BEFORE_UPDATE, self::KEPT_BEFORE_UPDATE);
    }

    private function prune(string $trigger, int $keep): int {
        $matching = array_values(array_filter($this->list(), fn ($b) => $b['trigger'] === $trigger));
        $excess = array_slice($matching, max($keep, 0));
        foreach ($excess as $backup) {
            $this->delete($backup['name']);
        }

        return count($excess);
    }

    private function describe(string $name): array {
        preg_match(self::NAME_PATTERN, $name, $parts);

        return [
            'name' => $name,
            'size' => filesize("{$this->directory}/{$name}"),
            'created_at' => CarbonImmutable::createFromFormat('Ymd-His', $parts[1]),
            'trigger' => $parts[2],
        ];
    }
}
