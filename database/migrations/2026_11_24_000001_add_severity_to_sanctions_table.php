<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Gravite d'une suspension (voir Sanction::SEVERITIES) ; vide pour les sanctions anterieures et les avertissements. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sanctions', function (Blueprint $table) {
            $table->string('severity', 16)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('sanctions', function (Blueprint $table) {
            $table->dropColumn('severity');
        });
    }
};
