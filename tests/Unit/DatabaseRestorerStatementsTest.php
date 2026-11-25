<?php

namespace Tests\Unit;

use App\Services\Backup\DatabaseRestorer;
use PHPUnit\Framework\TestCase;

/** Decoupage d'une sauvegarde en instructions : jamais au milieu d'une chaine. */
class DatabaseRestorerStatementsTest extends TestCase
{
    private function split(string $sql, bool $backslashEscapes = true): array
    {
        return iterator_to_array(DatabaseRestorer::statements($sql, $backslashEscapes), false);
    }

    public function test_semicolons_and_dashes_inside_strings_do_not_split(): void
    {
        $sql = "-- Table `a`\nDROP TABLE IF EXISTS `a`;\nINSERT INTO `a` VALUES\n(1,'x ; y -- z'),\n(2,'l''apostrophe');\n-- Fin de la sauvegarde\n";

        $this->assertSame([
            'DROP TABLE IF EXISTS `a`',
            "INSERT INTO `a` VALUES\n(1,'x ; y -- z'),\n(2,'l''apostrophe')",
        ], $this->split($sql));
    }

    public function test_mysql_backslash_escapes_keep_the_string_open(): void
    {
        $this->assertSame(["INSERT INTO t VALUES ('a\\';b')", 'SELECT 1'], $this->split("INSERT INTO t VALUES ('a\\';b');SELECT 1;"));
    }

    public function test_sqlite_treats_a_backslash_as_a_plain_character(): void
    {
        $this->assertSame(["INSERT INTO t VALUES ('a\\')", 'SELECT 1'], $this->split("INSERT INTO t VALUES ('a\\');SELECT 1;", false));
    }

    public function test_quoted_identifiers_can_contain_a_semicolon(): void
    {
        $this->assertSame(['CREATE TABLE "a;b" (x)'], $this->split('CREATE TABLE "a;b" (x);'));
    }
}
