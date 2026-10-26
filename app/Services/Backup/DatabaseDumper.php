<?php
namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use PDO;

/** Exporte la base en SQL compresse (gzip) avec PDO, sans dependre de mysqldump. Pilotes : MySQL, MariaDB, SQLite. */
class DatabaseDumper {
    /** Tables dont seule la structure est exportee : donnees temporaires ou regenerables. */
    private const STRUCTURE_ONLY = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
    private const ROWS_PER_INSERT = 100;
    private const END_MARKER = '-- Fin de la sauvegarde';

    public function dump(string $path, ?string $connection = null): void {
        $db = DB::connection($connection);
        $driver = $db->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new BackupException("La sauvegarde n'est pas prise en charge pour le pilote {$driver}.");
        }
        $mysql = $driver !== 'sqlite';
        $pdo = $db->getPdo();

        $handle = gzopen($path, 'wb6');
        if ($handle === false) {
            throw new BackupException("Impossible d'ecrire le fichier de sauvegarde.");
        }

        // Une seule vue coherente de la base pendant tout l'export (InnoDB, isolation REPEATABLE READ)
        $ownTransaction = !$pdo->inTransaction() && $pdo->beginTransaction();
        try {
            $this->write($handle, "-- NovaCreneaux : sauvegarde de la base de donnees\n-- Date : " . now()->toIso8601String() . "\n-- Pilote : {$driver}\n\n");
            $this->write($handle, $mysql ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n" : "PRAGMA foreign_keys=OFF;\n\n");

            foreach ($this->tables($pdo, $mysql) as $table) {
                $this->dumpTable($handle, $pdo, $mysql, $table);
            }

            $this->write($handle, $mysql ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n");
            $this->write($handle, self::END_MARKER . "\n");
        } finally {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack(); // lecture seule : rien a valider
            }
            if (!gzclose($handle)) {
                throw new BackupException("Impossible de terminer le fichier de sauvegarde.");
            }
        }
    }

    /** Une archive complete se termine par ce marqueur : un fichier tronque (disque plein) ne l'a pas. */
    public static function isComplete(string $path): bool {
        $handle = is_file($path) ? @gzopen($path, 'rb') : false;
        if ($handle === false) {
            return false;
        }
        $tail = '';
        while (!gzeof($handle)) {
            $chunk = gzread($handle, 8192);
            if ($chunk === false) {
                break;
            }
            $tail = substr($tail . $chunk, -256);
        }
        gzclose($handle);

        return str_contains($tail, self::END_MARKER);
    }

    private function tables(PDO $pdo, bool $mysql): array {
        $sql = $mysql
            ? "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"
            : "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    private function dumpTable($handle, PDO $pdo, bool $mysql, string $table): void {
        $quoted = $this->identifier($table, $mysql);
        $this->write($handle, "-- Table {$table}\nDROP TABLE IF EXISTS {$quoted};\n" . $this->createStatement($pdo, $mysql, $table) . ";\n\n");

        if (in_array($table, self::STRUCTURE_ONLY, true)) {
            return;
        }

        // MySQL : resultat lu ligne a ligne, sans charger toute la table en memoire
        $options = $mysql && defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY') ? [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false] : [];
        $rows = $pdo->prepare("SELECT * FROM {$quoted}", $options);
        $rows->execute();

        $batch = [];
        while (($row = $rows->fetch(PDO::FETCH_NUM)) !== false) {
            $batch[] = '(' . implode(',', array_map(fn ($value) => $this->literal($pdo, $value), $row)) . ')';
            if (count($batch) >= self::ROWS_PER_INSERT) {
                $this->write($handle, "INSERT INTO {$quoted} VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        $rows->closeCursor();

        if ($batch !== []) {
            $this->write($handle, "INSERT INTO {$quoted} VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        $this->write($handle, "\n");
    }

    private function createStatement(PDO $pdo, bool $mysql, string $table): string {
        if ($mysql) {
            return $pdo->query('SHOW CREATE TABLE ' . $this->identifier($table, true))->fetch(PDO::FETCH_NUM)[1];
        }
        $statement = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?");
        $statement->execute([$table]);

        return $statement->fetchColumn();
    }

    private function identifier(string $name, bool $mysql): string {
        return $mysql ? '`' . str_replace('`', '``', $name) . '`' : '"' . str_replace('"', '""', $name) . '"';
    }

    private function literal(PDO $pdo, mixed $value): string {
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return match (true) {
            $value === null => 'NULL',
            is_int($value) => (string) $value,
            is_float($value) => var_export($value, true),
            $value !== '' && !mb_check_encoding($value, 'UTF-8') => "X'" . bin2hex($value) . "'",
            default => $pdo->quote((string) $value),
        };
    }

    private function write($handle, string $sql): void {
        $written = gzwrite($handle, $sql);
        if ($written === false || ($sql !== '' && $written === 0)) {
            throw new BackupException("Ecriture impossible dans le fichier de sauvegarde (disque plein ?).");
        }
    }
}
