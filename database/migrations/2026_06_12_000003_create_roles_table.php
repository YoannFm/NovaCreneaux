<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('label');
                $table->string('description')->nullable();
                $table->string('color')->default('secondary');
                $table->boolean('is_admin')->default(false);
                $table->boolean('locked')->default(false);
                $table->timestamps();
            });
        }

        // Seed default roles if missing
        $now = now();
        if (DB::table('roles')->where('name', 'admin')->doesntExist()) {
            DB::table('roles')->insert([
                ['name' => 'admin', 'label' => 'Administrateur', 'description' => "Acces complet au panneau d'administration.", 'color' => 'primary',   'is_admin' => true,  'locked' => true,  'created_at' => $now, 'updated_at' => $now],
                ['name' => 'user',  'label' => 'Utilisateur',    'description' => 'Acces standard - reservation de creneaux.',   'color' => 'secondary', 'is_admin' => false, 'locked' => true,  'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        // Add role_id to users if missing
        if (!Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete()->after('is_active');
            });
        }

        // Drop old string role column if it exists
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            });
        }
        Schema::dropIfExists('roles');
    }
};
