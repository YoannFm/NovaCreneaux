<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une ressource ou un creneau supprime n'est plus efface de la base : sinon la suppression en cascade emportait
 * toutes ses reservations, passees comprises (historique de l'eleve, planning des jours passes, exports).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['resources', 'slots'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (['resources', 'slots'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
