<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rejoue une sauvegarde de DatabaseDumper (DROP, CREATE puis INSERT de chaque table) sur la base du site.
 * Les instructions sont executees une a une : avec plusieurs instructions dans un seul appel, PDO MySQL
 * ne signalerait que l'erreur de la premiere. Une sauvegarde incomplete est refusee avant de rien toucher.
 */
class DatabaseRestorer
{
    /** @return int nombre d'instructions executees */
    public function restore(string $path, ?string $connection = null): int
    {
        if (!DatabaseDumper::isComplete($path)) {
            throw new BackupException('Cette sauvegarde est incomplète : elle ne peut pas être restaurée.');
        }
        $sql = gzdecode((string) file_get_contents($path));
        if ($sql === false) {
            throw new BackupException('Cette sauvegarde est illisible.');
        }

        $db = DB::connection($connection);
        $pdo = $db->getPdo();
        $count = 0;
        $tables = [];
        foreach (self::statements($sql, $db->getDriverName() !== 'sqlite') as $statement) {
            if (preg_match('/^DROP TABLE IF EXISTS [`"](.+)[`"]$/', $statement, $match)) {
                $tables[] = $match[1];
            }
            try {
                $pdo->exec($statement);
            } catch (\PDOException $e) {
                throw new BackupException("La restauration s'est arrêtée à l'instruction " . ($count + 1) . ' : ' . $e->getMessage());
            }
            $count++;
        }
        $this->dropTablesMissingFrom($tables, $connection);

        return $count;
    }

    /**
     * Supprime les tables creees apres la sauvegarde (migrations d'une mise a jour annulee) : sans cela, la table
     * "migrations" restauree ne les connait plus et la prochaine mise a jour echouerait sur "table deja existante".
     */
    private function dropTablesMissingFrom(array $tables, ?string $connection): void
    {
        $schema = Schema::connection($connection);
        $extra = array_diff($schema->getTableListing(schemaQualified: false), $tables);
        if ($extra === []) {
            return;
        }
        $schema->withoutForeignKeyConstraints(function () use ($schema, $extra) {
            foreach ($extra as $table) {
                $schema->dropIfExists($table);
            }
        });
    }

    /**
     * Decoupe un script SQL en instructions, sans couper a l'interieur d'une chaine ('...', "...", `...`) et en
     * ignorant les commentaires "-- " de debut de ligne. $backslashEscapes : MySQL echappe par \ dans les chaines.
     *
     * @return \Generator<string>
     */
    public static function statements(string $sql, bool $backslashEscapes): \Generator
    {
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $buffer .= $char;
                if ($backslashEscapes && $quote === "'" && $char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                } elseif ($char === $quote) {
                    if (($sql[$i + 1] ?? '') === $quote) {
                        $buffer .= $sql[++$i]; // guillemet double ('') : reste dans la chaine
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
            } elseif ($char === '-' && ($sql[$i + 1] ?? '') === '-' && trim(substr($buffer, (int) strrpos("\n" . $buffer, "\n"))) === '') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
            } elseif ($char === ';') {
                if (trim($buffer) !== '') {
                    yield trim($buffer);
                }
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        if (trim($buffer) !== '') {
            yield trim($buffer);
        }
    }
}
