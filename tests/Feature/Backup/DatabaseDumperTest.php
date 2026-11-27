<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\BackupException;
use App\Services\Backup\DatabaseDumper;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class DatabaseDumperTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir() . '/nc-dump-' . bin2hex(random_bytes(4)) . '.sql.gz';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function sql(): string
    {
        return gzdecode(file_get_contents($this->file));
    }

    /** Nom de table tel que la sauvegarde l'ecrit : la suite tourne sur SQLite et sur MySQL (CI). */
    private function quoted(string $table): string
    {
        return DB::getDriverName() === 'sqlite' ? "\"{$table}\"" : "`{$table}`";
    }

    /** Une autre base, vide, du meme moteur, dans laquelle on rejoue la sauvegarde. */
    private function restoredCopy(): PDO
    {
        if (DB::getDriverName() === 'sqlite') {
            $copy = new PDO('sqlite::memory:');
        } else {
            $config = DB::connection()->getConfig();
            $copy = new PDO("mysql:host={$config['host']};port={$config['port']};charset=utf8mb4", $config['username'], $config['password']);
            $name = $config['database'] . '_copie';
            $copy->exec("DROP DATABASE IF EXISTS `{$name}`; CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; USE `{$name}`");
        }
        $copy->exec($this->sql());

        return $copy;
    }

    public function test_the_dump_is_a_complete_gzip_file_with_structure_and_data(): void
    {
        DB::table('settings')->insert(['key' => 'site_name', 'value' => 'CDI']);

        (new DatabaseDumper())->dump($this->file);

        $sql = $this->sql();
        $this->assertStringStartsWith('-- NovaCreneaux : sauvegarde de la base de donnees', $sql);
        $this->assertStringContainsString('CREATE TABLE ' . $this->quoted('settings'), $sql);
        $this->assertStringContainsString("'site_name','CDI'", $sql);
        $this->assertTrue(DatabaseDumper::isComplete($this->file));
    }

    public function test_every_kind_of_value_survives_a_round_trip_into_another_database(): void
    {
        $binary = "\x00\xFF\xFE binaire";
        $tricky = "O'Brien \"guillemets\"\nligne 2 \\ antislash ; -- pas un commentaire \u{1F4DA} accents éàü";
        DB::table('settings')->insert([
            ['key' => 'tricky', 'value' => $tricky],
            ['key' => 'empty', 'value' => ''],
            ['key' => 'nothing', 'value' => null],
        ]);
        // Des octets qui ne sont pas de l'UTF-8 : MySQL les refuse deja dans une colonne de texte, seul SQLite les garde
        $withBinary = DB::getDriverName() === 'sqlite';
        if ($withBinary) {
            DB::table('settings')->insert(['key' => 'binary', 'value' => $binary]);
        }
        DB::table('users')->insert(['name' => 'Élève', 'email' => 'eleve@example.com', 'password' => 'x', 'is_active' => 1, 'created_at' => '2026-10-26 10:00:00', 'updated_at' => '2026-10-26 10:00:00']);

        (new DatabaseDumper())->dump($this->file);

        $copy = $this->restoredCopy();

        $values = $copy->query('SELECT ' . $this->quoted('key') . ', value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame($tricky, $values['tricky']);
        $this->assertSame('', $values['empty']);
        $this->assertNull($values['nothing']);
        if ($withBinary) {
            $this->assertSame($binary, $values['binary']);
        }
        $this->assertSame(DB::table('users')->count(), (int) $copy->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->assertSame('Élève', $copy->query('SELECT name FROM users')->fetchColumn());
    }

    public function test_more_rows_than_one_insert_are_split_and_none_is_lost(): void
    {
        DB::table('settings')->insert(array_map(fn ($i) => ['key' => "cle_{$i}", 'value' => "valeur {$i}"], range(1, 250)));

        (new DatabaseDumper())->dump($this->file);

        $this->assertGreaterThan(2, substr_count($this->sql(), 'INSERT INTO ' . $this->quoted('settings')));
        $copy = $this->restoredCopy();
        $this->assertSame(DB::table('settings')->count(), (int) $copy->query('SELECT COUNT(*) FROM settings')->fetchColumn());
    }

    public function test_temporary_tables_keep_their_structure_but_not_their_rows(): void
    {
        DB::table('cache')->insert(['key' => 'k', 'value' => 'donnee temporaire', 'expiration' => 9999999999]);

        (new DatabaseDumper())->dump($this->file);

        $this->assertStringContainsString('CREATE TABLE ' . $this->quoted('cache'), $this->sql());
        $this->assertStringNotContainsString('donnee temporaire', $this->sql());
    }

    public function test_a_truncated_file_is_not_considered_complete(): void
    {
        file_put_contents($this->file, gzencode("-- NovaCreneaux\nCREATE TABLE t (id int);\n"));

        $this->assertFalse(DatabaseDumper::isComplete($this->file));
        $this->assertFalse(DatabaseDumper::isComplete($this->file . '.absent'));
    }

    public function test_an_unsupported_driver_is_refused_with_a_clear_message(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn('pgsql');
        DB::partialMock()->shouldReceive('connection')->andReturn($connection);

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('pgsql');

        (new DatabaseDumper())->dump($this->file);
    }
}
