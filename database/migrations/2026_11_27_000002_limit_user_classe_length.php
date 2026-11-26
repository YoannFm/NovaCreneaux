<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * La classe a desormais la meme longueur maximale partout (User::CLASSE_MAX, 100 caracteres). Une classe plus longue,
 * venue d'un ancien import CSV, est raccourcie : sinon la fiche de l'eleve ne pouvait plus etre enregistree.
 */
return new class extends Migration
{
    /** Valeur figee ici : la migration ne doit pas changer de sens si la constante change un jour. */
    private const MAX = 100;

    public function up(): void
    {
        DB::table('users')->whereNotNull('classe')->select('id', 'classe')->orderBy('id')->each(function ($row) {
            if (mb_strlen($row->classe) > self::MAX) {
                DB::table('users')->where('id', $row->id)->update(['classe' => mb_substr($row->classe, 0, self::MAX)]);
                Log::info("Classe du compte #{$row->id} raccourcie a " . self::MAX . ' caracteres.');
            }
        });
    }

    public function down(): void
    {
        // Les caracteres retires ne sont pas conserves
    }
};
