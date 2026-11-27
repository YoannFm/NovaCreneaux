<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Heures au format HH:MM:SS partout (App\Casts\TimeOfDay). MySQL les stocke deja ainsi : seule une base SQLite peut
 * garder "12:00", qui ferait croire une fois l'horaire modifie au prochain enregistrement du creneau.
 */
return new class extends Migration
{
    private const COLUMNS = ['slots' => ['start_time', 'end_time'], 'resources' => ['open_time', 'close_time']];

    public function up(): void
    {
        // Une colonne TIME de MySQL ou MariaDB est deja en HH:MM:SS (et || n'y concatene pas)
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->whereRaw("length({$column}) = 5")->update([$column => DB::raw("{$column} || ':00'")]);
            }
        }
    }

    public function down(): void
    {
        // Rien a defaire : HH:MM:SS est lu correctement par toutes les versions
    }
};
