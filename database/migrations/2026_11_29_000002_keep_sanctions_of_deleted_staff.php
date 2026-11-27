<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une sanction survit au compte qui l'a posee : la suppression en cascade sur admin_id effacait les suspensions et les
 * avertissements poses par un membre du personnel dont on supprimait le compte (l'eleve suspendu reservait de nouveau).
 * admin_id devient facultatif et passe a null : "par un compte supprime".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanctions', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
        });
        Schema::table('sanctions', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->change();
            $table->foreign('admin_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Pas de retour a la cascade : elle effacait des sanctions encore en cours
    }
};
