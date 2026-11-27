<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dernier pas de temps TOTP accepte : un code deja utilise est refuse s'il est presente une seconde fois. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('two_factor_last_step'));
    }
};
